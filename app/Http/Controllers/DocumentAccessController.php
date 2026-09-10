<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Services\AuditLogger;
use App\Services\LineMessagingService;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\V2\DocumentReadService as V2DocumentReadService;
use App\Services\V2\DocumentWriteService as V2DocumentWriteService;
use App\Models\V2\DocumentAccessRequest as V2DocumentAccessRequest;
use Illuminate\Support\Facades\DB;

class DocumentAccessController extends Controller
{
    public function unlock(Request $request, string $id, AuditLogger $audit, V2DocumentReadService $v2Documents)
    {
        $request->validate(['pin' => ['required', 'digits:6']]);
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            $this->authorize('accessConfidential', $document);
            $user = $request->user();
            $key = 'pin-unlock-attempts:'.$user->id;
            if (RateLimiter::tooManyAttempts($key, 5)) { return back()->with('error_pin', 'กรอก PIN ผิดเกินกำหนด กรุณารอสักครู่'); }
            if (! $user->pin || ! Hash::check($request->pin, $user->pin)) {
                RateLimiter::hit($key, 60);
                $this->v2Audit('document.secret_unlock_failed', $document->id, $user->id);
                return back()->with('error_pin', 'รหัส PIN ไม่ถูกต้อง');
            }
            RateLimiter::clear($key);
            session(['secret_unlocked_'.$document->id => now()->timestamp]);
            $this->v2Audit('document.secret_opened', $document->id, $user->id);
            return redirect()->route('documents.show', $document->uuid);
        }
        $document = Document::whereIdentifier($id)->firstOrFail();
        $this->authorize('accessConfidential', $document);
        $user = $request->user();
        $key = 'pin-unlock-attempts:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error_pin', 'กรอก PIN ผิดเกินกำหนด กรุณารออีก '.RateLimiter::availableIn($key).' วินาที');
        }
        if (! $user->pin || ! Hash::check($request->pin, $user->pin)) {
            RateLimiter::hit($key, 60);
            $audit->log('document.secret_unlock_failed', $document);

            return back()->with('error_pin', 'รหัส PIN ไม่ถูกต้อง');
        }

        RateLimiter::clear($key);
        session(['secret_unlocked_'.$document->id => now()->timestamp]);
        $audit->log('document.secret_opened', $document);

        return redirect()->route('documents.show', $document->uuid ?? $document->id);
    }

    public function request(Request $request, string $id, NotificationDispatcher $notifications, LineMessagingService $line, V2DocumentReadService $v2Documents, V2DocumentWriteService $v2Writes)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $v2Documents->find($id);
            abort_unless(($document->confidentiality?->level_no ?? 0) > 1, 422);
            $v2Writes->requestAccess($document, $request->user()->id, $request->input('reason'));
            return back()->with('success', 'ส่งคำขอสิทธิ์เข้า V2 เรียบร้อยแล้ว');
        }
        $document = Document::with('creator')->whereIdentifier($id)->firstOrFail();
        $this->authorize('view', $document);
        abort_unless(in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'], true), 422);

        DocumentAccessRequest::updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $request->user()->id],
            ['status' => 'pending']
        );

        $message = "🔐 มีคำขอเข้าถึงเอกสารลับ\nเรื่อง: {$document->title}\nผู้ขอ: {$request->user()->name}\n\nเปิดเอกสารเพื่อพิจารณา:\n"
            .route('documents.show', $document->uuid ?? $document->id);
        $notifications->toUsers(collect([$document->creator])->merge($line->usersWithRoles('saraban')), $message);

        return back()->with('success', 'ส่งคำขอสิทธิ์เรียบร้อยแล้ว');
    }

    public function decide(Request $request, int $requestId, string $status, NotificationDispatcher $notifications, AuditLogger $audit, V2DocumentWriteService $v2Writes)
    {
        abort_unless(in_array($status, ['approved', 'rejected'], true), 422);
        if (config('edoc.v2.document_reads')) {
            $accessRequest = V2DocumentAccessRequest::with(['document', 'requester'])->findOrFail($requestId);
            $v2Writes->decideAccess($accessRequest, $request->user(), $status);
            return back()->with('success', $status === 'approved' ? 'อนุมัติสิทธิ์ใน V2 สำเร็จ' : 'ปฏิเสธคำขอสิทธิ์ใน V2 สำเร็จ');
        }
        $accessRequest = DocumentAccessRequest::with(['document', 'user'])->findOrFail($requestId);
        $this->authorize('update', $accessRequest);

        $old = $accessRequest->status;
        $accessRequest->update(['status' => $status]);
        $audit->log('document.access_decided', $accessRequest->document, ['status' => $old], [
            'status' => $status, 'request_user_id' => $accessRequest->user_id,
        ]);
        $notifications->toUser($accessRequest->user,
            ($status === 'approved' ? '✅ อนุมัติ' : '❌ ปฏิเสธ')."คำขอเข้าถึงเอกสารแล้ว\nเรื่อง: {$accessRequest->document->title}"
        );

        return back()->with('success', $status === 'approved' ? 'อนุมัติสิทธิ์สำเร็จ' : 'ปฏิเสธคำขอสิทธิ์สำเร็จ');
    }

    private function v2Audit(string $event, int $documentId, int $userId): void
    {
        DB::connection('mysql_v2')->table('audit_logs')->insert([
            'user_id' => $userId, 'event' => $event,
            'auditable_type' => 'document', 'auditable_id' => $documentId,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
