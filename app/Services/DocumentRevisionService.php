<?php

namespace App\Services;

use App\Models\Document;
use App\Models\V2\Document as V2Document;

class DocumentRevisionService
{
    public function record(Document|V2Document $document, int $actorId, ?string $note = null): void
    {
        $document->loadMissing('revisions');
        $version = ((int) $document->revisions->max('version_no')) + 1;

        $document->revisions()->create([
            'version_no' => $version,
            'snapshot' => $this->snapshot($document),
            'file_path' => $document->attachment_path,
            'change_note' => $note,
            'created_by' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function snapshot(Document|V2Document $document): array
    {
        return [
            'title' => $document->title,
            'document_number' => $document->doc_number,
            'document_date' => $document->doc_date instanceof \DateTimeInterface
                ? $document->doc_date->format('Y-m-d')
                : (string) $document->doc_date,
            'recipient' => $document->doc_to,
            'content' => $document->content,
            'signer_name' => $document->signer_name,
            'reference' => $document->reference_doc,
            'source_type' => $document->source_type,
            'sender_address' => $document->sender_address,
            'closing_text' => $document->closing_text,
            'contact_line' => $document->contact_line,
            'attachment_description' => $document->attachment_description,
            'number_status' => $document->number_status,
            'status' => $document->status,
        ];
    }
}
