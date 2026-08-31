<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AuditLogger;
use App\Services\ExternalDocumentArchiver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ArchiveExternalDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 300, 900];

    public function __construct(public int $documentId)
    {
        $this->onQueue('documents');
    }

    public function handle(ExternalDocumentArchiver $archiver, AuditLogger $audit): void
    {
        $document = Document::findOrFail($this->documentId);
        if (! $document->external_url || $document->external_attachment_path) {
            return;
        }

        $document->fill($archiver->archive($document->external_url));
        $document->save();
        $audit->log('document.external_archived', $document, [], [
            'external_sha256' => $document->external_sha256,
            'external_file_size' => $document->external_file_size,
        ]);
    }

    public function failed(Throwable $error): void
    {
        Document::whereKey($this->documentId)->update([
            'external_download_error' => mb_substr($error->getMessage(), 0, 1000),
        ]);
    }
}
