<?php

namespace App\Jobs;

use App\Models\V2\DocumentFile;
use App\Services\ExternalDocumentArchiver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ArchiveV2ExternalDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 300, 900];

    public function __construct(public int $documentFileId)
    {
        $this->onQueue('documents');
    }

    public function handle(ExternalDocumentArchiver $archiver): void
    {
        $file = DocumentFile::findOrFail($this->documentFileId);
        if (! $file->external_url || $file->file_path) {
            return;
        }

        try {
            $archived = $archiver->archive($file->external_url);
            $file->update([
                'file_path' => $archived['external_attachment_path'],
                'original_name' => $archived['external_original_name'],
                'mime_type' => $archived['external_mime_type'],
                'file_size' => $archived['external_file_size'],
                'sha256' => $archived['external_sha256'],
                'downloaded_at' => $archived['external_downloaded_at'],
                'download_error' => null,
            ]);
        } catch (Throwable $error) {
            $file->update(['download_error' => $this->friendlyError($error)]);
            throw $error;
        }
    }

    public function failed(Throwable $error): void
    {
        DocumentFile::whereKey($this->documentFileId)->update([
            'download_error' => $this->friendlyError($error),
        ]);
    }

    private function friendlyError(Throwable $error): string
    {
        $message = $error->getMessage();
        if (str_contains(strtolower($message), 'connection refused') || str_contains(strtolower($message), 'curl error')) {
            return 'เว็บไซต์ปลายทางปฏิเสธการดาวน์โหลดอัตโนมัติ กรุณาเปิดลิงก์ต้นฉบับแล้วดาวน์โหลดไฟล์มาแนบในระบบ';
        }

        return mb_substr($message, 0, 1000);
    }
}
