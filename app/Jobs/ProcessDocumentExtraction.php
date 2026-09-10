<?php

namespace App\Jobs;

use App\Models\DocumentExtractionTask;
use App\Services\DocumentExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 720;

    public bool $failOnTimeout = true;

    public string $taskId;

    public function __construct(string $taskId)
    {
        $this->taskId = $taskId;
        $this->onQueue('ocr');
    }

    public function handle(DocumentExtractionService $extraction): void
    {
        $task = DocumentExtractionTask::find($this->taskId);
        if (! $task || $task->status !== 'queued') {
            return;
        }

        $task->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $filePath = (string) $task->file_path;

        try {
            $result = $extraction->extractPath(
                $disk->path($filePath),
                pathinfo($filePath, PATHINFO_EXTENSION)
            );

            $task->update([
                'status' => 'completed',
                'result' => $result,
                'finished_at' => now(),
            ]);
        } catch (Throwable $error) {
            report($error);
            $task->update([
                'status' => 'failed',
                'error_message' => 'ไม่สามารถสกัดข้อมูลเอกสารได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
                'finished_at' => now(),
            ]);

            throw $error;
        } finally {
            $disk->delete($filePath);
        }
    }

    public function failed(Throwable $error): void
    {
        $task = DocumentExtractionTask::find($this->taskId);
        if (! $task) {
            return;
        }

        Storage::disk('local')->delete($task->file_path);
        $task->update([
            'status' => 'failed',
            'error_message' => 'ไม่สามารถสกัดข้อมูลเอกสารได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            'finished_at' => now(),
        ]);
    }
}
