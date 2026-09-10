<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\RateLimiter; 
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Services\LineMessagingService;
use App\Jobs\ArchiveExternalDocument;
use App\Jobs\ArchiveV2ExternalDocument;
use App\Services\AuditLogger;
use App\Services\NotificationDispatcher;
use App\Services\AssignmentNotificationService;
use App\Services\DocumentFileStorage;
use App\Jobs\ProcessDocumentExtraction;
use App\Models\DocumentExtractionTask;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\V2\DocumentReadService as V2DocumentReadService;
use App\Services\V2\DocumentWriteService as V2DocumentWriteService;
use App\Models\V2\User as V2User;


class DocumentController extends Controller
{
    // ========================================================================
    // --- โซนที่ 1: ฟังก์ชันพื้นฐาน (สำหรับทุกคน และธุรการ) ---
    // ========================================================================

    public function index()
    {
        return redirect()->route('home'); 
    }

    public function show($id, V2DocumentReadService $v2Documents)
    {
       if (config('edoc.v2.document_reads')) {
           return $this->showV2($id, $v2Documents);
       }

       $document = Document::with(['creator', 'supervisor', 'palad', 'nayok', 'routes.user'])
            ->whereIdentifier($id)->firstOrFail();
            
        // 🌟 1. ประกาศตัวแปรจดจำ ID หน้าเดิมไว้
        $targetDocId = $document->uuid ?? $document->id;
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('view', $document);

        // Dynamic workflow เปิดเผยเอกสารตามลำดับคิวเท่านั้น ผู้รับในอนาคต
        // แม้ทราบ URL ก็ยังเปิดไม่ได้จนกว่าคิวก่อนหน้าจะดำเนินการเสร็จ
        if ($document->routes->isNotEmpty() && !$user->hasRole('super-admin')) {
            $routeForUser = $document->routes->firstWhere('user_id', $user->id);
            $hasBookingAccess = \App\Models\RoomBooking::where('document_id', $document->id)
                ->where(function ($bookingQuery) use ($user) {
                    $bookingQuery->where('created_by', $user->id)
                        ->orWhereHas('invitees', function ($inviteeQuery) use ($user) {
                            $inviteeQuery->where('users.id', $user->id);
                        });
                })->exists();
            $assignmentIsActive = in_array($document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
                && in_array($document->assignment_status, ['pending', 'accepted', 'delegated', 'in_progress', 'completed'], true);
            $hasAssignmentAccess = $assignmentIsActive && ($document->assigned_user_id === $user->id
                || ($user->hasRole('head') && $document->assigned_to === $user->department));
            $hasReachedUser = $routeForUser
                && $document->current_step !== null
                && $routeForUser->step_order <= $document->current_step;

            if ($document->created_by !== $user->id && !$hasReachedUser && !$hasAssignmentAccess && !$hasBookingAccess) {
                abort(403, 'เอกสารฉบับนี้ยังไม่ถึงลำดับพิจารณาของคุณ');
            }
        }

        // ⚠️ หมายเหตุ: เอาโค้ดเช็ค "status !== DRAFT แล้ว redirect" ออกจากตรงนี้
        // เพราะ show() มีหน้าที่แค่แสดงผลเอกสาร ไม่ควรกันเหมือน sign()/store()
        // ตัวกันกดซ้ำที่ถูกต้องอยู่ใน sign() บรรทัด 255-258 อยู่แล้ว

        // ==========================================
        // 🔒 ระบบความปลอดภัย: ตรวจสอบเอกสารลับ
        // ==========================================
        if (in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'])) {
            
            // สิทธิ์มาตรฐาน (เจ้าของเรื่อง หรือ ตำแหน่งผู้บริหาร/ธุรการ)
            $isAllowed = $user->id === $document->created_by || 
                         (isset($hasBookingAccess) && $hasBookingAccess) ||
                         $document->routes->contains(function ($route) use ($user, $document) {
                             return $route->user_id === $user->id
                                 && $route->step_order <= $document->current_step;
                         }) ||
                         $user->hasRole('saraban') || 
                         $user->hasRole('head') || 
                         $user->hasRole('palad') || 
                         $user->hasRole('deputy-palad') || 
                         $user->hasRole('executive');
            
            // เช็คว่าเคยขอสิทธิ์และ "ได้รับการอนุมัติ" หรือไม่
            $hasApprovedRequest = \App\Models\DocumentAccessRequest::where('document_id', $document->id)
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->exists();

            // ถ้าไม่มีสิทธิ์แต่แรก และยังไม่เคยได้รับการอนุมัติ
            if (!$isAllowed && !$hasApprovedRequest) {
                // ดึงสถานะคำขอปัจจุบัน (ถ้ามี)
                $accessRequest = \App\Models\DocumentAccessRequest::where('document_id', $document->id)
                    ->where('user_id', $user->id)
                    ->first();
                    
                // โยนไปหน้า "ขอสิทธิ์"
                return view('documents.request_access', compact('document', 'accessRequest'));
            }

            // ระบบจับเวลา 3 นาที (180 วินาที) ของการเปิดเอกสารลับ
            $unlockedAt = session('secret_unlocked_' . $document->id);
            $timeoutSeconds = 180; // ตั้งเวลาหมดอายุที่ 3 นาที

            if (!$unlockedAt || (now()->timestamp - $unlockedAt) > $timeoutSeconds) {
                // ถ้าไม่เคยปลดล็อก หรือ เวลาผ่านไปเกิน 3 นาทีแล้ว ให้เคลียร์ session ทิ้ง
                session()->forget('secret_unlocked_' . $document->id);
                
                // โยนไปหน้ากรอกรหัสผ่าน
                return view('documents.unlock_secret', compact('document'));
            } else {
                // ต่อเวลาให้ ถ้าผู้ใช้กำลังอ่านหรือรีเฟรชหน้าเอกสารนี้อยู่
                session(['secret_unlocked_' . $document->id => now()->timestamp]);
                app(AuditLogger::class)->log('document.secret_viewed', $document);
            }
        }

        // ==========================================
        // 🌟 ระบบเข้าคิวพิจารณาเอกสาร (Workflow Queue)
        // ==========================================
        
        // 🌟 ตรวจสอบคิวพิจารณาปัจจุบัน
        $currentRoute = $document->routes->where('step_order', $document->current_step)->first();

        $canApprove = false;
        // 🌟 ดักไว้ว่าคิวที่ 1 (คนสร้าง) จะไม่เห็นปุ่ม "อนุมัติ" เพราะต้องไปกด "ลงนามส่งเรื่อง"
        if ($currentRoute && $currentRoute->user_id === $user->id && $currentRoute->status === 'pending' && $document->current_step > 1) {
            $canApprove = true; 
        }

        // ==========================================
        // 🖥️ ส่งข้อมูลไปแสดงผลที่หน้าเว็บ (แยกตามประเภทเอกสาร)
        // ==========================================
        if ($document->doc_type === 'outgoing') {
            return view('documents.show_outgoing', compact('document', 'currentRoute', 'canApprove'));
        } 
        elseif ($document->doc_type === 'internal') {
            return view('documents.show_internal', compact('document', 'currentRoute', 'canApprove'));
        } 
        elseif ($document->doc_type === 'incoming') {
            return view('documents.show_incoming', compact('document', 'currentRoute', 'canApprove'));
        }

        return view('documents.show', compact('document', 'currentRoute', 'canApprove'));
    }

    private function showV2(string|int $id, V2DocumentReadService $documents)
    {
        $document = $documents->find($id);
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->authorize('view', $document);

        if ($document->confidentiality?->level_no > 1) {
            $this->authorize('accessConfidential', $document);
            $unlockedAt = session('secret_unlocked_'.$document->id);
            if (! $unlockedAt || (now()->timestamp - $unlockedAt) > 180) {
                session()->forget('secret_unlocked_'.$document->id);
                return view('documents.unlock_secret', compact('document'));
            }
            session(['secret_unlocked_'.$document->id => now()->timestamp]);
        }

        $currentRoute = $document->routes->firstWhere('step_order', $document->current_step);
        $routeStatus = $currentRoute?->status instanceof \BackedEnum
            ? $currentRoute->status->value
            : $currentRoute?->status;
        $canApprove = config('edoc.v2.write_enabled') && $currentRoute
            && $currentRoute->assigned_user_id === $user->id
            && strtoupper((string) $routeStatus) === 'PENDING'
            && $document->current_step > 1;

        $view = match ($document->doc_type) {
            'outgoing' => 'documents.show_outgoing',
            'internal' => 'documents.show_internal',
            'incoming' => 'documents.show_incoming',
            default => 'documents.show',
        };

        return view($view, compact('document', 'currentRoute', 'canApprove'));
    }

    public function createUpload()
    {
        return view('documents.create_upload', ['users' => $this->routeUsers()]);
    }

    public function storeUpload(Request $request, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            return $this->storeV2Document($request, $v2Writes, 'internal', true);
        }
        // 🌟 ตัด doc_number และ running_number ออกจากการบังคับกรอก (รอธุรการออกให้ทีหลัง)
        $request->validate([
            'doc_from'       => 'required|string|max:255',
            'title'          => 'required|string|max:255',
            'doc_to'         => 'required|string|max:255',
            'doc_date'       => 'required|date',
            'file'           => 'required|file|mimes:pdf|max:5120',
            // 🌟 เพิ่มให้รองรับชั้นความลับและชั้นความเร็วในการอัปโหลด
            'doc_secret'     => 'nullable|string|max:50',
            'doc_speed'      => 'nullable|string|max:50',
            'routing_users'   => 'required|array|min:1',
            'routing_users.*' => 'required|integer|distinct|exists:users,id|not_in:' . Auth::id(),
        ], [
            'doc_from.required'       => 'กรุณาระบุส่วนราชการ',
            'title.required'          => 'กรุณาระบุเรื่อง',
            'doc_to.required'         => 'กรุณาระบุผู้รับ (เรียน)',
            'file.required'           => 'กรุณาแนบไฟล์เอกสาร PDF',
            'file.mimes'              => 'รองรับเฉพาะไฟล์ PDF เท่านั้น',
            'file.max'                => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
        ]);

        $document = new Document();
        $document->doc_type       = 'internal';
        $document->doc_from       = $request->doc_from;
        
        // 🌟 เซ็ตค่าให้เป็นค่าว่างทั้งหมด รอธุรการมาใส่เลขให้เมื่ออนุมัติเสร็จ
        $document->doc_number     = null; 
        $document->running_number = null; 
        
        $document->title          = $request->title;
        $document->doc_to         = $request->doc_to;
        $document->doc_date       = $request->doc_date;
        $document->content        = 'อ้างอิงจากไฟล์แนบในระบบ'; // 🌟 แบบอัปโหลดไม่มีการพิมพ์เนื้อหา ให้ใส่คำนี้แทน
        $document->created_by     = Auth::id();
        $document->status         = 'DRAFT'; 
        $document->current_step   = 1;
        
        // 🌟 บันทึกชั้นความลับและความเร็ว (ถ้าไม่ได้เลือกจะตั้งเป็น ปกติ อัตโนมัติ)
        $document->doc_secret     = $request->doc_secret ?? 'ปกติ';
        $document->doc_speed      = $request->doc_speed ?? 'ปกติ';

        if ($request->hasFile('file')) {
            $path = app(DocumentFileStorage::class)->store($request->file('file'), 'attachments');
            $document->attachment_path = $path;
        }

        $document->save();
        $this->saveDocumentRoute($document, $request->input('routing_users', []));

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'อัปโหลดบันทึกข้อความเรียบร้อย กรุณาลงนามเพื่อส่งเรื่อง');
    }

        public function create()
    {
        // 🌟 1. ดึงรายชื่อผู้ใช้งานทั้งหมดจากฐานข้อมูล (ยกเว้นตัวเอง)
        $users = $this->routeUsers();
        
        // 🌟 2. ส่งตัวแปร $users แนบไปให้หน้าเว็บด้วยคำสั่ง compact('users')
        return view('documents.create', compact('users'));
    }

    public function store(Request $request, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            return $this->storeV2Document($request, $v2Writes, 'internal', false);
        }
        $request->validate([
            'title'    => 'required|string|max:255',
            'content'  => 'required|string',
            'doc_date' => 'required'
            ,'routing_users' => 'required|array|min:1'
            ,'routing_users.*' => 'required|integer|distinct|exists:users,id|not_in:' . Auth::id()
        ]);

        // 1. สร้างและบันทึกเอกสาร
        $document = new Document();
        $document->doc_type   = 'internal';
        $document->title      = $request->title;
        $document->doc_number = $request->doc_number;
        $document->doc_date   = $request->doc_date;
        $document->content    = trim(preg_replace('/\n{3,}/', "\n\n", $request->content));
        $document->running_number = $request->running_number;
        $document->created_by = Auth::id();
        $document->status     = 'DRAFT';
        $document->current_step = 1; 
        $document->save();

        // ====================================================
        // 🌟 แก้ไขส่วนบันทึกเส้นทางตรงนี้ครับ ให้ก๊อปปี้ไปทับเลย
        // ====================================================

        // 🌟 1. บังคับยัดคนสร้าง (คนปัจจุบัน) เป็นคิวที่ 1 เสมอ (อยู่นอกลูป ทำรอบเดียว)
        $this->saveDocumentRoute($document, $request->input('routing_users', []));
        // ====================================================

        // 3. เด้งกลับไปหน้าแสดงเอกสาร (ต้องอยู่ล่างสุดเสมอ)
        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกร่างเอกสารและกำหนดเส้นทางสำเร็จ กรุณาตรวจสอบและลงนาม');
    }

    public function sign(Request $request, $id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $request->validate(['pin' => 'required|digits:6']);
            $document = $v2Documents->find($id);
            $this->authorize('update', $document);
            if ($document->doc_type === 'incoming'
                && $document->external_url
                && ! $document->attachment_path
                && ! $document->external_attachment_path) {
                return redirect()->route('documents.show', $document->uuid)->with(
                    'error',
                    'ไม่สามารถส่งเรื่องได้ กรุณาเปิดลิงก์ต้นฉบับ ดาวน์โหลดเอกสาร แล้วแนบไฟล์ที่ดาวน์โหลดมาก่อนส่งเรื่อง'
                );
            }
            $actor = $this->validatedV2Signer($request, 'pin-sign-attempts:');
            $submittedDocument = $v2Writes->submit($document, $actor);
            $this->notifySarabanForV2Numbering($submittedDocument);
            $this->notifyCurrentV2Reviewer($submittedDocument);
            return redirect()->route('documents.show', $document->uuid)->with('success', 'ลงนามและส่งเรื่องเข้าสู่คิวพิจารณาเรียบร้อยแล้ว');
        }
        $request->validate(['pin' => 'required|digits:6']);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
        
        // 🌟 1. ดึงข้อมูลเอกสาร (เพิ่ม 'routes.user' เข้ามาด้วย)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok', 'routes.user'])
            ->whereIdentifier($id)->lockForUpdate()->firstOrFail();
            
        // 🌟 2. กำหนดตัวแปรอ้างอิงหน้าเดิม
        $targetDocId = $document->uuid ?? $document->id;

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 🌟 3. ดักตรวจสอบสิทธิ์และสถานะเอกสาร (ห้ามเซ็นซ้ำ)
        if ($document->status !== 'DRAFT') {
            return redirect()->route('documents.show', $targetDocId)
                ->with('error', 'เอกสารฉบับนี้ได้รับการลงนามส่งเรื่องไปแล้ว ไม่สามารถลงนามซ้ำได้');
        }

        if ($document->created_by !== $user->id) {
            return redirect()->route('documents.show', $targetDocId)
                ->with('error', 'คุณไม่มีสิทธิ์ลงนามในเอกสารฉบับนี้');
        }

        if ($document->doc_type === 'incoming'
            && $document->external_url
            && ! $document->attachment_path
            && ! $document->external_attachment_path) {
            return redirect()->route('documents.show', $targetDocId)->with(
                'error',
                'ไม่สามารถส่งเรื่องได้ กรุณาเปิดลิงก์ต้นฉบับ ดาวน์โหลดเอกสาร แล้วแนบไฟล์ที่ดาวน์โหลดมาก่อนส่งเรื่อง'
            );
        }

        $throttleKey = 'pin-sign-attempts:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            // 🌟 เปลี่ยนจาก back()
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_pin', 'คุณกรอกรหัส PIN ผิดเกินกำหนด (5 ครั้ง) กรุณารออีก ' . $seconds . ' วินาที');
        }

        if (empty($user->signature) || empty($user->pin)) {
            // 🌟 เปลี่ยนจาก back()
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_signature', 'กรุณาอัปโหลดลายเซ็นและตั้งรหัส PIN ก่อนทำการลงนามส่งเรื่องครับ');
        }

        if (!Hash::check($request->pin, $user->pin)) {
            RateLimiter::hit($throttleKey, 60); 
            // 🌟 เปลี่ยนจาก back()
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_pin', 'รหัสผ่าน (PIN) ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        RateLimiter::clear($throttleKey);

        if ($document->status === 'DRAFT' && $document->created_by === $user->id) {
            
            $document->creator_signature = $user->signature;

            // ==========================================
            // 🌟 4. ระบบคิว (Dynamic Workflow)
            // ==========================================
            
            // 4.1 ติ๊กผ่าน (Approve) ให้คิวที่ 1 (คนสร้าง)
            $creatorRoute = $document->routes->where('step_order', 1)->where('user_id', $user->id)->first();
            if ($creatorRoute) {
                $creatorRoute->update(['status' => 'approved']);
            }

            // 4.2 ดึงคิวที่ 2 (คนที่ส่งให้พิจารณาคนแรก)
            $nextRoute = $document->routes->where('step_order', 2)->first();

            if ($nextRoute) {
                // หากมีการตั้งคิวไว้ ให้เปลี่ยนสถานะและเลื่อนคิวไปขั้น 2
                $hasRouteAfterNext = $document->routes
                    ->contains(fn ($route) => (int) $route->step_order > (int) $nextRoute->step_order);
                $document->status = $hasRouteAfterNext ? 'PROCESSING' : 'WAITING_APPROVER';
                $document->current_step = 2; // 🌟 เลื่อนไปคิว 2
                $success_msg = 'ลงนามและส่งเรื่องให้ ' . ($nextRoute->user?->name ?? 'ผู้พิจารณาท่านต่อไป') . ' พิจารณาเรียบร้อยแล้ว';            } else {
                // ==========================================
                // 🌟 ระบบเดิม (Legacy Role-based) กรณีไม่ได้ระบุคิว
                // ==========================================
                if ($document->doc_type === 'outgoing') {
                    if ($user->hasRole('saraban')) {
                        if (str_contains($document->signer_name ?? '', 'นายก')) {
                            $document->status = 'WAITING_NAYOK';
                        } elseif (str_contains($document->signer_name ?? '', 'ปลัด')) {
                            $document->status = 'WAITING_PALAD';
                        } else {
                            $document->status = 'WAITING_SUPERVISOR';
                        }
                        $success_msg = 'ลงนามและส่งเอกสารให้ผู้ลงนามพิจารณาเรียบร้อยแล้ว';
                    } else {
                        $document->status = 'WAITING_NUMBERING'; 
                        $success_msg = 'ลงนามและส่งเรื่องให้ธุรการตรวจสอบและออกเลขเรียบร้อยแล้ว';
                    }
                } 
                else {
                    if ($user->hasRole('executive')) {
                        $document->status = 'WAITING_NUMBERING'; 
                        $success_msg = 'ลงนามเสนอเรื่องเรียบร้อยแล้ว (ส่งไปรอออกเลข)';
                    } elseif ($user->hasRole('palad') || $user->hasRole('deputy-palad')) {
                        $document->status = 'WAITING_NAYOK'; 
                        $success_msg = 'ลงนามเสนอเรื่องและส่งต่อให้ นายก อบต. พิจารณาเรียบร้อยแล้ว';
                    } elseif ($user->hasRole('head')) {
                        $document->supervisor_id = $user->id;
                        $document->supervisor_signature = $user->signature;
                        $document->supervisor_approved_at = now();
                        $document->supervisor_comment = 'ผู้เสนอเรื่องเป็นหัวหน้าส่วนราชการ';
                        $document->status = 'WAITING_PALAD'; 
                        $success_msg = 'ลงนามเสนอเรื่องและส่งต่อให้ ปลัด อบต. พิจารณาเรียบร้อยแล้ว';
                    } elseif ($user->hasRole('saraban')) {
                        $document->status = 'WAITING_SUPERVISOR'; 
                        $success_msg = 'ลงนามและส่งเรื่องให้หัวหน้าสำนักปลัดตรวจสอบเรียบร้อยแล้ว';
                    } else {
                        $document->status = 'WAITING_ADMIN'; 
                        $success_msg = 'ลงนามและส่งเรื่องให้ธุรการตรวจสอบเรียบร้อยแล้ว';
                    }
                }
            }

            $document->save();

            // 🌟 เช็คเรื่องการประทับลายเซ็นผู้เสนอเรื่องบนไฟล์อัปโหลด 🌟
            if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                
                // ถ้ายูสเซอร์ติ๊กถูก (เอาประทับลายเซ็น)
                if ($request->has('stamp_creator') && $request->stamp_creator == '1') {
                    $this->autoStampCreator($document, $user);
                } else {
                    // ถ้าไม่ปั๊ม ให้ลบไฟล์ที่เคยปั๊มไปแล้ว (ถ้ามี) ทิ้งไป เพื่อป้องกันความสับสน
                    $stampedPath = 'documents/stamped/stamped_' . $document->id . '.pdf';
                    if (app(DocumentFileStorage::class)->exists($stampedPath)) {
                         app(DocumentFileStorage::class)->delete($stampedPath);
                    }
                }

                // สั่งหุ่นยนต์ทำใบแนบท้ายตามปกติ
                $this->autoAppendSignaturePage($document);
            }

           // =========================================================
            // 🌟 แจ้งเตือน LINE เมื่อมีการเซ็นส่งเรื่อง
            // =========================================================
            $docUrl = route('documents.show', $targetDocId);
            $lineMsg = "🔔 มีเอกสารรอการพิจารณาใหม่!\n";
            $lineMsg .= "เรื่อง: " . $document->title . "\n";
            $lineMsg .= "ผู้เสนอ: " . $user->name . " (" . ($user->department ?? '-') . ")\n";
            
            if ($document->doc_speed !== 'ปกติ') $lineMsg .= "ความเร็ว: ⚡" . $document->doc_speed . "\n";
            if ($document->doc_secret !== 'ปกติ' && $document->doc_secret !== 'ไม่มีชั้นความลับ') $lineMsg .= "ความลับ: 🔒" . $document->doc_secret . "\n";
            
            $lineMsg .= "\n👇 เปิดดูเอกสารที่นี่:\n" . $docUrl;

            // 🌟 เติม $document เข้าไปตรงนี้ เพื่อให้ระบบรู้ว่าต้องส่งหาใคร! 🌟
            $this->sendLineMessage($lineMsg, $document);
            // =========================================================

            // 🌟 เปลี่ยนเป้าหมายจาก route('home') เป็นให้เด้งกลับหน้าเดิม!
            return redirect()->route('documents.show', $targetDocId)->with('success', $success_msg);
        }

        return back()->with('error', 'คุณไม่มีสิทธิ์เซ็นเอกสารในขั้นตอนนี้');
        }, 3);
    }

    public function edit($id, V2DocumentReadService $v2Documents)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('update', $document);
            if ($document->doc_type === 'incoming') { return view('documents.edit_incoming', compact('document')); }
            if ($document->doc_type === 'outgoing') {
                $incomingDocs = \App\Models\V2\Document::with('type')->whereHas('type', fn ($types) => $types->where('code', 'INCOMING'))->latest()->limit(20)->get();
                $signers = \App\Models\User::role(['executive', 'palad', 'deputy-palad'])->get();
                return view('documents.edit_outgoing', compact('document', 'incomingDocs', 'signers'));
            }
            return str_contains(strip_tags((string) $document->content), 'อ้างอิงจากไฟล์แนบในระบบ')
                ? view('documents.edit_upload', compact('document')) : view('documents.edit', compact('document'));
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        $this->authorize('update', $document);

        if ($document->doc_type === 'incoming') {
            return view('documents.edit_incoming', compact('document'));
        } elseif ($document->doc_type === 'outgoing') {
            $incomingDocs = Document::where('doc_type', 'incoming')->orderBy('created_at', 'desc')->limit(20)->get();
            $signers = \App\Models\User::role(['executive', 'palad', 'deputy-palad'])
                ->orWhere(function($query) {
                    $query->role('head')->where('department', 'LIKE', '%สำนักงานปลัด%'); 
                })->get();
            return view('documents.edit_outgoing', compact('document', 'incomingDocs', 'signers'));
        } elseif ($document->doc_type === 'internal') {
            // 🌟 เพิ่มการแยกหน้า Edit สำหรับบันทึกข้อความแบบ "อัปโหลดไฟล์" 🌟
            if (str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                if (view()->exists('documents.edit_upload')) {
                    return view('documents.edit_upload', compact('document'));
                }
            }
        }

        return view('documents.edit', compact('document'));
    }

    public function update(Request $request, $id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('update', $document);
            $data = $this->validateV2Update($request, $document->doc_type, $document->content);
            $v2Writes->update($document, $data, Auth::id(), $request->file('file'));
            return redirect()->route('documents.show', $document->uuid)->with('success', 'แก้ไขเอกสาร V2 เรียบร้อยแล้ว กรุณาลงนามส่งเรื่องใหม่');
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        $this->authorize('update', $document);

        // 🌟 1. ลบไฟล์ที่ประทับลายเซ็นหรือต่อใบแนบท้ายเก่าทิ้งไป (เพราะเอกสารถูกแก้ไขเนื้อหาแล้ว)
        if ($document->signed_path) {
            app(DocumentFileStorage::class)->delete($document->signed_path);
        }
        $stampedPath = 'documents/stamped/stamped_' . $document->id . '.pdf';
        if (app(DocumentFileStorage::class)->exists($stampedPath)) {
            app(DocumentFileStorage::class)->delete($stampedPath);
        }

        // 🌟 2. เพิ่มการเคลียร์ signed_path และ creator_signature ให้กลับเป็นค่าว่าง
        $resetApprovals = [
            'status'                 => 'DRAFT',
            'current_step'           => 1,
            'signed_path'            => null,
            'creator_signature'      => null,
            'reject_reason'          => null,
            'supervisor_id'          => null,
            'supervisor_signature'   => null,
            'supervisor_comment'     => null,
            'supervisor_approved_at' => null,
            'palad_id'               => null,
            'palad_signature'        => null,
            'palad_comment'          => null,
            'palad_approved_at'      => null,
            'nayok_id'               => null,
            'nayok_signature'        => null,
            'nayok_comment'          => null,
            'nayok_approved_at'      => null,
        ];

        if ($document->doc_type === 'incoming') {
            $request->validate([
                'receive_number'    => 'required|string|max:255',
                'receive_date'      => 'required|date',
                'doc_number'        => 'required|string|max:255',
                'doc_date'          => 'required|date',
                'title'             => 'required|string|max:255',
                'doc_from'          => 'required|string|max:255',
                'doc_type_category' => 'required|string|max:255',
                'file'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 
            ]);

            $updateData = array_merge([
                'receive_number'    => $request->receive_number,
                'receive_date'      => $request->receive_date,
                'doc_number'        => $request->doc_number,
                'doc_date'          => $request->doc_date,
                'title'             => $request->title,
                'doc_from'          => $request->doc_from,
                'doc_type_category' => $request->doc_type_category,
                'doc_speed'         => $request->doc_speed,
                'doc_secret'        => $request->doc_secret,
            ], $resetApprovals);

            if ($request->hasFile('file')) {
                app(DocumentFileStorage::class)->delete($document->attachment_path);
                $updateData['attachment_path'] = app(DocumentFileStorage::class)->store($request->file('file'), 'incoming_docs');
                $updateData['content'] = 'อ้างอิงจากไฟล์แนบในระบบ';
            }

            $document->update($updateData);

        } elseif ($document->doc_type === 'outgoing') {
            $request->validate([
                'doc_number'        => 'required|string|max:255',
                'doc_date'          => 'required|date',
                'doc_type_category' => 'required|string|max:255',
                'title'             => 'required|string|max:255',
                'doc_to'            => 'required|string|max:255',
                'signer_name'       => 'required|string|max:255',
                'file'              => 'nullable|file|mimes:pdf|max:20480',
            ]);

            $updateData = array_merge([
                'doc_number'        => $request->doc_number,
                'doc_date'          => $request->doc_date,
                'doc_type_category' => $request->doc_type_category,
                'doc_speed'         => $request->doc_speed ?? 'ปกติ',
                'doc_secret'        => $request->doc_secret ?? 'ปกติ',
                'title'             => $request->title,
                'doc_to'            => $request->doc_to,
                'signer_name'       => $request->signer_name,
                'reference_doc'     => $request->reference_doc,
                'remark'            => $request->remark,
                'running_number'    => $request->running_number,
            ], $resetApprovals);

            if ($request->hasFile('file')) {
                app(DocumentFileStorage::class)->delete($document->attachment_path);
                $updateData['attachment_path'] = app(DocumentFileStorage::class)->store($request->file('file'), 'outgoing_docs');
            }
            
            $updateData['content'] = $request->filled('attachment') 
                ? 'สิ่งที่ส่งมาด้วย: ' . $request->attachment 
                : $document->content;

            $document->update($updateData);

        } else {
            
            // 🌟 3. บันทึกข้อความภายใน (ทั้งแบบอัปโหลดและพิมพ์)
            $isUploadOnly = str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ');

            // 🌟 เพิ่ม doc_from และ doc_to ในส่วนการ validate
            $request->validate([
                'doc_from' => 'required|string|max:255',
                'title'    => 'required|string|max:255',
                'doc_to'   => 'required|string|max:255',
                'doc_date' => 'required|date',
                'content'  => $isUploadOnly ? 'nullable' : 'required|string',
                'file'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 
            ]);

            // 🌟 ตัด doc_number ออก เพื่อป้องกันการเซฟทับด้วยข้อความรอธุรการ
            $updateData = array_merge([
                'doc_from'   => $request->doc_from,
                'title'      => $request->title,
                'doc_to'     => $request->doc_to,
                'doc_date'   => $request->doc_date,
                'content'    => $isUploadOnly ? $document->content : trim(preg_replace('/\n{3,}/', "\n\n", $request->content)),
            ], $resetApprovals);

            if ($request->hasFile('file')) {
                app(DocumentFileStorage::class)->delete($document->attachment_path);
                $updateData['attachment_path'] = app(DocumentFileStorage::class)->store($request->file('file'), 'attachments');
            }

            $document->update($updateData);
        }

        $document->routes()->update([
            'status' => 'pending',
            'comment' => null,
            'actioned_at' => null,
        ]);

        return redirect()->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'แก้ไขเอกสารเรียบร้อยแล้ว กรุณาลงนามเพื่อส่งเรื่องใหม่อีกครั้ง');
    }

    // ========================================================================
    // --- โซนที่ 2: หนังสือรับเข้า ---
    // ========================================================================

    public function createIncoming()
    {
        return view('documents.create_incoming', ['users' => $this->routeUsers()]);
    }

    public function storeIncoming(Request $request, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            return $this->storeV2Document($request, $v2Writes, 'incoming', false);
        }
        $request->validate([
            'receive_number'    => 'required|string|max:255',
            'running_number'    => 'required|integer|min:1',
            'receive_date'      => 'required|date',
            'doc_number'        => 'required|string|max:255',
            'doc_date'          => 'required|date',
            'title'             => 'required|string|max:255',
            'doc_from'          => 'required|string|max:255',
            'doc_type_category' => 'required|string|max:255',
            'doc_speed'         => 'nullable|string|max:50',
            'doc_secret'        => 'nullable|string|max:50',
            'file'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'external_url'      => 'nullable|url',
            'routing_users'     => 'required|array|min:1',
            'routing_users.*'   => 'required|integer|distinct|exists:users,id|not_in:' . Auth::id(),
        ], [
            'receive_number.required' => 'กรุณากดรันเลขรับ หรือรอให้ระบบรันเลขให้อัตโนมัติ',
            'running_number.required' => 'ไม่พบเลขลำดับรับ กรุณากดรันเลขใหม่',
            'title.required'    => 'กรุณาระบุเรื่อง',
            'file.mimes'        => 'รองรับเฉพาะไฟล์ PDF, JPG, PNG เท่านั้น',
            'file.max'          => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
            'external_url.url'  => 'รูปแบบลิงก์จากการสแกนไม่ถูกต้อง',
        ]);

        if (!$request->hasFile('file') && !$request->filled('external_url')) {
            return back()
                ->withErrors(['file' => 'กรุณาแนบไฟล์สแกนหนังสือ หรือ สแกน QR Codeลิงก์เอกสาร'])
                ->withInput();
        }

        $document = new Document();
        $document->doc_type          = 'incoming';
        $document->title             = $request->title;
        $document->doc_number        = $request->doc_number;
        $document->doc_date          = $request->doc_date;
        $document->receive_number    = $request->receive_number;
        $document->receive_date      = $request->receive_date;
        $document->doc_from          = $request->doc_from;
        $document->doc_type_category = $request->doc_type_category;
        $document->doc_speed         = $request->doc_speed;
        $document->doc_secret        = $request->doc_secret;
        $document->running_number    = $request->running_number;
        $document->created_by        = Auth::id();
        $document->status            = 'DRAFT';
        $document->current_step      = 1;
        $document->external_url      = $request->filled('external_url')
            ? trim($request->external_url)
            : null;

        if ($request->hasFile('file')) {
            $path = app(DocumentFileStorage::class)->store($request->file('file'), 'incoming_docs');
            $document->attachment_path = $path;
            $document->content = 'อ้างอิงจากไฟล์แนบในระบบ';
        } else {
            $document->content = 'อ้างอิงจากเอกสารใน QR Code';
        }

        $document->save();
        $this->saveDocumentRoute($document, $request->input('routing_users', []));

        if ($document->external_url) {
            ArchiveExternalDocument::dispatch($document->id)->afterCommit();
        }

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกหนังสือรับเข้าเรียบร้อย กรุณาตรวจสอบข้อมูลและลงนาม');
    }

    // ========================================================================
    // --- โซนที่ 3: หนังสือส่งออก ---
    // ========================================================================

   public function createOutgoing()
   {
        $incomingDocs = config('edoc.v2.document_reads')
            ? \App\Models\V2\Document::with('type')->whereHas('type', fn ($types) => $types->where('code', 'INCOMING'))->latest()->limit(20)->get()
            : Document::where('doc_type', 'incoming')->orderBy('created_at', 'desc')->limit(20)->get();

        $signers = \App\Models\User::role(['executive', 'palad', 'deputy-palad'])
            ->orWhere(function($query) {
                $query->role('head') 
                      ->where('department', 'LIKE', '%สำนักงานปลัด%'); 
            })
            ->get();

        $users = $this->routeUsers();
        return view('documents.create_outgoing', compact('incomingDocs', 'signers', 'users'));
    }

    public function storeOutgoing(Request $request, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            return $this->storeV2Document($request, $v2Writes, 'outgoing', false);
        }
        $request->validate([
            'doc_number'        => 'required|string|max:255',
            'doc_date'          => 'required|date',
            'doc_type_category' => 'required|string|max:255',
            'title'             => 'required|string|max:255',
            'doc_to'            => 'required|string|max:255',
            'signer_name'       => 'required|string|max:255', 
            'file'              => 'required|file|mimes:pdf|max:20480',
            'running_number'    => 'nullable|integer',
            'routing_users'     => 'required|array|min:1',
            'routing_users.*'   => 'required|integer|distinct|exists:users,id|not_in:' . Auth::id(),
        ], [
            'title.required'       => 'กรุณาระบุเรื่อง',
            'doc_to.required'      => 'กรุณาระบุผู้รับ (เรียน)',
            'signer_name.required' => 'กรุณาเลือกผู้ลงนาม',
            'file.required'        => 'กรุณาแนบไฟล์หนังสือส่งออก (PDF)',
            'file.mimes'           => 'รองรับเฉพาะไฟล์ PDF เท่านั้น',
            'file.max'             => 'ขนาดไฟล์ต้องไม่เกิน 20MB',
        ]);

        $document = new Document();
        $document->doc_type          = 'outgoing';
        $document->doc_number        = $request->doc_number;
        $document->doc_date          = $request->doc_date;
        $document->doc_type_category = $request->doc_type_category;
        $document->doc_speed         = $request->doc_speed ?? 'ปกติ';
        $document->doc_secret        = $request->doc_secret ?? 'ปกติ';
        $document->title             = $request->title;
        $document->doc_to            = $request->doc_to;
        $document->signer_name       = $request->signer_name; 
        $document->reference_doc     = $request->reference_doc;
        $document->remark            = $request->remark;
        $document->running_number    = $request->running_number;
        $document->created_by        = Auth::id();
        $document->status            = 'DRAFT';
        $document->current_step      = 1;

        if ($request->hasFile('file')) {
            $path = app(DocumentFileStorage::class)->store($request->file('file'), 'outgoing_docs');
            $document->attachment_path = $path;
            
            $document->content = $request->filled('attachment') 
                ? 'สิ่งที่ส่งมาด้วย: ' . $request->attachment 
                : 'อ้างอิงจากไฟล์แนบในระบบ';
        }

        $document->save();
        $this->saveDocumentRoute($document, $request->input('routing_users', []));

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกร่างหนังสือส่งออกเรียบร้อย กรุณาตรวจสอบข้อมูลและลงนามเพื่อส่งเรื่อง');
    }

    // ========================================================================
    // --- โซนที่ 4: พิจารณาและอนุมัติ (รวมถึงประทับลายเซ็นอัตโนมัติ) ---
    // ========================================================================

    public function approveList(V2DocumentReadService $v2Documents)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (config('edoc.v2.document_reads')) {
            $documents = $v2Documents->approvalQueue($user);
            return view('documents.approve_list', compact('documents'));
        }

        if ($user->hasRole('super-admin')) {
            $documents = Document::with(['creator', 'routes'])->whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELED'])
                ->orderBy('created_at', 'desc')->get();
            return view('documents.approve_list', compact('documents'));
        }

        $query = Document::with(['creator', 'routes']);

        if ($user->hasRole('saraban')) {
            $query->orWhereIn('status', ['WAITING_ADMIN', 'WAITING_NUMBERING']);
        }

        if ($user->hasRole('head') && !empty($user->department)) {
            $dept = trim($user->department); 
            
            $query->orWhere(function ($q) use ($dept) {
                $q->where('status', 'WAITING_SUPERVISOR')
                  ->whereHas('creator', function($creatorQuery) use ($dept) {
                      $creatorQuery->where('department', 'LIKE', "%{$dept}%");
                  });
            });
        }

        if ($user->hasRole('palad') || $user->hasRole('deputy-palad')) {
            $query->orWhere('status', 'WAITING_PALAD');
        }

        if ($user->hasRole('executive')) {
            $query->orWhere('status', 'WAITING_NAYOK');
        }

        $query->orWhere(function ($q) use ($user) {
            $q->whereIn('status', ['DRAFT', 'REJECTED'])
              ->where('created_by', $user->id);
        });

        $query->orWhereHas('routes', function ($routeQuery) use ($user) {
            $routeQuery->where('user_id', $user->id)
                ->where('status', 'pending')
                ->whereColumn('document_routes.step_order', 'documents.current_step');
        });

        // งานที่หัวหน้าฝ่ายส่งต่อมาให้ผู้ใช้โดยตรง แสดงในกล่องงานเดียวกับ
        // เอกสารรอพิจารณา เพื่อไม่ให้ผู้รับต้องตามหาจากคนละเมนู
        $query->orWhere(function ($assignmentQuery) use ($user) {
            $assignmentQuery->where('assigned_user_id', $user->id)
                ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                ->whereIn('assignment_status', ['pending', 'delegated']);
        });

        if ($user->hasRole('head')) {
            $query->orWhere(function ($headAssignmentQuery) use ($user) {
                $headAssignmentQuery->where('assigned_to', $user->department)
                    ->whereNull('assigned_user_id')
                    ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                    ->where('assignment_status', 'pending');
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return view('documents.approve_list', compact('documents'));
    }

    public function reviewDocument(Request $request, $id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $request->validate(['pin' => 'required|digits:6']);
            $document = $v2Documents->find($id);
            $this->authorize('review', $document);
            $approved = (string) $request->input('is_approved') === '1';
            /** @var User $legacyActor */
            $legacyActor = Auth::user();
            $assignmentChoice = $this->validatedAssignmentChoice($request, $document, $legacyActor, $approved);
            $actor = $this->validatedV2Signer($request, 'pin-review-attempts:');
            if (! $approved) {
                $request->validate(['comment' => 'required|string|max:1000']);
            }
            if (! $legacyActor instanceof User) {
                abort(401);
            }
            $evidenceAction = $legacyActor->hasRole('executive') ? 'EXECUTIVE_APPROVED'
                : ($legacyActor->hasAnyRole(['palad', 'deputy-palad']) ? 'PALAD_APPROVED'
                    : ($legacyActor->hasRole('head') ? 'SUPERVISOR_APPROVED' : ($approved ? 'APPROVED' : 'REJECTED')));
            $reviewedDocument = $v2Writes->review($document, $actor, $approved, $request->input('comment'), $assignmentChoice, $evidenceAction);
            if ($approved) {
                $this->notifySarabanForV2Numbering($reviewedDocument);
                $this->notifyCurrentV2Reviewer($reviewedDocument);
                $this->notifyActivatedV2Assignment($reviewedDocument, $legacyActor);
            }
            return redirect()->route('documents.show', $document->uuid)
                ->with('success', $approved ? 'บันทึกผลอนุมัติใน V2 เรียบร้อยแล้ว' : 'ตีกลับเอกสารใน V2 เรียบร้อยแล้ว');
        }
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
        // 🌟 1. ดึงข้อมูลเอกสาร (เพิ่ม 'routes.user' เข้ามาด้วย)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok', 'routes.user'])
            ->whereIdentifier($id)->lockForUpdate()->firstOrFail();
            
        // 🌟 2. ประกาศตัวแปร $targetDocId ไว้บังคับกลับหน้าเดิม
        $targetDocId = $document->uuid ?? $document->id;
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $approved = (string) $request->input('is_approved') === '1';
        $assignmentChoice = $this->validatedAssignmentChoice($request, $document, $user, $approved);

        if ($document->routes->isNotEmpty()) {
            $this->authorize('review', $document);
        }

        $throttleKey = 'pin-review-attempts:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            // 🌟 เปลี่ยนจาก back() เป็น redirect กลับหน้าเดิม
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_pin', 'คุณกรอกรหัส PIN ผิดเกินกำหนด (5 ครั้ง) กรุณารออีก ' . $seconds . ' วินาที');
        }

        if (empty($user->signature) || empty($user->pin)) {
            // 🌟 เปลี่ยนจาก back() เป็น redirect กลับหน้าเดิม
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_signature', 'กรุณาอัปโหลดลายเซ็นและตั้งรหัส PIN ก่อนดำเนินการครับ');
        }

        if (!Hash::check($request->pin, $user->pin)) {
            RateLimiter::hit($throttleKey, 60);
            // 🌟 เปลี่ยนจาก back() เป็น redirect กลับหน้าเดิม
            return redirect()->route('documents.show', $targetDocId)
                ->with('error_pin', 'รหัสผ่าน (PIN) ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        RateLimiter::clear($throttleKey);

        $signature = $user->signature;
        $comment = $request->input('comment');

        // 🌟 ดึงข้อมูลคิวพิจารณาปัจจุบัน (Dynamic Route)
        $currentRoute = $document->routes
            ->where('step_order', $document->current_step)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        $isLegacyReviewer =
            ($user->hasRole('saraban') && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) ||
            ($user->hasRole('head') && $document->status === 'WAITING_SUPERVISOR') ||
            ($user->hasAnyRole(['palad', 'deputy-palad']) && $document->status === 'WAITING_PALAD') ||
            ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK');

        if (!$currentRoute && !$isLegacyReviewer) {
            return redirect()->route('documents.show', $targetDocId)
                ->with('error', 'เอกสารฉบับนี้ยังไม่ถึงลำดับพิจารณาของคุณ');
        }

        // ==========================================
        // 🟢 กรณีที่ 1: อนุมัติ / เห็นชอบ (Approve)
        // ==========================================
        if ($request->is_approved == '1') {
            
            $msg = ''; // ตัวแปรเก็บข้อความสำเร็จ

            // อัปเดตข้อมูลลายเซ็นลงตาราง
            if ($user->hasRole('executive')) {
                $document->nayok_id = $user->id; $document->nayok_signature = $signature; $document->nayok_approved_at = now(); $document->nayok_comment = $comment;
            } elseif ($user->hasRole('palad') || $user->hasRole('deputy-palad')) {
                $document->palad_id = $user->id; $document->palad_signature = $signature; $document->palad_approved_at = now(); $document->palad_comment = $comment;
            } elseif ($user->hasRole('head')) {
                $document->supervisor_id = $user->id; $document->supervisor_signature = $signature; $document->supervisor_approved_at = now(); $document->supervisor_comment = $comment;
            }

            // 🌟 เช็กว่ามีคิวพิจารณาไหม
            if ($currentRoute) {
                // อัปเดตสถานะคิวคนนี้ว่า "ผ่านแล้ว"
                $currentRoute->update([
                    'status' => 'approved',
                    'comment' => $comment,
                    'actioned_at' => now(),
                ]);
                
                // เช็กว่ามีคนรอคิวถัดไปไหม
                $nextStep = $document->current_step + 1;
                $hasNextRoute = \App\Models\DocumentRoute::where('document_id', $document->id)
                    ->where('step_order', $nextStep)
                    ->exists();

                if ($hasNextRoute) {
                    $hasRouteAfterNext = \App\Models\DocumentRoute::where('document_id', $document->id)
                        ->where('step_order', '>', $nextStep)
                        ->exists();
                    $nextStepData = [
                        'current_step' => $nextStep,
                        'status' => $hasRouteAfterNext ? 'PROCESSING' : 'WAITING_APPROVER',
                    ];
                    if ($document->doc_type === 'incoming' && $assignmentChoice !== null) {
                        $nextStepData['assigned_to'] = $this->assignedUnitFromChoice($assignmentChoice);
                        $nextStepData['assignment_status'] = null;
                        $nextStepData['assigned_at'] = null;
                        $nextStepData['assigned_user_id'] = null;
                        $nextStepData['delegated_by'] = null;
                    }
                    $document->update($nextStepData);
                    $msg = 'อนุมัติสำเร็จ ระบบได้ส่งเรื่องให้ผู้พิจารณาลำดับถัดไปเรียบร้อยแล้ว';
                } else {
                    $finalData = [
                        'status' => $document->doc_type === 'internal' ? 'WAITING_NUMBERING' : 'APPROVED',
                    ];
                    if ($document->doc_type === 'incoming') {
                        $assignedTo = $assignmentChoice !== null
                            ? $this->assignedUnitFromChoice($assignmentChoice)
                            : $document->assigned_to;
                        $finalData['assigned_to'] = $assignedTo;
                        $finalData['assignment_status'] = $assignedTo ? 'pending' : null;
                        $finalData['assigned_at'] = $assignedTo ? now() : null;
                        $finalData['assigned_user_id'] = null;
                        $finalData['delegated_by'] = null;
                    }
                    $document->update($finalData);
                    if ($document->doc_type === 'outgoing') $this->autoStampPdf($document, $user);
                    if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) $this->autoAppendSignaturePage($document);
                    $msg = $document->doc_type === 'internal'
                        ? 'เห็นชอบเรียบร้อยแล้ว ระบบส่งเอกสารให้ธุรการลงเลขต่อไป'
                        : 'อนุมัติสมบูรณ์! เอกสารฉบับนี้ผ่านการพิจารณาครบทุกขั้นตอนแล้ว';
                }
            } 
            else {
                // 🌟 ระบบสิทธิ์ Role ตามลำดับชั้นแบบเดิม (Legacy)
                if ($user->hasRole('saraban') && $document->status === 'WAITING_ADMIN') {
                    $document->update(['status' => 'WAITING_SUPERVISOR']);
                    $msg = 'ตรวจสอบและส่งต่อให้หัวหน้าส่วนราชการเรียบร้อย';
                
                } elseif ($user->hasRole('head') && $document->status === 'WAITING_SUPERVISOR') {
                    $isFinalSigner = ($document->doc_type === 'outgoing' && !str_contains($document->signer_name ?? '', 'ปลัด') && !str_contains($document->signer_name ?? '', 'นายก'));
                    $nextStatus = $isFinalSigner ? 'APPROVED' : 'WAITING_PALAD';

                    $document->update([
                        'status'                 => $nextStatus,
                        'supervisor_id'          => $user->id,
                        'supervisor_signature'   => $signature,
                        'supervisor_approved_at' => now(),
                        'supervisor_comment'     => $comment,
                    ]);
                    
                    if ($isFinalSigner) $this->autoStampPdf($document, $user);
                    if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) $this->autoAppendSignaturePage($document);

                    $msg = $isFinalSigner ? 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)' : 'หัวหน้าส่วนราชการพิจารณาเห็นชอบและส่งเรื่องต่อให้ ปลัด อบต. แล้ว';

                } elseif (($user->hasRole('palad') || $user->hasRole('deputy-palad')) && $document->status === 'WAITING_PALAD') {
                    $assignedTo = $assignmentChoice !== null
                        ? $this->assignedUnitFromChoice($assignmentChoice)
                        : $document->assigned_to;
                    $isFinalSigner = ($document->doc_type === 'outgoing' && str_contains($document->signer_name ?? '', 'ปลัด'));
                    $nextStatus = $isFinalSigner ? 'APPROVED' : 'WAITING_NAYOK';

                    $paladData = [
                        'status'           => $nextStatus,
                        'palad_id'         => $user->id,
                        'palad_signature'  => $signature,
                        'palad_approved_at'=> now(),
                        'palad_comment'    => $comment, 
                        'assigned_to'      => $assignedTo, 
                    ];
                    if ($document->doc_type === 'incoming') {
                        $paladData['assignment_status'] = null;
                        $paladData['assigned_at'] = null;
                        $paladData['assigned_user_id'] = null;
                        $paladData['delegated_by'] = null;
                    }
                    $document->update($paladData);
                    
                    if ($isFinalSigner) $this->autoStampPdf($document, $user);
                    if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) $this->autoAppendSignaturePage($document);

                    $msg = $isFinalSigner ? 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)' : 'พิจารณาเห็นชอบส่งต่อให้นายกฯ เรียบร้อย';
                
                } elseif ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK') {
                    if ($document->doc_type === 'incoming') {
                        $newStatus = 'APPROVED'; $successMsg = 'ลงนามสั่งการหนังสือรับเข้าเรียบร้อยแล้ว';
                    } elseif ($document->doc_type === 'outgoing') {
                        $newStatus = 'APPROVED'; $successMsg = 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)';
                    } else {
                        $newStatus = 'WAITING_NUMBERING'; $successMsg = 'ลงนามอนุมัติและส่งให้ธุรการลงทะเบียนเลขเรียบร้อยแล้ว';
                    }

                    $assignedTo = $assignmentChoice !== null
                        ? $this->assignedUnitFromChoice($assignmentChoice)
                        : $document->assigned_to;

                    $executiveData = [
                        'status'           => $newStatus,
                        'nayok_id'         => $user->id,
                        'nayok_signature'  => $signature,
                        'nayok_approved_at'=> now(),
                        'nayok_comment'    => $comment, 
                        'assigned_to'      => $assignedTo, 
                    ];
                    if ($document->doc_type === 'incoming') {
                        $executiveData['assignment_status'] = $assignedTo ? 'pending' : null;
                        $executiveData['assigned_at'] = $assignedTo ? now() : null;
                        $executiveData['assigned_user_id'] = null;
                        $executiveData['delegated_by'] = null;
                    }
                    $document->update($executiveData);
                    
                    if ($document->doc_type === 'outgoing') $this->autoStampPdf($document, $user);
                    if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) $this->autoAppendSignaturePage($document);

                    $msg = $successMsg;

                } elseif ($user->hasRole('saraban') && $document->status === 'WAITING_NUMBERING') {
                    $finalDocNumber = trim($request->doc_number);
                    
                    if ($document->doc_type === 'outgoing') {
                        if (str_contains($document->signer_name ?? '', 'นายก')) {
                            $nextStatus = 'WAITING_NAYOK';
                        } elseif (str_contains($document->signer_name ?? '', 'ปลัด')) {
                            $nextStatus = 'WAITING_PALAD';
                        } else {
                            $nextStatus = 'WAITING_SUPERVISOR';
                        }
                        
                        $document->update([
                            'status'         => $nextStatus,
                            'doc_number'     => $finalDocNumber,
                            'running_number' => $request->running_number,
                            'doc_date'       => now()->toDateString()
                        ]);
                        $msg = 'ออกเลขและส่งต่อให้ผู้ลงนามพิจารณาเรียบร้อยแล้ว';
                    } 
                    else {
                        $document->update([
                            'status'         => 'APPROVED',
                            'doc_number'     => $finalDocNumber,
                            'running_number' => $request->running_number,
                            'doc_date'       => now()->toDateString()
                        ]);
                        $msg = 'ออกเลขและจัดเก็บเอกสารเรียบร้อยแล้ว: ' . $finalDocNumber;
                    }
                } else {
                    return redirect()->route('documents.show', $targetDocId)
                        ->with('error', 'คุณไม่มีสิทธิ์ดำเนินการในขั้นตอนนี้');
                }
            }

            // แจ้งเตือน LINE
            if (!in_array($document->status, ['APPROVED', 'CANCELED'])) {
                $lineMsg = "🔔 มีเอกสารส่งต่อถึงคุณ!\nเรื่อง: " . $document->title . "\n👇 เปิดดูเอกสารที่นี่:\n" . route('documents.show', $targetDocId);
                $this->sendLineMessage($lineMsg, $document);
            } elseif ($document->status === 'APPROVED') {
                $docUrl = route('documents.show', $targetDocId);
                app(NotificationDispatcher::class)->toUser(
                    $document->creator,
                    "✅ เอกสารของคุณดำเนินการเสร็จสิ้นแล้ว\nเรื่อง: {$document->title}\nเลขที่: " . ($document->doc_number ?: '-') . "\n\nเปิดดูเอกสาร:\n{$docUrl}"
                );

                if (!empty($document->assigned_to)) {
                    app(AssignmentNotificationService::class)->toUnitHeads(
                        $document->assigned_to,
                        $document->title,
                        $user->name,
                        $document->doc_number,
                        $this->documentNotificationUrl($targetDocId)
                    );
                }
            }

            // 🌟 3. บังคับโหลดกลับหน้าเอกสารใบเดิม! (แก้ไขจากของเดิมที่ไป approve_list)
            return redirect()->route('documents.show', $targetDocId)->with('success', $msg);

        } 
        // ==========================================
        // 🔴 กรณีที่ 2: ตีกลับ / ไม่อนุมัติ (Reject)
        // ==========================================
        else {
            $request->validate(['comment' => 'required|string|max:1000'], [
                'comment.required' => 'กรุณาระบุความเห็นหรือเหตุผลการตีกลับเอกสารในช่องความเห็น'
            ]);

            // ล้างสถานะคิวที่ค้างอยู่ (ถ้ามี)
            if ($currentRoute) {
                \App\Models\DocumentRoute::where('document_id', $document->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'comment' => $comment,
                        'actioned_at' => now(),
                    ]);
            }

            if (!empty($document->doc_number)) {
                $newStatus = 'CANCELED';
                $msg = 'ไม่อนุมัติ และยกเลิกเอกสารฉบับนี้ (บันทึกเป็นเลขเสียเรียบร้อย)';
            } else {
                $newStatus = 'REJECTED';
                $msg = ($user->hasRole('saraban') && $document->status === 'WAITING_NUMBERING')
                    ? 'ส่งคืนเรื่องให้ผู้บริหาร/เจ้าของเรื่องแก้ไขเรียบร้อยแล้ว'
                    : 'ทำการตีกลับเอกสารกลับไปยังผู้เสนอเรื่องเรียบร้อยแล้ว';
            }

            $document->update([
                'status'                 => $newStatus,
                'reject_reason'          => $comment,
                'supervisor_id'          => null, 'supervisor_signature'   => null, 'supervisor_comment'     => null, 'supervisor_approved_at' => null,
                'palad_id'               => null, 'palad_signature'        => null, 'palad_comment'          => null, 'palad_approved_at'      => null,
                'nayok_id'               => null, 'nayok_signature'        => null, 'nayok_comment'          => null, 'nayok_approved_at'      => null,
            ]);

            $docUrl = route('documents.show', $targetDocId);
            $lineMsg = "⚠️ เอกสารของคุณถูกตีกลับ/ตีตก!\nเรื่อง: " . $document->title . "\nเหตุผล: " . $comment . "\n👇 ดูรายละเอียด:\n" . $docUrl;
            app(NotificationDispatcher::class)->toUser($document->creator, $lineMsg);

            // 🌟 4. บังคับโหลดกลับหน้าเอกสารใบเดิม! (แก้ไขจากของเดิมที่ไป approve_list)
            return redirect()->route('documents.show', $targetDocId)->with('success', $msg);
        }
        }, 3);
    }

    private function autoStampPdf($doc, $user)
    {
        if (!$doc->attachment_path || !app(DocumentFileStorage::class)->exists($doc->attachment_path)) return false;
        if (!$user->signature) return false;

        $originalPdf = app(DocumentFileStorage::class)->path($doc->attachment_path);
        $newFileName = 'signed_' . time() . '_' . basename($doc->attachment_path);
        $signedStoragePath = 'documents/signed/' . $newFileName;
        $outputPdf = Storage::disk('documents')->path($signedStoragePath);
        $tempSigPath = storage_path('app/temp/sig_' . $user->id . '_' . time() . '.jpg');

        if (!File::exists(dirname($tempSigPath))) File::makeDirectory(dirname($tempSigPath), 0755, true);
        if (!File::exists(dirname($outputPdf))) File::makeDirectory(dirname($outputPdf), 0755, true);

        // แปลง Base64 เป็นรูป
        $base64Image = $user->signature;
        @list($type, $fileData) = explode(';', $base64Image);
        @list(, $fileData)      = explode(',', $fileData);
        
        $imageResource = imagecreatefromstring(base64_decode($fileData));
        $width = imagesx($imageResource);
        $height = imagesy($imageResource);
        $whiteBgImage = imagecreatetruecolor($width, $height);
        $whiteColor = imagecolorallocate($whiteBgImage, 255, 255, 255);
        imagefilledrectangle($whiteBgImage, 0, 0, $width, $height, $whiteColor);
        imagecopy($whiteBgImage, $imageResource, 0, 0, 0, 0, $width, $height);
        imagejpeg($whiteBgImage, $tempSigPath, 100);
        imagedestroy($imageResource);
        imagedestroy($whiteBgImage);

        // เรียกใช้ Python
        $scriptPath = base_path('scripts/stamper.py');
        $pythonCommand = config('services.docling.python', 'python');
        
        $process = new Process([$pythonCommand, $scriptPath, $originalPdf, $tempSigPath, $outputPdf]);
        
        try {
            $process->mustRun();
            $output = json_decode($process->getOutput(), true);

            if ($output && isset($output['status']) && $output['status'] === 'success') {
                $doc->signed_path = $signedStoragePath; 
                $doc->save();
                File::delete($tempSigPath);
                return true; 
            }
        } catch (\Exception $e) {
            // ปล่อยผ่านไป ไม่ต้องแจ้ง Error หน้าเว็บ
        }
        
        File::delete($tempSigPath);
        return false;
    }

    private function autoStampCreator($doc, $user)
    {
        if (!$doc->attachment_path || !app(DocumentFileStorage::class)->exists($doc->attachment_path)) return false;
        if (!$user->signature) return false;

        $originalPdf = app(DocumentFileStorage::class)->path($doc->attachment_path);
        
        $stampedStoragePath = 'documents/stamped/stamped_' . $doc->id . '.pdf';
        $outputPdf = Storage::disk('documents')->path($stampedStoragePath);
        $tempSigPath = storage_path('app/temp/sig_creator_' . $user->id . '_' . time() . '.jpg');

        if (!File::exists(dirname($tempSigPath))) File::makeDirectory(dirname($tempSigPath), 0755, true);
        if (!File::exists(dirname($outputPdf))) File::makeDirectory(dirname($outputPdf), 0755, true);

        $base64Image = $user->signature;
        @list($type, $fileData) = explode(';', $base64Image);
        @list(, $fileData)      = explode(',', $fileData);
        
        $imageResource = imagecreatefromstring(base64_decode($fileData));
        $width = imagesx($imageResource);
        $height = imagesy($imageResource);
        $whiteBgImage = imagecreatetruecolor($width, $height);
        $whiteColor = imagecolorallocate($whiteBgImage, 255, 255, 255);
        imagefilledrectangle($whiteBgImage, 0, 0, $width, $height, $whiteColor);
        imagecopy($whiteBgImage, $imageResource, 0, 0, 0, 0, $width, $height);
        imagejpeg($whiteBgImage, $tempSigPath, 100);
        imagedestroy($imageResource);
        imagedestroy($whiteBgImage);

        // --- เรียกใช้ Python ที่อัปเกรดความฉลาดแล้ว ---
        $scriptPath = base_path('scripts/stamper.py');
        $pythonCommand = config('services.docling.python', 'python');
        
        $process = new \Symfony\Component\Process\Process([$pythonCommand, $scriptPath, $originalPdf, $tempSigPath, $outputPdf]);
        
        try {
            $process->mustRun();
            $output = json_decode($process->getOutput(), true);
            if ($output && isset($output['status']) && $output['status'] === 'success') {
                File::delete($tempSigPath);
                return true; 
            }
        } catch (\Exception $e) {}
        
        File::delete($tempSigPath);
        return false;
    }

    private function autoAppendSignaturePage($doc)
    {
        if (!$doc->attachment_path || !app(DocumentFileStorage::class)->exists($doc->attachment_path)) return false;

        $stampedPath = 'documents/stamped/stamped_' . $doc->id . '.pdf';
        if (app(DocumentFileStorage::class)->exists($stampedPath)) {
            $originalPdf = app(DocumentFileStorage::class)->path($stampedPath);
        } else {
            $originalPdf = app(DocumentFileStorage::class)->path($doc->attachment_path);
        }

        $newFileName = 'appended_' . time() . '_' . basename($doc->attachment_path);
        $signedStoragePath = 'documents/signed/' . $newFileName;
        $outputPdf = Storage::disk('documents')->path($signedStoragePath);

        if (!File::exists(dirname($outputPdf))) File::makeDirectory(dirname($outputPdf), 0755, true);

        try {
            $pdf = Pdf::loadView('documents.pdf_approval', ['document' => $doc]);
            $pdf->setOption(['isRemoteEnabled' => true]); 
            $tempApprovalPath = storage_path('app/temp/temp_approval_' . $doc->id . '_' . time() . '.pdf');
            
            if (!File::exists(dirname($tempApprovalPath))) File::makeDirectory(dirname($tempApprovalPath), 0755, true);
            $pdf->save($tempApprovalPath);

            $fpdi = new Fpdi();
            
            $pageCount = $fpdi->setSourceFile($originalPdf);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tpl = $fpdi->importPage($i);
                $size = $fpdi->getTemplateSize($tpl);
                $fpdi->AddPage($size['orientation'], $size);
                $fpdi->useTemplate($tpl);
            }

            $fpdi->setSourceFile($tempApprovalPath);
            $tpl = $fpdi->importPage(1);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], $size);
            $fpdi->useTemplate($tpl);

            $fpdi->Output('F', $outputPdf);
            File::delete($tempApprovalPath);

            if ($doc->signed_path && $doc->signed_path !== $signedStoragePath) {
                app(DocumentFileStorage::class)->delete($doc->signed_path);
            }

            $doc->signed_path = $signedStoragePath;
            $doc->save();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ========================================================================
    // --- โซนที่ 5: จัดการข้อมูล (อัปโหลดไฟล์ ลบ และสร้าง PDF) ---
    // ========================================================================

    public function uploadAttachment(Request $request, $id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'attachment_context' => 'nullable|in:external_qr',
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ที่ต้องการอัปโหลด',
            'file.mimes' => 'รองรับเฉพาะไฟล์ PDF, JPG, PNG เท่านั้น',
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
        ]);

        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            if ($request->input('attachment_context') === 'external_qr') {
                $v2Writes->attachExternalQrCopy($document, $request->file('file'), Auth::id());
                return back()->with('success', 'แนบสำเนาเอกสารจาก QR เรียบร้อยแล้ว ขณะนี้สามารถตรวจสอบและส่งเรื่องต่อได้');
            }
            $v2Writes->replaceAttachment($document, $request->file('file'), Auth::id());
            return back()->with('success', 'อัปโหลดไฟล์แนบเข้า V2 เรียบร้อยแล้ว');
        }

        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();

        if ($document->created_by !== Auth::id() || !in_array($document->status, ['DRAFT', 'REJECTED'])) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แนบไฟล์ในขั้นตอนนี้');
        }

        if ($request->input('attachment_context') === 'external_qr') {
            if ($document->external_attachment_path) {
                app(DocumentFileStorage::class)->delete($document->external_attachment_path);
            }
            $file = $request->file('file');
            $path = app(DocumentFileStorage::class)->store($file, 'incoming_qr_docs');
            $document->external_attachment_path = $path;
            $document->external_original_name = $file->getClientOriginalName();
            $document->external_mime_type = $file->getMimeType();
            $document->external_file_size = $file->getSize();
            $document->external_sha256 = hash_file('sha256', $file->getRealPath());
            $document->external_downloaded_at = now();
            $document->external_download_error = null;
            $document->save();
            return back()->with('success', 'แนบสำเนาเอกสารจาก QR เรียบร้อยแล้ว ขณะนี้สามารถตรวจสอบและส่งเรื่องต่อได้');
        }

        if ($document->attachment_path) {
            app(DocumentFileStorage::class)->delete($document->attachment_path);
        }

        $path = app(DocumentFileStorage::class)->store($request->file('file'), 'attachments');
        $document->attachment_path = $path;
        $document->save();

        return back()->with('success', 'อัปโหลดไฟล์แนบเรียบร้อยแล้ว');
    }

    public function stampSignatureToPdf(Request $request, $id, V2DocumentReadService $v2Documents)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('view', $document);
            return back()->with('success', 'V2 เก็บลายเซ็นเป็นหลักฐาน workflow แบบตรวจสอบ hash ได้แล้ว');
        }

        $document = Document::whereIdentifier($id)->firstOrFail();
        $this->authorize('update', $document);
        return $this->autoStampCreator($document, Auth::user())
            ? back()->with('success', 'ประทับลายเซ็นใน PDF เรียบร้อยแล้ว')
            : back()->with('error', 'ไม่สามารถประทับลายเซ็นใน PDF ได้');
    }

    public function destroy($id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('delete', $document);
            $v2Writes->delete($document, Auth::id());
            return redirect()->route('documents.approve_list')->with('success', 'ลบเอกสาร V2 เรียบร้อยแล้ว');
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        $this->authorize('delete', $document);

        if ($document->attachment_path) {
            app(DocumentFileStorage::class)->delete($document->attachment_path);
        }
        if ($document->external_attachment_path) {
            app(DocumentFileStorage::class)->delete($document->external_attachment_path);
        }
        $document->delete();

        return redirect()
            ->route('documents.approve_list')
            ->with('success', 'ลบเอกสารเรียบร้อยแล้ว');
    }

    public function downloadSignedPdf($id, V2DocumentReadService $v2Documents)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('accessConfidential', $document);
            if (in_array($document->status, ['DRAFT', 'REJECTED', 'CANCELED'], true) || ! $document->attachment_path) {
                return back()->with('error', 'เอกสารยังไม่พร้อมดาวน์โหลด');
            }
            return app(DocumentFileStorage::class)->response(
                (string) ($document->signed_path ?: $document->attachment_path),
                null,
                ['Content-Disposition' => 'attachment']
            );
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        $this->authorize('accessConfidential', $document);
        app(AuditLogger::class)->log('document.downloaded', $document, [], [
            'signed' => true,
        ]);

        if (in_array($document->status, ['DRAFT', 'REJECTED', 'CANCELED'])) {
            return back()->with('error', 'ไม่สามารถดาวน์โหลดเอกสารที่ถูกยกเลิก หรือยังไม่สิ้นสุดกระบวนการได้');
        }

        if (!$document->attachment_path) {
            return back()->with('error', 'ไม่พบไฟล์แนบต้นฉบับ');
        }

        $pdf = Pdf::loadView('documents.pdf_approval', compact('document'));
        $pdf->setOption(['isRemoteEnabled' => true]); 
        
        $tempApprovalPath = storage_path('app/temp/temp_approval_' . $document->id . '.pdf');
        File::ensureDirectoryExists(dirname($tempApprovalPath));
        $pdf->save($tempApprovalPath);

        $fpdi = new Fpdi();

        $originalPath = app(DocumentFileStorage::class)->path($document->attachment_path);
        $pageCount = $fpdi->setSourceFile($originalPath);
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], $size);
            $fpdi->useTemplate($tpl);
        }

        $fpdi->setSourceFile($tempApprovalPath);
        $tpl = $fpdi->importPage(1);
        $size = $fpdi->getTemplateSize($tpl);
        $fpdi->AddPage($size['orientation'], $size);
        $fpdi->useTemplate($tpl);

        unlink($tempApprovalPath);

        $fileName = 'Signed_' . ($document->doc_number ?? 'Draft') . '.pdf';
        $fpdi->Output('I', $fileName); 
    }

    public function file(string|int $id, string $kind, V2DocumentReadService $v2Documents)
    {
        $document = config('edoc.v2.document_reads')
            ? $v2Documents->find($id)
            : Document::whereIdentifier($id)->firstOrFail();
        $this->authorize('accessConfidential', $document);

        $isConfidential = $document instanceof \App\Models\V2\Document
            ? ($document->confidentiality?->level_no ?? 0) > 1
            : in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'], true);
        if ($isConfidential) {
            $unlockedAt = session('secret_unlocked_'.$document->id);
            abort_unless($unlockedAt && (now()->timestamp - $unlockedAt) <= 180, 403, 'กรุณายืนยัน PIN เพื่อเปิดเอกสารลับ');
        }

        $path = match ($kind) {
            'main' => $document->attachment_path,
            'signed' => $document->signed_path,
            'external' => $document->external_attachment_path,
        };
        abort_unless($path, 404);

        app(AuditLogger::class)->log('document.file_viewed', $document, [], ['kind' => $kind]);

        return app(DocumentFileStorage::class)->response(
            (string) $path,
            basename((string) $path),
            ['Content-Disposition' => 'inline']
        );
    }

    public function routingSlip($id, V2DocumentReadService $v2Documents)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('view', $document);
            abort_unless($document->doc_type === 'incoming', 422);
            return view('documents.routing_slip', compact('document'));
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        $this->authorize('view', $document);

        if ($document->doc_type !== 'incoming') {
            return back()->with('error', 'ใบเกษียนหนังสือใช้สำหรับหนังสือรับเข้าเท่านั้น');
        }

        return view('documents.routing_slip', compact('document'));
    }

    // ========================================================================
    // --- โซนที่ 6: สมุดคุมเลข และ API รันเลขอัตโนมัติ ---
    // ========================================================================

    public function assignedList(V2DocumentReadService $v2Documents)
    {
        /** @var User $user */
        $user = Auth::user();
        $leaveDelegations = LeaveRequest::with('user')
            ->assignedToDelegate($user->id)
            ->atWorkflowStage('pending_delegate')
            ->oldest('created_at')
            ->get();

        if (config('edoc.v2.document_reads')) {
            $documents = $v2Documents->assignedDocuments($user);
            $subordinates = $v2Documents->subordinates($user);
            return view('documents.assigned_list', compact('documents', 'subordinates', 'leaveDelegations'));
        }
        $userDepartment = $user->department;

        $departmentAliases = [
            'สำนักงานปลัด'                    => ['สำนักงานปลัด', 'สำนักปลัด'],
            'กองการศึกษา ศาสนา และวัฒนธรรม'  => ['กองการศึกษา ศาสนา และวัฒนธรรม', 'กองการศึกษาฯ', 'กองศึกษา'],
            'กองคลัง'                          => ['กองคลัง'],
            'กองช่าง'                          => ['กองช่าง'],
            'กองสาธารณสุขและสิ่งแวดล้อม'      => ['กองสาธารณสุขและสิ่งแวดล้อม', 'กองสาธารณสุข'],
        ];

        $aliases = $departmentAliases[$userDepartment] ?? [$userDepartment];

        $documents = Document::with(['assignee', 'delegator'])
            ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
            ->whereIn('assignment_status', ['pending', 'accepted', 'delegated', 'in_progress', 'completed'])
            ->where(function ($query) use ($aliases, $user) {
                $query->where('assigned_user_id', $user->id);

                if ($user->hasRole('head')) {
                    $query->orWhere(function ($departmentQuery) use ($aliases) {
                        $departmentQuery->whereIn('assigned_to', $aliases)
                            ->whereNull('assigned_user_id');
                    })->orWhere('delegated_by', $user->id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $subordinates = User::where('department', $userDepartment)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        return view('documents.assigned_list', compact('documents', 'subordinates', 'leaveDelegations'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบัน',
            'new_password.required'     => 'กรุณาระบุรหัสผ่านใหม่',
            'new_password.min'          => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร',
            'new_password.confirmed'    => 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง!');
        }

        if (Hash::check($request->new_password, $user->password)) {
            return back()->with('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านปัจจุบัน!');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'อัปเดตรหัสผ่านเข้าสู่ระบบเรียบร้อยแล้ว');
    }

    public function acknowledge($id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $v2Writes->assign($document, Auth::user(), 'accept');
            return back()->with('success', 'บันทึกการรับทราบใน V2 เรียบร้อยแล้ว');
        }
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->whereIdentifier($id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! in_array($document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
            || ! in_array($document->assignment_status, ['pending', 'accepted', 'delegated', 'in_progress', 'completed'], true)) {
            return back()->with('error', 'เอกสารยังไม่ผ่านการอนุมัติขั้นสุดท้าย');
        }

        if ($user->hasRole('head') && $document->assigned_to === $user->department) {
            $document->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $user->id,
            ]);
            return back()->with('success', 'บันทึกการรับทราบคำสั่งเรียบร้อยแล้ว ระบบได้เก็บประวัติเวลาไว้เป็นหลักฐาน');
        }

        return back()->with('error', 'คุณไม่มีสิทธิ์ดำเนินการในขั้นตอนนี้');
    }

    public function assignmentAction(Request $request, $id, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        $request->validate([
            'action' => 'required|in:accept,delegate',
            'delegate_user_id' => 'nullable|required_if:action,delegate|integer|exists:users,id',
        ]);

        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            /** @var User $actor */
            $actor = Auth::user();
            $delegateId = $request->integer('delegate_user_id') ?: null;
            $assignedDocument = $v2Writes->assign($document, $actor, $request->input('action'), $delegateId);
            if ($request->input('action') === 'delegate' && $delegateId) {
                app(AssignmentNotificationService::class)->toUser(
                    User::find($delegateId),
                    $assignedDocument->title,
                    $actor->name,
                    $assignedDocument->doc_number,
                    $this->documentNotificationUrl($assignedDocument->uuid)
                );
            }
            return back()->with('success', $request->input('action') === 'accept' ? 'รับเรื่องใน V2 เรียบร้อยแล้ว' : 'ส่งต่องานใน V2 เรียบร้อยแล้ว');
        }

        $document = Document::whereIdentifier($id)->firstOrFail();
        /** @var User $user */
        $user = Auth::user();

        if (! in_array($document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
            || ! in_array($document->assignment_status, ['pending', 'accepted', 'delegated', 'in_progress', 'completed'], true)) {
            return back()->with('error', 'เอกสารยังไม่ผ่านการอนุมัติขั้นสุดท้าย');
        }

        $isDepartmentHead = $user->hasRole('head')
            && $document->assigned_to === $user->department
            && $document->assigned_user_id === null;
        $isCurrentAssignee = $document->assigned_user_id === $user->id;

        if (!$isDepartmentHead && !$isCurrentAssignee) {
            return back()->with('error', 'คุณไม่ใช่ผู้รับมอบหมายปัจจุบันของเอกสารฉบับนี้');
        }

        if ($request->action === 'accept') {
            $document->update([
                'assigned_user_id' => $user->id,
                'assignment_status' => 'accepted',
                'assigned_at' => now(),
                'acknowledged_at' => now(),
                'acknowledged_by' => $user->id,
            ]);

            return back()->with('success', 'รับเรื่องไว้ดำเนินการเองเรียบร้อยแล้ว');
        }

        $delegate = User::whereKey($request->delegate_user_id)
            ->where('department', $user->department)
            ->where('id', '!=', $user->id)
            ->first();

        if (!$delegate) {
            return back()->with('error', 'เลือกส่งต่อได้เฉพาะบุคลากรในฝ่ายเดียวกันเท่านั้น');
        }

        $document->update([
            'assigned_user_id' => $delegate->id,
            'delegated_by' => $user->id,
            'assignment_status' => 'delegated',
            'assigned_at' => now(),
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ]);

        app(AssignmentNotificationService::class)->toUser(
            $delegate,
            $document->title,
            $user->name,
            $document->doc_number,
            $this->documentNotificationUrl($document->uuid ?? $document->id)
        );

        return back()->with('success', 'ส่งต่อเรื่องให้ ' . $delegate->name . ' เรียบร้อยแล้ว');
    }

    public function generateDraft(Request $request)
    {
        $request->validate(['topic' => 'required|string']);

        $apiKey = config('services.typhoon.api_key');
        
        if (!$apiKey) {
            return response()->json(['error' => 'ระบบติดต่อปัญญาประดิษฐ์ยังไม่พร้อมใช้งาน'], 500);
        }

        $systemPrompt = "คุณคือปลัดองค์การบริหารส่วนตำบล ผู้เชี่ยวชาญระเบียบงานสารบรรณ จงร่างบันทึกข้อความจากหัวข้อที่กำหนดให้ โดยเขียนในรูปแบบเอกสารราชการ ๓ ย่อหน้า (ภาคเหตุ, ภาคความประสงค์, ภาคสรุป) ใช้ภาษาทางการ สั้น กระชับ สละสลวย **คำสั่งสำคัญ:** 1. ให้ตอบมาเฉพาะเนื้อหาล้วนๆ ห้ามมีคำว่า 'เรื่อง', 'เรียน', 'จึงเรียนมาเพื่อโปรดทราบ' หรือคำอธิบายเพิ่มเติมใดๆ 2. ให้ขึ้นต้นแต่ละย่อหน้าด้วยการย่อหน้า (ไม่ต้องพิมพ์คำว่า ภาคเหตุ/ประสงค์/สรุป นำหน้า)";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60) 
                ->post('https://api.opentyphoon.ai/v1/chat/completions', [
                    'model' => config('services.typhoon.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "หัวข้อที่ต้องการให้ร่าง: " . $request->topic]
                    ],
                    'temperature' => 0.4, 
                    'max_tokens' => 1500,
                ]);

            if ($response->status() === 429) {
                return response()->json(['error' => 'AI ยุ่งอยู่ขณะนี้ กรุณารอสักครู่แล้วลองใหม่อีกครั้งครับ'], 429);
            }

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $formattedContent = "\t\t" . str_replace("\n", "\n\t\t", trim($content));
                return response()->json(['draft' => $formattedContent]);
            }

            return response()->json(['error' => 'ระบบ AI ขัดข้องชั่วคราว'], 500);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'ไม่สามารถเชื่อมต่อระบบปัญญาประดิษฐ์ได้'], 500);
        }
    }

    // =========================================================================
    // 🌟 โซนฟังก์ชันสำหรับระบบ "เอกสารลับ" (ปลดล็อก และ ขอสิทธิ์)
    // =========================================================================

    // =========================================================================
    // 🌟 โซนฟังก์ชันสำหรับระบบแจ้งเตือนผ่าน LINE Messaging API (LINE Bot)
    // =========================================================================
    private function sendLineMessage($messageText, $document = null)
    {
        if (!$document) {
            return;
        }

        $line = app(LineMessagingService::class);
        $document->loadMissing(['creator', 'routes.user']);

        // Dynamic route has priority because it identifies the exact next reviewer.
        $currentRoute = $document->routes
            ->first(fn ($route) => $route->step_order == $document->current_step && $route->status === 'pending');
        if ($currentRoute?->user) {
            app(NotificationDispatcher::class)->toUser($currentRoute->user, $messageText);
            return;
        }

        $recipients = match ($document->status) {
            'WAITING_SUPERVISOR' => $line->usersWithRoles('head', $document->creator?->department),
            'WAITING_PALAD' => $line->usersWithRoles(['palad', 'deputy-palad']),
            'WAITING_NAYOK' => $line->usersWithRoles('executive'),
            'WAITING_NUMBERING', 'WAITING_ADMIN' => $line->usersWithRoles('saraban'),
            default => collect(),
        };

        app(NotificationDispatcher::class)->toUsers($recipients, $messageText);
    }

    private function notifySarabanForV2Numbering(\App\Models\V2\Document $document): void
    {
        $document->loadMissing('type');
        if ($document->doc_type !== 'internal' || $document->status !== 'APPROVED') {
            return;
        }

        $recipients = app(LineMessagingService::class)->usersWithRoles('saraban');
        $documentUrl = route('documents.show', $document->uuid);
        app(NotificationDispatcher::class)->toUsers(
            $recipients,
            "🔔 มีหนังสือบันทึกข้อความกำลังรอออกเลข\nเรื่อง: {$document->title}\n\nเปิดดูเอกสาร:\n{$documentUrl}"
        );
    }

    private function notifyCurrentV2Reviewer(\App\Models\V2\Document $document): void
    {
        $document->loadMissing(['workflow.steps', 'type']);
        if ($document->status !== 'IN_REVIEW' || ! $document->workflow?->current_step) {
            return;
        }

        $currentStep = $document->workflow->steps
            ->first(fn ($step) => $step->step_order === $document->workflow->current_step
                && strtoupper((string) ($step->status instanceof \BackedEnum ? $step->status->value : $step->status)) === 'PENDING');
        if (! $currentStep?->assigned_user_id) {
            return;
        }

        // V2 และระบบผู้ใช้เดิมใช้รหัสผู้ใช้ชุดเดียวกัน ส่งเฉพาะผู้รับคิวปัจจุบันเท่านั้น
        $recipient = User::find($currentStep->assigned_user_id);
        if (! $recipient) {
            return;
        }

        app(NotificationDispatcher::class)->toUser(
            $recipient,
            "🔔 เอกสารมาถึงคิวพิจารณาของคุณแล้ว\nเรื่อง: {$document->title}\n\nเปิดดูเอกสาร:\n" . route('documents.show', $document->uuid)
        );
    }

    private function notifyActivatedV2Assignment(\App\Models\V2\Document $document, User $actor): void
    {
        if ($document->status !== 'APPROVED') {
            return;
        }

        $assignment = $document->assignments()
            ->with('unit')
            ->where('status', 'PENDING')
            ->whereNotNull('assigned_at')
            ->latest('id')
            ->first();
        if (!$assignment) {
            return;
        }

        $notifications = app(AssignmentNotificationService::class);
        $url = $this->documentNotificationUrl($document->uuid);
        if ($assignment->assigned_user_id) {
            $notifications->toUser(
                User::find($assignment->assigned_user_id),
                $document->title,
                $actor->name,
                $document->doc_number,
                $url
            );
            return;
        }

        if ($assignment->unit?->name) {
            $notifications->toUnitHeads(
                $assignment->unit->name,
                $document->title,
                $actor->name,
                $document->doc_number,
                $url
            );
        }
    }

    private function documentNotificationUrl(string|int $identifier): string
    {
        return rtrim((string) config('app.url'), '/').route('documents.show', $identifier, false);
    }

    // =========================================================================
    // 🌟 โซน AI + OCR: ดึงข้อมูลจากไฟล์ PDF/รูปภาพ อัตโนมัติ (Auto-fill)
    // =========================================================================
    public function autoExtract(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('file');
        $taskId = (string) Str::uuid();
        $extension = strtolower($file->extension() ?: 'bin');
        $path = $file->storeAs('document_extractions', $taskId.'.'.$extension, 'local');

        if (! $path) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเตรียมไฟล์สำหรับประมวลผลได้',
            ], 500);
        }

        try {
            $task = DocumentExtractionTask::create([
                'id' => $taskId,
                'user_id' => $request->user()->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'queued',
            ]);

            ProcessDocumentExtraction::dispatch($task->id);

            return response()->json([
                'success' => true,
                'task_id' => $task->id,
                'status' => $task->status,
                'status_url' => route('documents.auto_extract_status', $task->id),
            ], 202);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            report($error);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเริ่มงานสกัดข้อมูลเอกสารได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function autoExtractStatus(Request $request, string $taskId)
    {
        $task = DocumentExtractionTask::findOrFail($taskId);

        abort_unless(
            $task->user_id === $request->user()->id || $request->user()->hasRole('super-admin'),
            403
        );

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'status' => $task->status,
            'data' => $task->status === 'completed' ? $task->result : null,
            'message' => $task->status === 'failed' ? $task->error_message : null,
        ]);
    }

    private function autoExtractLegacy(\Illuminate\Http\Request $request)
    {
        set_time_limit(600); // ขยายเวลาประมวลผลเป็น 10 นาที

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->storeAs('temp_extract', time() . '_' . $file->getClientOriginalName());
            
            // 🌟 1. ปรับสัญลักษณ์ Slash ให้เป็นสไตล์ Windows (\) ทั้งหมด ป้องกัน Path เพี้ยน
            $fullPath = str_replace('/', '\\', storage_path('app\\' . $path));
            $jsonOutputPath = $fullPath . '_result.json';
            $scriptPath = base_path('extract_doc.py');

            // 🌟 2. ตรวจสอบว่าไฟล์ Python สคริปต์วางถูกที่จริงๆ ไหม
            if (!file_exists($scriptPath)) {
                @unlink($fullPath);
                return response()->json([
                    'success' => false, 
                    'message' => "หาไฟล์สคริปต์ไม่เจอ! พาร์ทที่ระบบกำลังค้นหา: " . $scriptPath
                ], 500);
            }

            // ใช้ Process แยกแต่ละ argument ป้องกันปัญหาพาธ Windows และช่องว่างในชื่อไฟล์
            // ใช้ Python ตัวเดียวกับ stamper.py ตามรูปแบบเดิม
            $pythonCommand = config('services.document_extraction.python', 'python');
            // PHP/Apache บางแบบไม่ส่ง SystemRoot ให้ child process ทำให้ Python
            // โหลด asyncio/_overlapped ไม่ได้และเกิด WinError 10106
            $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
            // กำหนด home/cache ที่เขียนได้เสมอ เพราะ Apache บางแบบไม่มี USERPROFILE
            // ทำให้ pathlib.Path.home() ของ Python/OCR ล้มเหลว
            $pythonHome = storage_path('app/python_home');
            $pythonTemp = storage_path('app/temp_extract/python_tmp');
            $pythonAppData = $pythonHome . DIRECTORY_SEPARATOR . 'AppData';
            File::ensureDirectoryExists($pythonHome);
            File::ensureDirectoryExists($pythonTemp);
            File::ensureDirectoryExists($pythonAppData . DIRECTORY_SEPARATOR . 'Roaming');
            File::ensureDirectoryExists($pythonAppData . DIRECTORY_SEPARATOR . 'Local');
            // Symfony Process บน Windows ใช้ TEMP ของ PHP parent สำหรับไฟล์ pipe
            putenv('TEMP=' . $pythonTemp);
            putenv('TMP=' . $pythonTemp);
            $processEnvironment = [
                'SystemRoot' => $systemRoot,
                'SYSTEMROOT' => $systemRoot,
                'windir' => $systemRoot,
                'HOME' => $pythonHome,
                'USERPROFILE' => $pythonHome,
                'APPDATA' => $pythonAppData . DIRECTORY_SEPARATOR . 'Roaming',
                'LOCALAPPDATA' => $pythonAppData . DIRECTORY_SEPARATOR . 'Local',
                'TEMP' => $pythonTemp,
                'TMP' => $pythonTemp,
                'TORCH_COMPILE_DISABLE' => '1',
                'TORCHDYNAMO_DISABLE' => '1',
                'TORCH_CACHING_PRECOMPILE' => '0',
                'TOKENIZERS_PARALLELISM' => 'false',
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
                'PYTHONPATH' => false,
                'PYTHONHOME' => false,
                'VIRTUAL_ENV' => false,
            ];

            $runExtractor = function () use ($pythonCommand, $scriptPath, $fullPath, $jsonOutputPath, $processEnvironment) {
                $process = new Process([
                    $pythonCommand,
                    $scriptPath,
                    $fullPath,
                    $jsonOutputPath,
                ], base_path(), $processEnvironment);
                $process->setTimeout(600);
                $process->run();

                return trim($process->getOutput() . PHP_EOL . $process->getErrorOutput());
            };

            // OCR ใช้ทรัพยากรสูง จึงป้องกันหลาย request ประมวลผลพร้อมกัน
            $lockPath = storage_path('app/ocr-extract.lock');
            $lockHandle = fopen($lockPath, 'c');
            if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException('ไม่สามารถล็อกระบบประมวลผลเอกสารได้');
            }

            try {
                $terminalOutput = $runExtractor();

            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }

            // 🌟 4. ตรวจสอบไฟล์ผลลัพธ์ JSON
            if (!file_exists($jsonOutputPath)) {
                @unlink($fullPath);
                return response()->json([
                    'success' => false, 
                    'message' => "ระบบ Python ขัดข้อง!\nPython: {$pythonCommand}\nไฟล์สคริปต์: {$scriptPath}\nข้อความตอบกลับ: " . ($terminalOutput ?: 'ไม่มีข้อความตอบกลับจากระบบ')
                ], 500);
            }

            // อ่านผลลัพธ์จากไฟล์ JSON
            $jsonContent = file_get_contents($jsonOutputPath);
            $extractResult = json_decode($jsonContent, true);
            
            @unlink($fullPath);
            @unlink($jsonOutputPath);

            if (!is_array($extractResult)) {
                return response()->json(['success' => false, 'message' => 'รูปแบบข้อมูลจาก Python ไม่ถูกต้อง'], 500);
            }

            if (isset($extractResult['status']) && $extractResult['status'] === 'error') {
                $errMsg = is_array($extractResult['message']) ? json_encode($extractResult['message']) : ($extractResult['message'] ?? '');
                return response()->json([
                    'success' => false,
                    'message' => "Python Error ({$pythonCommand}): " . $errMsg,
                ], 500);
            }

            // ... (โค้ดก่อนหน้านี้เหมือนเดิม) ...
            $markdownText = is_array($extractResult['text']) ? json_encode($extractResult['text']) : ($extractResult['text'] ?? '');

            // ส่งข้อมูลต่อไปยัง OpenTyphoon AI
            $prompt = "คุณคือ AI ผู้ช่วยงานสารบรรณ หน้าที่ของคุณคือดึงข้อมูลจากเอกสารราชการต่อไปนี้ และตอบกลับมาเป็น JSON เท่านั้น (ไม่ต้องมีคำอธิบายอื่น) โดยมีคีย์ดังนี้:\n{\n    \"doc_number\": \"ที่/เลขที่เอกสาร (ถ้าไม่มีให้ใส่ว่าง)\",\n    \"doc_date\": \"วันที่ในเอกสาร (แปลงเป็นรูปแบบ YYYY-MM-DD ค.ศ. เช่น 2024-05-20)\",\n    \"title\": \"เรื่องของเอกสาร\",\n    \"doc_from\": \"จาก (ชื่อหน่วยงานต้นทาง หรือชื่อบุคคลที่ส่งมา)\"\n}\n\nข้อความจากเอกสาร:\n" . $markdownText;

            // ตรวจสอบว่ามีการตั้งค่า API Key หรือยัง
            $apiKey = config('services.typhoon.api_key');
            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'ยังไม่ได้ตั้งค่า TYPHOON_API_KEY ในไฟล์ .env ครับ'], 500);
            }

            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->post(config('services.typhoon.endpoint'), [
                    'model' => config('services.typhoon.model'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                $aiContent = $response->json('choices.0.message.content');
                if (is_array($aiContent)) $aiContent = json_encode($aiContent);
                
                $aiContent = preg_replace('/```json\s*(.*?)\s*```/s', '$1', (string)$aiContent);
                $extractedData = json_decode(trim($aiContent), true);

                if (!is_array($extractedData)) {
                    return response()->json(['success' => false, 'message' => "AI ตอบข้อความมาผิดรูปแบบ ไม่ใช่ JSON:\n" . $aiContent], 500);
                }

                return response()->json(['success' => true, 'data' => $extractedData]);
            }

            $statusCode = $response->status();
            report(new \RuntimeException('Typhoon API returned HTTP '.$statusCode));
            
            return response()->json([
                'success' => false, 
                'message' => 'ระบบ AI ไม่สามารถประมวลผลเอกสารได้ในขณะนี้'
            ], 500);

        } catch (\Exception $e) {
            if (isset($fullPath)) @unlink($fullPath);
            if (isset($jsonOutputPath)) @unlink($jsonOutputPath);
            
            report($e);
            return response()->json(['success' => false, 'message' => 'ไม่สามารถสกัดข้อมูลเอกสารได้ในขณะนี้'], 500);
        }
    
    }

    private function routeUsers()
    {
        return User::where('id', '!=', Auth::id())
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department']);
    }

    private function storeV2Document(Request $request, V2DocumentWriteService $writes, string $type, bool $uploadOnly)
    {
        $common = [
            'title' => 'required|string|max:255', 'doc_date' => 'required|date',
            'routing_users' => 'required|array|min:1',
            'routing_users.*' => 'required|integer|distinct|exists:users,id|not_in:'.Auth::id(),
            'doc_speed' => 'nullable|string|max:50', 'doc_secret' => 'nullable|string|max:50',
        ];
        $rules = match ($type) {
            'incoming' => $common + [
                'receive_number' => 'required|string|max:255', 'running_number' => 'required|integer|min:1',
                'receive_date' => 'required|date', 'doc_number' => 'required|string|max:255',
                'doc_from' => 'required|string|max:255', 'doc_type_category' => 'required|string|max:255',
                'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', 'external_url' => 'nullable|url',
            ],
            'outgoing' => $common + [
                'doc_number' => 'required|string|max:255', 'doc_type_category' => 'required|string|max:255',
                'doc_to' => 'required|string|max:255', 'signer_name' => 'required|string|max:255',
                'file' => 'required|file|mimes:pdf|max:20480', 'running_number' => 'nullable|integer|min:1',
            ],
            default => $common + ($uploadOnly ? [
                'doc_from' => 'required|string|max:255', 'doc_to' => 'required|string|max:255',
                'file' => 'required|file|mimes:pdf|max:5120',
            ] : ['content' => 'required|string']),
        };
        $data = $request->validate($rules);
        if ($type === 'incoming' && ! $request->hasFile('file') && ! $request->filled('external_url')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'กรุณาแนบไฟล์หรือระบุลิงก์เอกสาร']);
        }
        $data['doc_type'] = $type;
        if ($uploadOnly) { $data['content'] = 'อ้างอิงจากไฟล์แนบในระบบ'; }
        if ($type === 'incoming') {
            $data['content'] = $request->hasFile('file') ? 'อ้างอิงจากไฟล์แนบในระบบ' : 'อ้างอิงจากเอกสารใน QR Code';
        }
        if ($type === 'outgoing') {
            $data['content'] = $request->filled('attachment') ? 'สิ่งที่ส่งมาด้วย: '.$request->input('attachment') : 'อ้างอิงจากไฟล์แนบในระบบ';
        }
        if (isset($data['content'])) { $data['content'] = trim(preg_replace('/\n{3,}/', "\n\n", $data['content'])); }

        $document = $writes->create($data, $request->input('routing_users', []), Auth::id(), $request->file('file'));
        if ($type === 'incoming' && $request->filled('external_url')) {
            $externalFileId = $document->files()->where('file_type', 'EXTERNAL')->latest('version_no')->value('id');
            if ($externalFileId) {
                ArchiveV2ExternalDocument::dispatch($externalFileId)->afterCommit();
            }
        }
        return redirect()->route('documents.show', $document->uuid)->with('success', 'บันทึกร่างเอกสาร V2 และกำหนดเส้นทางเรียบร้อยแล้ว');
    }

    private function validateV2Update(Request $request, string $type, ?string $existingContent): array
    {
        $rules = [
            'title' => 'required|string|max:255', 'doc_date' => 'required|date',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'doc_speed' => 'nullable|string|max:50', 'doc_secret' => 'nullable|string|max:50',
        ];
        if ($type === 'incoming') {
            $rules += ['receive_number' => 'required|string|max:255', 'receive_date' => 'required|date',
                'doc_number' => 'required|string|max:255', 'doc_from' => 'required|string|max:255',
                'doc_type_category' => 'required|string|max:255'];
        } elseif ($type === 'outgoing') {
            $rules += ['doc_number' => 'required|string|max:255', 'doc_type_category' => 'required|string|max:255',
                'doc_to' => 'required|string|max:255', 'signer_name' => 'required|string|max:255'];
        } else {
            $rules += ['doc_from' => 'required|string|max:255', 'doc_to' => 'required|string|max:255', 'content' => 'nullable|string'];
        }
        $data = $request->validate($rules);
        if ($type === 'internal' && ! $request->filled('content')) { $data['content'] = $existingContent; }
        return $data;
    }

    private function validatedAssignmentChoice(Request $request, $document, User $user, bool $approved): ?string
    {
        $canDirectIncoming = $document->doc_type === 'incoming'
            && $user->hasAnyRole(['palad', 'deputy-palad', 'executive']);

        if (! $canDirectIncoming) {
            return null;
        }

        $allowedChoices = [
            '__NO_ASSIGNMENT__',
            'สำนักงานปลัด',
            'กองคลัง',
            'กองช่าง',
            'กองการศึกษา ศาสนา และวัฒนธรรม',
            'กองสาธารณสุขและสิ่งแวดล้อม',
            'กองสวัสดิการสังคม',
        ];
        $request->validate([
            'assignment_choice' => [
                Rule::requiredIf($approved),
                'nullable',
                'string',
                Rule::in($allowedChoices),
            ],
        ], [
            'assignment_choice.required' => 'กรุณาเลือกส่วนราชการที่ต้องการมอบหมาย หรือเลือกไม่มอบหมาย',
            'assignment_choice.in' => 'ตัวเลือกการมอบหมายไม่ถูกต้อง กรุณาเลือกใหม่อีกครั้ง',
        ]);

        return $approved ? $request->input('assignment_choice') : null;
    }

    private function assignedUnitFromChoice(?string $choice): ?string
    {
        return $choice === '__NO_ASSIGNMENT__' ? null : $choice;
    }

    private function validatedV2Signer(Request $request, string $keyPrefix): V2User
    {
        $request->validate(['pin' => 'required|digits:6']);
        /** @var User $legacy */
        $legacy = Auth::user();
        $key = $keyPrefix.$legacy->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pin' => 'กรอก PIN ผิดเกินกำหนด กรุณารอ '.RateLimiter::availableIn($key).' วินาที']);
        }
        $actor = V2User::with('signatures')->findOrFail($legacy->id);
        if (! $legacy->pin || ! Hash::check($request->input('pin'), $legacy->pin)) {
            RateLimiter::hit($key, 60);
            throw \Illuminate\Validation\ValidationException::withMessages(['pin' => 'รหัส PIN ไม่ถูกต้อง']);
        }
        if (! $actor->signatures->where('is_active', true)->whereNull('revoked_at')->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pin' => 'กรุณาอัปโหลดลายเซ็นก่อนดำเนินการ']);
        }
        RateLimiter::clear($key);
        return $actor;
    }

    private function saveDocumentRoute(Document $document, array $userIds): void
    {
        DocumentRoute::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'step_order' => 1,
            'status' => 'pending',
        ]);

        foreach (array_values($userIds) as $index => $userId) {
            DocumentRoute::create([
                'document_id' => $document->id,
                'user_id' => $userId,
                'step_order' => $index + 2,
                'status' => 'pending',
            ]);
        }
    }
}
