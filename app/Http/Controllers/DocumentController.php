<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter; 
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class DocumentController extends Controller
{
    // ========================================================================
    // --- โซนที่ 1: ฟังก์ชันพื้นฐาน (สำหรับทุกคน และธุรการ) ---
    // ========================================================================

    public function index()
    {
        return redirect()->route('home'); 
    }

    public function show($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 🌟 1. ดักตรวจสอบถ้าเป็น "เอกสารลับ"
        if (in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'])) {
            
            // สิทธิ์มาตรฐาน (เจ้าของเรื่อง หรือ ตำแหน่งผู้บริหาร/ธุรการ)
            $isAllowed = $user->id === $document->created_by || 
                         $user->hasAnyRole(['saraban', 'head', 'palad', 'deputy-palad', 'executive']);
            
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

            // 🌟 1.2 ระบบจับเวลา 3 นาที (180 วินาที) ของการเปิดเอกสารลับ
            $unlockedAt = session('secret_unlocked_' . $document->id);
            $timeoutSeconds = 180; // ตั้งเวลาหมดอายุที่ 3 นาที

            if (!$unlockedAt || (now()->timestamp - $unlockedAt) > $timeoutSeconds) {
                // ถ้าไม่เคยปลดล็อก หรือ เวลาผ่านไปเกิน 3 นาทีแล้ว ให้เคลียร์ session ทิ้ง
                session()->forget('secret_unlocked_' . $document->id);
                
                // โยนไปหน้ากรอกรหัสผ่าน
                return view('documents.unlock_secret', compact('document'));
            } else {
                // 🌟 ต่อเวลาให้ ถ้าผู้ใช้กำลังอ่านหรือรีเฟรชหน้าเอกสารนี้อยู่ (ไม่ให้เด้งหลุดขณะทำงาน)
                session(['secret_unlocked_' . $document->id => now()->timestamp]);
            }
        }

        // --- (โค้ดแสดงผลเอกสารตามปกติ) ---
        if ($document->doc_type === 'outgoing') {
            return view('documents.show_outgoing', compact('document'));
        } 
        elseif ($document->doc_type === 'internal') {
            return view('documents.show_internal', compact('document'));
        } 
        elseif ($document->doc_type === 'incoming') {
            return view('documents.show_incoming', compact('document'));
        }

        return view('documents.show', compact('document'));
    }

    public function createUpload()
    {
        return view('documents.create_upload');
    }

    public function storeUpload(Request $request)
    {
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
        
        // 🌟 บันทึกชั้นความลับและความเร็ว (ถ้าไม่ได้เลือกจะตั้งเป็น ปกติ อัตโนมัติ)
        $document->doc_secret     = $request->doc_secret ?? 'ปกติ';
        $document->doc_speed      = $request->doc_speed ?? 'ปกติ';

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('attachments', 'public');
            $document->attachment_path = $path;
        }

        $document->save();

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'อัปโหลดบันทึกข้อความเรียบร้อย กรุณาลงนามเพื่อส่งเรื่อง');
    }

    public function create()
    {
        return view('documents.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:255',
            'content'  => 'required|string',
            'doc_date' => 'required'
        ]);

        $document = new Document();
        $document->doc_type   = 'internal';
        $document->title      = $request->title;
        $document->doc_number = $request->doc_number;
        $document->doc_date   = $request->doc_date;
        $document->content    = trim(preg_replace('/\n{3,}/', "\n\n", $request->content));
        $document->running_number = $request->running_number;
        $document->created_by = Auth::id();
        $document->status     = 'DRAFT';
        $document->save();

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกร่างเอกสารเรียบร้อย กรุณาตรวจสอบและลงนาม');
    }

    public function sign(Request $request, $id)
    {
        $request->validate(['pin' => 'required|digits:6']);
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $throttleKey = 'pin-sign-attempts:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error_pin', 'คุณกรอกรหัส PIN ผิดเกินกำหนด (5 ครั้ง) กรุณารออีก ' . $seconds . ' วินาที');
        }

        if (empty($user->signature) || empty($user->pin)) {
            return back()->with('error_signature', 'กรุณาอัปโหลดลายเซ็นและตั้งรหัส PIN ก่อนทำการลงนามส่งเรื่องครับ');
        }

        if (!Hash::check($request->pin, $user->pin)) {
            RateLimiter::hit($throttleKey, 60); 
            return back()->with('error_pin', 'รหัสผ่าน (PIN) ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        RateLimiter::clear($throttleKey);

        if ($document->status === 'DRAFT' && $document->created_by === $user->id) {
            
            $document->creator_signature = $user->signature;

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

            $document->save();

            // 🌟 เช็คเรื่องการประทับลายเซ็นผู้เสนอเรื่องบนไฟล์อัปโหลด 🌟
            if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                
                // ถ้ายูสเซอร์ติ๊กถูก (เอาประทับลายเซ็น)
                if ($request->has('stamp_creator') && $request->stamp_creator == '1') {
                    $this->autoStampCreator($document, $user);
                } else {
                    // ถ้าไม่ปั๊ม ให้ลบไฟล์ที่เคยปั๊มไปแล้ว (ถ้ามี) ทิ้งไป เพื่อป้องกันความสับสน
                    $stampedPath = 'documents/stamped/stamped_' . $document->id . '.pdf';
                    if ( Storage::disk('public')->exists($stampedPath)) {
                         Storage::disk('public')->delete($stampedPath);
                    }
                }

                // สั่งหุ่นยนต์ทำใบแนบท้ายตามปกติ
                $this->autoAppendSignaturePage($document);
            }

           // =========================================================
            // 🌟 แจ้งเตือน LINE เมื่อมีการเซ็นส่งเรื่อง
            // =========================================================
            $docUrl = route('documents.show', $document->uuid ?? $document->id);
            $lineMsg = "🔔 มีเอกสารรอการพิจารณาใหม่!\n";
            $lineMsg .= "เรื่อง: " . $document->title . "\n";
            $lineMsg .= "ผู้เสนอ: " . $user->name . " (" . ($user->department ?? '-') . ")\n";
            
            if ($document->doc_speed !== 'ปกติ') $lineMsg .= "ความเร็ว: ⚡" . $document->doc_speed . "\n";
            if ($document->doc_secret !== 'ปกติ' && $document->doc_secret !== 'ไม่มีชั้นความลับ') $lineMsg .= "ความลับ: 🔒" . $document->doc_secret . "\n";
            
            $lineMsg .= "\n👇 เปิดดูเอกสารที่นี่:\n" . $docUrl;

            // 🌟 เติม $document เข้าไปตรงนี้ เพื่อให้ระบบรู้ว่าต้องส่งหาใคร! 🌟
            $this->sendLineMessage($lineMsg, $document);
            // =========================================================

            return redirect()->route('home')->with('success', $success_msg);
        }

        return back()->with('error', 'คุณไม่มีสิทธิ์เซ็นเอกสารในขั้นตอนนี้');
    }

    public function edit($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if ($document->created_by !== Auth::id() || !in_array($document->status, ['DRAFT', 'REJECTED'])) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แก้ไขเอกสารนี้');
        }

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

    public function update(Request $request, $id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        if ($document->created_by !== Auth::id() || !in_array($document->status, ['DRAFT', 'REJECTED'])) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แก้ไขเอกสารนี้');
        }

        // 🌟 1. ลบไฟล์ที่ประทับลายเซ็นหรือต่อใบแนบท้ายเก่าทิ้งไป (เพราะเอกสารถูกแก้ไขเนื้อหาแล้ว)
        if ($document->signed_path) {
            Storage::disk('public')->delete($document->signed_path);
        }
        $stampedPath = 'documents/stamped/stamped_' . $document->id . '.pdf';
        if (Storage::disk('public')->exists($stampedPath)) {
            Storage::disk('public')->delete($stampedPath);
        }

        // 🌟 2. เพิ่มการเคลียร์ signed_path และ creator_signature ให้กลับเป็นค่าว่าง
        $resetApprovals = [
            'status'                 => 'DRAFT',
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
                if ($document->attachment_path) Storage::disk('public')->delete($document->attachment_path);
                $updateData['attachment_path'] = $request->file('file')->store('incoming_docs', 'public');
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
                if ($document->attachment_path) Storage::disk('public')->delete($document->attachment_path);
                $updateData['attachment_path'] = $request->file('file')->store('outgoing_docs', 'public');
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
                if ($document->attachment_path) Storage::disk('public')->delete($document->attachment_path);
                $updateData['attachment_path'] = $request->file('file')->store('attachments', 'public');
            }

            $document->update($updateData);
        }

        return redirect()->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'แก้ไขเอกสารเรียบร้อยแล้ว กรุณาลงนามเพื่อส่งเรื่องใหม่อีกครั้ง');
    }

    // ========================================================================
    // --- โซนที่ 2: หนังสือรับเข้า ---
    // ========================================================================

    public function createIncoming()
    {
        return view('documents.create_incoming');
    }

    public function storeIncoming(Request $request)
    {
        $request->validate([
            'receive_number'    => 'required|string|max:255',
            'receive_date'      => 'required|date',
            'doc_number'        => 'required|string|max:255',
            'doc_date'          => 'required|date',
            'title'             => 'required|string|max:255',
            'doc_from'          => 'required|string|max:255',
            'doc_type_category' => 'required|string|max:255',
            'doc_speed'         => 'nullable|string|max:50',
            'doc_secret'        => 'nullable|string|max:50',
            'file'              => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'external_url'      => 'nullable|url',
        ], [
            'title.required'    => 'กรุณาระบุเรื่อง',
            'file.mimes'        => 'รองรับเฉพาะไฟล์ PDF, JPG, PNG เท่านั้น',
            'file.max'          => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
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

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('incoming_docs', 'public');
            $document->attachment_path = $path;
            $document->content = 'อ้างอิงจากไฟล์แนบในระบบ';
        } else {
            $document->content = '<p>เอกสารรับเข้าผ่านการสแกน QR Code:</p>
                <a href="' . e($request->external_url) . '" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-external-link-alt"></i> คลิกเพื่อเปิดดูเอกสารต้นฉบับ</a>';
        }

        $document->save();

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกหนังสือรับเข้าเรียบร้อย กรุณาตรวจสอบข้อมูลและลงนาม');
    }

    // ========================================================================
    // --- โซนที่ 3: หนังสือส่งออก ---
    // ========================================================================

   public function createOutgoing()
    {
        $incomingDocs = Document::where('doc_type', 'incoming')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $signers = \App\Models\User::role(['executive', 'palad', 'deputy-palad'])
            ->orWhere(function($query) {
                $query->role('head') 
                      ->where('department', 'LIKE', '%สำนักงานปลัด%'); 
            })
            ->get();

        return view('documents.create_outgoing', compact('incomingDocs', 'signers'));
    }

    public function storeOutgoing(Request $request)
    {
        $request->validate([
            'doc_number'        => 'required|string|max:255',
            'doc_date'          => 'required|date',
            'doc_type_category' => 'required|string|max:255',
            'title'             => 'required|string|max:255',
            'doc_to'            => 'required|string|max:255',
            'signer_name'       => 'required|string|max:255', 
            'file'              => 'required|file|mimes:pdf|max:20480',
            'running_number'    => 'nullable|integer',
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

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('outgoing_docs', 'public');
            $document->attachment_path = $path;
            
            $document->content = $request->filled('attachment') 
                ? 'สิ่งที่ส่งมาด้วย: ' . $request->attachment 
                : 'อ้างอิงจากไฟล์แนบในระบบ';
        }

        $document->save();

        return redirect()
            ->route('documents.show', $document->uuid ?? $document->id)
            ->with('success', 'บันทึกร่างหนังสือส่งออกเรียบร้อย กรุณาตรวจสอบข้อมูลและลงนามเพื่อส่งเรื่อง');
    }

    // ========================================================================
    // --- โซนที่ 4: พิจารณาและอนุมัติ (รวมถึงประทับลายเซ็นอัตโนมัติ) ---
    // ========================================================================

    public function approveList()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            $documents = Document::whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELED'])
                ->orderBy('created_at', 'desc')->get();
            return view('documents.approve_list', compact('documents'));
        }

        $query = Document::query();

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

        $documents = $query->orderBy('created_at', 'desc')->get();

        return view('documents.approve_list', compact('documents'));
    }

    public function reviewDocument(Request $request, $id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $throttleKey = 'pin-review-attempts:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error_pin', 'คุณกรอกรหัส PIN ผิดเกินกำหนด (5 ครั้ง) กรุณารออีก ' . $seconds . ' วินาที');
        }

        if (empty($user->signature) || empty($user->pin)) {
            return back()->with('error_signature', 'กรุณาอัปโหลดลายเซ็นและตั้งรหัส PIN ก่อนดำเนินการครับ');
        }

        if (!Hash::check($request->pin, $user->pin)) {
            RateLimiter::hit($throttleKey, 60);
            return back()->with('error_pin', 'รหัสผ่าน (PIN) ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        RateLimiter::clear($throttleKey);

        $signature = $user->signature;
        $comment = $request->input('comment'); 

        // กรณี อนุมัติ / เห็นชอบ (Approve)
        if ($request->is_approved == '1') {
            
            $msg = ''; // ตัวแปรเก็บข้อความสำเร็จ

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
                
                // 🌟 ประทับลายเซ็นอัตโนมัติ (สำหรับหนังสือส่งออก)
                if ($isFinalSigner) {
                    $this->autoStampPdf($document, $user);
                }
                
                // 🌟 อัปเดตวาดใบแนบท้าย PDF ใหม่ (สำหรับบันทึกข้อความภายใน)
                if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                    $this->autoAppendSignaturePage($document);
                }

                $msg = $isFinalSigner ? 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)' : 'หัวหน้าส่วนราชการพิจารณาเห็นชอบและส่งเรื่องต่อให้ ปลัด อบต. แล้ว';

            } elseif (($user->hasRole('palad') || $user->hasRole('deputy-palad')) && $document->status === 'WAITING_PALAD') {
                
                $assignedTo = $request->filled('assigned_to') ? $request->assigned_to : $document->assigned_to;
                $isFinalSigner = ($document->doc_type === 'outgoing' && str_contains($document->signer_name ?? '', 'ปลัด'));
                $nextStatus = $isFinalSigner ? 'APPROVED' : 'WAITING_NAYOK';

                $document->update([
                    'status'           => $nextStatus,
                    'palad_id'         => $user->id,
                    'palad_signature'  => $signature,
                    'palad_approved_at'=> now(),
                    'palad_comment'    => $comment, 
                    'assigned_to'      => $assignedTo, 
                ]);
                
                // 🌟 ประทับลายเซ็นอัตโนมัติ
                if ($isFinalSigner) {
                    $this->autoStampPdf($document, $user);
                }

                // 🌟 อัปเดตวาดใบแนบท้าย PDF ใหม่ (สำหรับบันทึกข้อความภายใน)
                if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                    $this->autoAppendSignaturePage($document);
                }

                $msg = $isFinalSigner ? 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)' : 'พิจารณาเห็นชอบส่งต่อให้นายกฯ เรียบร้อย';
            
            } elseif ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK') {
                
                if ($document->doc_type === 'incoming') {
                    $newStatus = 'APPROVED'; 
                    $successMsg = 'ลงนามสั่งการหนังสือรับเข้าเรียบร้อยแล้ว';
                } elseif ($document->doc_type === 'outgoing') {
                    $newStatus = 'APPROVED'; 
                    $successMsg = 'ลงนามหนังสือส่งออกและประทับลายเซ็นเรียบร้อยแล้ว (เอกสารสมบูรณ์)';
                } else {
                    $newStatus = 'WAITING_NUMBERING'; 
                    $successMsg = 'ลงนามอนุมัติและส่งให้ธุรการลงทะเบียนเลขเรียบร้อยแล้ว';
                }

                $assignedTo = $request->filled('assigned_to') ? $request->assigned_to : $document->assigned_to;

                $document->update([
                    'status'           => $newStatus,
                    'nayok_id'         => $user->id,
                    'nayok_signature'  => $signature,
                    'nayok_approved_at'=> now(),
                    'nayok_comment'    => $comment, 
                    'assigned_to'      => $assignedTo, 
                ]);
                
                // 🌟 ประทับลายเซ็นอัตโนมัติ (นายกเซ็นหนังสือส่งออก ถือว่าจบงานเสมอ)
                if ($document->doc_type === 'outgoing') {
                    $this->autoStampPdf($document, $user);
                }

                // 🌟 อัปเดตวาดใบแนบท้าย PDF ใหม่ (สำหรับบันทึกข้อความภายใน)
                if ($document->doc_type === 'internal' && str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ')) {
                    $this->autoAppendSignaturePage($document);
                }

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
                return back()->with('error', 'คุณไม่มีสิทธิ์ดำเนินการในขั้นตอนนี้');
            }

            // =========================================================
            // 🌟 แจ้งเตือน LINE ทันทีที่มีการเปลี่ยนสถานะส่งต่อ 🌟
            // =========================================================
            if (!in_array($document->status, ['APPROVED', 'CANCELED'])) {
                $docUrl = route('documents.show', $document->uuid ?? $document->id);
                $lineMsg = "🔔 มีเอกสารส่งต่อถึงคุณ!\n";
                $lineMsg .= "เรื่อง: " . $document->title . "\n";
                
                if ($document->doc_speed !== 'ปกติ') $lineMsg .= "ความเร็ว: ⚡" . $document->doc_speed . "\n";
                
                $lineMsg .= "สถานะ: ต้องพิจารณา/ลงนาม\n";
                $lineMsg .= "\n👇 เปิดดูเอกสารที่นี่:\n" . $docUrl;

                // สั่งให้บอทเช็คว่าคิวต่อไปคือใคร แล้วส่งหาคนนั้น
                $this->sendLineMessage($lineMsg, $document);
            }
            // =========================================================

            return redirect()->route('documents.approve_list')->with('success', $msg);

        } else {
            // กรณี ตีกลับ / ไม่อนุมัติ (Reject)
            $request->validate(['comment' => 'required|string|max:1000'], [
                'comment.required' => 'กรุณาระบุความเห็นหรือเหตุผลการตีกลับเอกสารในช่องความเห็น'
            ]);

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
            ]);

            // =========================================================
            // 🌟 แจ้งเตือน LINE กรณีโดนตีกลับเอกสาร 🌟
            // =========================================================
            if ($document->creator && !empty($document->creator->line_id)) {
                $docUrl = route('documents.show', $document->uuid ?? $document->id);
                $lineMsg = "⚠️ เอกสารของคุณถูกตีกลับ/ตีตก!\n";
                $lineMsg .= "เรื่อง: " . $document->title . "\n";
                $lineMsg .= "เหตุผล: " . $comment . "\n";
                $lineMsg .= "\n👇 ดูรายละเอียด:\n" . $docUrl;
                
                $token = env('LINE_BOT_TOKEN');
                if (!empty($token)) {
                    try {
                        \Illuminate\Support\Facades\Http::withToken($token)
                            ->post('https://api.line.me/v2/bot/message/push', [
                                'to' => $document->creator->line_id,
                                'messages' => [['type' => 'text', 'text' => $lineMsg]]
                            ]);
                    } catch (\Exception $e) {
                        // ปล่อยผ่านไปหากเชื่อมต่อ API ไม่สำเร็จ
                    }
                }
            }
            // =========================================================

            return redirect()->route('documents.approve_list')->with('success', $msg);
        }
    }

    private function autoStampPdf($doc, $user)
    {
        if (!$doc->attachment_path || !Storage::disk('public')->exists($doc->attachment_path)) return false;
        if (!$user->signature) return false;

        $originalPdf = storage_path('app/public/' . $doc->attachment_path);
        $newFileName = 'signed_' . time() . '_' . basename($doc->attachment_path);
        $signedStoragePath = 'documents/signed/' . $newFileName;
        $outputPdf = storage_path('app/public/' . $signedStoragePath);
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
        $pythonCommand = env('PYTHON_COMMAND', 'C:\\Users\\chari\\AppData\\Local\\Programs\\Python\\Python312\\python.exe');
        
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
        if (!$doc->attachment_path || !Storage::disk('public')->exists($doc->attachment_path)) return false;
        if (!$user->signature) return false;

        $originalPdf = storage_path('app/public/' . $doc->attachment_path);
        
        $stampedStoragePath = 'documents/stamped/stamped_' . $doc->id . '.pdf';
        $outputPdf = storage_path('app/public/' . $stampedStoragePath);
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
        $pythonCommand = env('PYTHON_COMMAND', 'C:\\Users\\chari\\AppData\\Local\\Programs\\Python\\Python312\\python.exe');
        
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
        if (!$doc->attachment_path || !Storage::disk('public')->exists($doc->attachment_path)) return false;

        $stampedPath = 'documents/stamped/stamped_' . $doc->id . '.pdf';
        if (Storage::disk('public')->exists($stampedPath)) {
            $originalPdf = storage_path('app/public/' . $stampedPath);
        } else {
            $originalPdf = storage_path('app/public/' . $doc->attachment_path);
        }

        $newFileName = 'appended_' . time() . '_' . basename($doc->attachment_path);
        $signedStoragePath = 'documents/signed/' . $newFileName;
        $outputPdf = storage_path('app/public/' . $signedStoragePath);

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
                Storage::disk('public')->delete($doc->signed_path);
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

    public function uploadAttachment(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ที่ต้องการอัปโหลด',
            'file.mimes' => 'รองรับเฉพาะไฟล์ PDF, JPG, PNG เท่านั้น',
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 5MB',
        ]);

        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if ($document->created_by !== Auth::id() || !in_array($document->status, ['DRAFT', 'REJECTED'])) {
            return back()->with('error', 'คุณไม่มีสิทธิ์แนบไฟล์ในขั้นตอนนี้');
        }

        if ($document->attachment_path) {
            Storage::disk('public')->delete($document->attachment_path);
        }

        $path = $request->file('file')->store('attachments', 'public');
        $document->attachment_path = $path;
        $document->save();

        return back()->with('success', 'อัปโหลดไฟล์แนบเรียบร้อยแล้ว');
    }

    public function destroy($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (in_array($document->status, ['DRAFT', 'REJECTED']) && $document->created_by === $user->id) {
            if ($document->attachment_path) {
                Storage::disk('public')->delete($document->attachment_path);
            }
            $document->delete();

            return redirect()
                ->route('documents.approve_list')
                ->with('success', 'ลบเอกสารเรียบร้อยแล้ว');
        }

        return back()->with('error', 'คุณไม่สามารถลบเอกสารนี้ได้ เนื่องจากส่งพิจารณาไปแล้ว หรือถูกบันทึกเป็นเลขเสีย');
    }

    public function downloadSignedPdf($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if (in_array($document->status, ['DRAFT', 'REJECTED', 'CANCELED'])) {
            return back()->with('error', 'ไม่สามารถดาวน์โหลดเอกสารที่ถูกยกเลิก หรือยังไม่สิ้นสุดกระบวนการได้');
        }

        if (!$document->attachment_path) {
            return back()->with('error', 'ไม่พบไฟล์แนบต้นฉบับ');
        }

        $pdf = Pdf::loadView('documents.pdf_approval', compact('document'));
        $pdf->setOption(['isRemoteEnabled' => true]); 
        
        $tempApprovalPath = storage_path('app/public/temp_approval_' . $document->id . '.pdf');
        $pdf->save($tempApprovalPath);

        $fpdi = new Fpdi();

        $originalPath = storage_path('app/public/' . $document->attachment_path);
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

    public function routingSlip($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if ($document->doc_type !== 'incoming') {
            return back()->with('error', 'ใบเกษียนหนังสือใช้สำหรับหนังสือรับเข้าเท่านั้น');
        }

        return view('documents.routing_slip', compact('document'));
    }

    // ========================================================================
    // --- โซนที่ 6: สมุดคุมเลข และ API รันเลขอัตโนมัติ ---
    // ========================================================================

    public function numberLedger(Request $request)
    {
        $type = $request->input('type', 'outgoing'); 
        $userDept = Auth::user()->department;
        $dept = $request->input('department', $userDept); 
        
        $currentMonth = date('m');
        $currentYear = date('Y');
        $defaultFiscalYear = ($currentMonth >= 10) ? $currentYear + 1 : $currentYear;
        $year = $request->input('year', $defaultFiscalYear);
        
        $start = ($year - 1) . '-10-01 00:00:00';
        $end = $year . '-09-30 23:59:59';

        $query = Document::where('doc_type', $type)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('running_number');

        if ($type === 'internal') {
            $query->whereHas('creator', function ($q) use ($dept) {
                $q->where('department', $dept);
            });
        }

        $documents = $query->orderBy('running_number', 'asc')->get();

        $usedNumbers = $documents->keyBy('running_number');
        $maxNumber = $documents->max('running_number') ?? 0;
        
        $ledger = [];
        $limit = max(50, $maxNumber + 20); 
        
        for ($i = 1; $i <= $limit; $i++) {
            if ($usedNumbers->has($i)) {
                $doc = $usedNumbers->get($i);
                
                if ($doc->status === 'APPROVED') {
                    $status = 'ออกเลขแล้ว';
                } elseif ($doc->status === 'CANCELED') {
                    $status = 'ยกเลิก';
                } elseif ($doc->status === 'RESERVED') {
                    $status = 'จองเลขมือ'; 
                } else {
                    $status = 'จองรออนุมัติ'; 
                }

                $ledger[$i] = [
                    'status' => $status,
                    'doc' => $doc
                ];
            } else {
                $ledger[$i] = ['status' => 'ว่าง', 'doc' => null];
            }
        }

        $departments = \App\Models\User::select('department')->distinct()->pluck('department')->filter();

        return view('documents.number_ledger', compact('ledger', 'type', 'year', 'maxNumber', 'dept', 'departments'));
    }

    public function apiNextNumber(Request $request)
    {
        $type = $request->input('type', 'outgoing');
        $user = Auth::user();
        
        $currentMonth = date('m');
        $currentYear = date('Y');
        $fiscalYear = ($currentMonth >= 10) ? $currentYear + 1 : $currentYear;

        $start = ($fiscalYear - 1) . '-10-01 00:00:00';
        $end = $fiscalYear . '-09-30 23:59:59';

        $query = Document::where('doc_type', $type)
            ->whereBetween('created_at', [$start, $end]);

        if ($type === 'internal') {
            $dept = $user->department;
            $query->whereHas('creator', function ($q) use ($dept) {
                $q->where('department', $dept);
            });
        }

        $maxNumber = $query->max('running_number') ?? 0;
        $nextNumber = $maxNumber + 1;
        
        $thDigits = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
        $thNum = str_replace(range(0,9), $thDigits, (string)$nextNumber);

        $formatted = (string)$nextNumber;
        if ($type === 'incoming') {
            $formatted = 'เลขรับที่ ' . $nextNumber; 
        } elseif ($type === 'outgoing') {
            $formatted = 'ยล ๗๗๓๐๑/' . $thNum;
        } elseif ($type === 'internal') {
            $deptName = $user->department;
            $shortDept = 'ก.';
            if (str_contains($deptName, 'ปลัด')) $shortDept = 'สป';
            elseif (str_contains($deptName, 'คลัง')) $shortDept = 'กค';
            elseif (str_contains($deptName, 'ช่าง')) $shortDept = 'กช';
            elseif (str_contains($deptName, 'ศึกษา')) $shortDept = 'กศ';
            elseif (str_contains($deptName, 'สาธารณสุข')) $shortDept = 'กส';
            elseif (str_contains($deptName, 'สวัสดิการ')) $shortDept = 'กสว';
            
            $formatted = '(' . $shortDept . ') ' . $nextNumber;
        }
        
        return response()->json([
            'next_number' => $nextNumber,
            'formatted' => $formatted
        ]);
    }

    public function assignedList()
    {
        $userDepartment = auth()->user()->department;

        $departmentAliases = [
            'สำนักงานปลัด'                    => ['สำนักงานปลัด', 'สำนักปลัด'],
            'กองการศึกษา ศาสนา และวัฒนธรรม'  => ['กองการศึกษา ศาสนา และวัฒนธรรม', 'กองการศึกษาฯ', 'กองศึกษา'],
            'กองคลัง'                          => ['กองคลัง'],
            'กองช่าง'                          => ['กองช่าง'],
            'กองสาธารณสุขและสิ่งแวดล้อม'      => ['กองสาธารณสุขและสิ่งแวดล้อม', 'กองสาธารณสุข'],
        ];

        $aliases = $departmentAliases[$userDepartment] ?? [$userDepartment];

        $documents = Document::whereIn('assigned_to', $aliases)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('documents.assigned_list', compact('documents'));
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

    public function registry(Request $request)
    {
        $year = $request->input('year', date('Y')); 
        $search = $request->input('search');

        $docQuery = Document::query()
            ->when($year, function($q) use ($year) {
                return $q->whereYear('created_at', $year);
            })
            ->when($search, function($q) use ($search) {
                return $q->where('title', 'like', "%{$search}%")
                         ->orWhere('doc_number', 'like', "%{$search}%");
            });

        $pendingDocuments = (clone $docQuery)->where('status', 'WAITING_NUMBERING')
            ->orderBy('updated_at', 'asc')->get();

        $outgoingDocuments = (clone $docQuery)->whereIn('status', ['APPROVED', 'CANCELED'])
            ->whereIn('doc_type', ['internal', 'outgoing']) 
            ->whereNotNull('doc_number')
            ->orderBy('updated_at', 'desc')->get();

        $incomingDocuments = (clone $docQuery)
            ->where('doc_type', 'incoming')
            ->whereNotNull('receive_number') 
            ->orderBy('updated_at', 'desc')
            ->get();

        $summary = [
            'pending' => $pendingDocuments->count(),
            'outgoing' => $outgoingDocuments->count(),
            'incoming' => $incomingDocuments->count(),
        ];

        return view('documents.registry', compact('pendingDocuments', 'outgoingDocuments', 'incomingDocuments', 'summary', 'year', 'search'));
    }

    public function assignNumber(Request $request, $id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();

        if ($document->status !== 'WAITING_NUMBERING') {
            return redirect()->back()->with('error', 'เอกสารฉบับนี้เสร็จสิ้นกระบวนการออกเลขแล้ว ไม่สามารถออกเลขซ้ำได้');
        }

        $request->validate([
            'doc_number' => 'required|string|max:255'
        ]);
        
        $document->doc_number = $request->doc_number;
        $document->status = 'APPROVED';
        $document->save();

        return redirect()->back()->with('success', 'ลงทะเบียนเลขที่เอกสารเรียบร้อยแล้ว');
    }

    public function reserveNumber(Request $request, $id)
    {
       // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        
        $request->validate([
            'doc_number' => 'required|string|max:255'
        ]);

        $document->update([
            'doc_number' => $request->doc_number,
            'running_number' => $request->running_number
        ]);

        return back()->with('success', 'จองเลขที่หนังสือ: ' . $request->doc_number . ' ล่วงหน้าเรียบร้อยแล้ว');
    }

    public function acknowledge($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('head') && $document->assigned_to === $user->department) {
            $document->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $user->id,
            ]);
            return back()->with('success', 'บันทึกการรับทราบคำสั่งเรียบร้อยแล้ว ระบบได้เก็บประวัติเวลาไว้เป็นหลักฐาน');
        }

        return back()->with('error', 'คุณไม่มีสิทธิ์ดำเนินการในขั้นตอนนี้');
    }

    public function generateDraft(Request $request)
    {
        $request->validate(['topic' => 'required|string']);

        $apiKey = env('TYPHOON_API_KEY');
        
        if (!$apiKey) {
            return response()->json(['error' => 'ระบบติดต่อปัญญาประดิษฐ์ยังไม่พร้อมใช้งาน'], 500);
        }

        $systemPrompt = "คุณคือปลัดองค์การบริหารส่วนตำบล ผู้เชี่ยวชาญระเบียบงานสารบรรณ จงร่างบันทึกข้อความจากหัวข้อที่กำหนดให้ โดยเขียนในรูปแบบเอกสารราชการ ๓ ย่อหน้า (ภาคเหตุ, ภาคความประสงค์, ภาคสรุป) ใช้ภาษาทางการ สั้น กระชับ สละสลวย **คำสั่งสำคัญ:** 1. ให้ตอบมาเฉพาะเนื้อหาล้วนๆ ห้ามมีคำว่า 'เรื่อง', 'เรียน', 'จึงเรียนมาเพื่อโปรดทราบ' หรือคำอธิบายเพิ่มเติมใดๆ 2. ให้ขึ้นต้นแต่ละย่อหน้าด้วยการย่อหน้า (ไม่ต้องพิมพ์คำว่า ภาคเหตุ/ประสงค์/สรุป นำหน้า)";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60) 
                ->post('https://api.opentyphoon.ai/v1/chat/completions', [
                    'model' => 'typhoon-v2.5-30b-a3b-instruct', 
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

    public function unlockSecret(Request $request, $id)
    {
        $request->validate(['pin' => 'required|digits:6']);
       // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $throttleKey = 'pin-unlock-attempts:' . $user->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            return back()->with('error_pin', 'คุณกรอกรหัส PIN ผิดเกินกำหนด กรุณารออีก ' . $seconds . ' วินาที');
        }

        if (empty($user->pin)) {
            return back()->with('error_signature', 'คุณยังไม่ได้ตั้งรหัส PIN! กรุณาตั้งค่าที่หน้าโปรไฟล์ก่อนเปิดเอกสารลับ');
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->pin, $user->pin)) {
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);
            return back()->with('error_pin', 'รหัสผ่าน (PIN) ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        // 🌟 ปลดล็อกสำเร็จ: บันทึก เวลา (Timestamp) ปัจจุบัน เพื่อเริ่มจับเวลา 3 นาที
        session(['secret_unlocked_' . $document->id => now()->timestamp]);

        // 🌟 เปลี่ยน Redirect กลับไปเป็น uuid 
        return redirect()->route('documents.show', $document->uuid ?? $document->id);
    }

    public function requestAccess($id)
    {
        // 🌟 เปลี่ยนมาค้นหาจาก uuid (พร้อมรองรับ id เก่าเผื่อตกหล่น)
        $document = Document::with(['creator', 'supervisor', 'palad', 'nayok'])
            ->where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        $user = Auth::user();

        \App\Models\DocumentAccessRequest::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $user->id],
            ['status' => 'pending']
        );

        return back()->with('success', 'ส่งคำขอสิทธิ์การเข้าถึงเรียบร้อยแล้ว กรุณารอเจ้าของเรื่องหรือธุรการอนุมัติ');
    }

    public function approveAccess($requestId, $status)
    {
        $accessRequest = \App\Models\DocumentAccessRequest::findOrFail($requestId);
        $document = Document::findOrFail($accessRequest->document_id);
        
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // เช็คว่าคนกดอนุมัติ เป็น "เจ้าของเรื่อง" หรือ "ธุรการ" หรือไม่
        if ($user->id !== $document->created_by && !$user->hasRole('saraban')) {
            return back()->with('error', 'คุณไม่มีสิทธิ์จัดการคำขอของเอกสารนี้');
        }

        $accessRequest->update(['status' => $status]); // 'approved' หรือ 'rejected'

        $msg = $status === 'approved' ? 'อนุมัติสิทธิ์สำเร็จ ผู้ขอสามารถเปิดดูเอกสารได้แล้ว' : 'ปฏิเสธคำขอสิทธิ์สำเร็จ';
        return back()->with('success', $msg);
    }

    // =========================================================================
    // 🌟 โซนฟังก์ชันสำหรับระบบแจ้งเตือนผ่าน LINE Messaging API (LINE Bot)
    // =========================================================================
    private function sendLineMessage($messageText, $document = null)
    {
        $token = env('LINE_BOT_TOKEN');
        $targetId = env('LINE_TARGET_ID'); // ไอดีแอดมิน (เอาไว้กันเหนียวถ้าหาใครไม่เจอ)
        
        // 🎯 ถ้าระบุเอกสารมาด้วย ให้บอทค้นหา LINE ID ของคนรับตามสถานะปัจจุบัน
        if ($document) {
            $foundLineId = null;

            if ($document->status === 'WAITING_SUPERVISOR') {
                // หา ผอ.กอง หรือ หัวหน้าส่วนราชการ (แผนกเดียวกับเจ้าของเรื่อง)
                $creatorDept = $document->creator->department ?? '';
                $head = \App\Models\User::role('head')->where('department', $creatorDept)->first();
                $foundLineId = $head->line_id ?? null;
            } 
            elseif ($document->status === 'WAITING_PALAD') {
                // หา ปลัด หรือ รองปลัด
                $palad = \App\Models\User::role(['palad', 'deputy-palad'])->first();
                $foundLineId = $palad->line_id ?? null;
            } 
            elseif ($document->status === 'WAITING_NAYOK') {
                // หา นายก อบต.
                $nayok = \App\Models\User::role('executive')->first();
                $foundLineId = $nayok->line_id ?? null;
            } 
            elseif (in_array($document->status, ['WAITING_NUMBERING', 'WAITING_ADMIN'])) {
                // หา ธุรการ (สารบรรณ)
                $saraban = \App\Models\User::role('saraban')->first();
                $foundLineId = $saraban->line_id ?? null;
            }

            // ถ้าเจอไอดีของคนที่ต้องพิจารณา ให้เปลี่ยนเป้าหมายการส่ง
            if (!empty($foundLineId)) {
                $targetId = $foundLineId;
            }
        }

        if (empty($token) || empty($targetId)) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::withToken($token)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $targetId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $messageText
                        ]
                    ]
                ]);
        } catch (\Exception $e) {
            // โยนทิ้งไปเลย ไม่ต้องแจ้ง Error หน้าเว็บ
        }
    }
}
