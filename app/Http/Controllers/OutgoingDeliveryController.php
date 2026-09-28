<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\DocumentFileStorage;
use App\Services\V2\DocumentReadService as V2DocumentReadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutgoingDeliveryController extends Controller
{
    private const CHANNELS = ['EMAIL', 'POST', 'COURIER', 'HAND', 'E_DOC', 'LINE', 'OTHER'];
    private const STATUSES = ['PENDING', 'SENT', 'RECEIVED', 'FAILED'];

    public function store(Request $request, string|int $id, V2DocumentReadService $v2Documents)
    {
        $document = $this->findDocument($id, $v2Documents);
        $this->authorize('dispatch', $document);

        $data = $request->validate([
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'recipient_name' => ['required', 'string', 'max:500'],
            'recipient_address' => ['nullable', 'string', 'max:2000'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'sent_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date', 'after_or_equal:sent_at'],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        unset($data['evidence']);

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = app(DocumentFileStorage::class)->store(
                $request->file('evidence'),
                'outgoing_delivery_evidence'
            );
        }

        try {
            $delivery = $document->deliveries()->create([
                ...$data,
                'sent_at' => in_array($data['status'], ['SENT', 'RECEIVED'], true)
                    ? ($data['sent_at'] ?? now())
                    : ($data['sent_at'] ?? null),
                'received_at' => $data['status'] === 'RECEIVED'
                    ? ($data['received_at'] ?? now())
                    : null,
                'evidence_path' => $evidencePath,
                'sent_by' => $request->user()->id,
            ]);
        } catch (\Throwable $error) {
            app(DocumentFileStorage::class)->delete($evidencePath);
            throw $error;
        }

        app(AuditLogger::class)->log('document.outgoing_delivery_created', $document, [], [
            'delivery_id' => $delivery->id,
            'channel' => $delivery->channel,
            'recipient_name' => $delivery->recipient_name,
            'status' => $delivery->status,
        ]);

        return back()->with('success', 'บันทึกการส่งหนังสือออกเรียบร้อยแล้ว');
    }

    public function update(Request $request, string|int $id, int $delivery, V2DocumentReadService $v2Documents)
    {
        $document = $this->findDocument($id, $v2Documents);
        $this->authorize('dispatch', $document);
        $record = $document->deliveries()->whereKey($delivery)->firstOrFail();

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'sent_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date', 'after_or_equal:sent_at'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        unset($data['evidence']);

        $old = $record->only(['status', 'sent_at', 'received_at', 'tracking_number', 'note', 'evidence_path']);
        $newEvidencePath = null;
        if ($request->hasFile('evidence')) {
            $newEvidencePath = app(DocumentFileStorage::class)->store(
                $request->file('evidence'),
                'outgoing_delivery_evidence'
            );
        }

        try {
            $record->update([
                ...$data,
                'sent_at' => in_array($data['status'], ['SENT', 'RECEIVED'], true)
                    ? ($data['sent_at'] ?? $record->sent_at ?? now())
                    : ($data['sent_at'] ?? $record->sent_at),
                'received_at' => $data['status'] === 'RECEIVED'
                    ? ($data['received_at'] ?? $record->received_at ?? now())
                    : null,
                'evidence_path' => $newEvidencePath ?: $record->evidence_path,
            ]);
        } catch (\Throwable $error) {
            app(DocumentFileStorage::class)->delete($newEvidencePath);
            throw $error;
        }

        if ($newEvidencePath && $old['evidence_path'] && $old['evidence_path'] !== $newEvidencePath) {
            app(DocumentFileStorage::class)->delete($old['evidence_path']);
        }

        app(AuditLogger::class)->log('document.outgoing_delivery_updated', $document, $old, [
            ...$record->only(['status', 'sent_at', 'received_at', 'tracking_number', 'note', 'evidence_path']),
            'delivery_id' => $record->id,
        ]);

        return back()->with('success', 'อัปเดตสถานะการส่งเรียบร้อยแล้ว');
    }

    public function evidence(string|int $id, int $delivery, V2DocumentReadService $v2Documents)
    {
        $document = $this->findDocument($id, $v2Documents);
        $this->authorize('accessConfidential', $document);
        $this->ensureConfidentialDocumentIsUnlocked($document);
        $record = $document->deliveries()->whereKey($delivery)->firstOrFail();
        abort_unless($record->evidence_path, 404);

        app(AuditLogger::class)->log('document.outgoing_delivery_evidence_viewed', $document, [], [
            'delivery_id' => $record->id,
        ]);

        return app(DocumentFileStorage::class)->response(
            (string) $record->evidence_path,
            basename((string) $record->evidence_path),
            ['Content-Disposition' => 'inline']
        );
    }

    private function findDocument(string|int $id, V2DocumentReadService $v2Documents)
    {
        return config('edoc.v2.document_reads')
            ? $v2Documents->find($id)
            : Document::whereIdentifier($id)->firstOrFail();
    }

    private function ensureConfidentialDocumentIsUnlocked($document): void
    {
        $isConfidential = $document instanceof \App\Models\V2\Document
            ? ($document->confidentiality?->level_no ?? 0) > 1
            : in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'], true);
        if (! $isConfidential) {
            return;
        }

        $unlockedAt = session('secret_unlocked_'.$document->id);
        abort_unless($unlockedAt && (now()->timestamp - $unlockedAt) <= 180, 403, 'กรุณายืนยัน PIN เพื่อเปิดเอกสารลับ');
    }
}
