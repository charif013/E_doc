<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\V2\DocumentReadService as V2DocumentReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return back()->with('error_pin', 'กรอก PIN ผิดเกินกำหนด กรุณารอสักครู่');
            }
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
