<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentExtraction;
use App\Models\DocumentExtractionTask;
use App\Models\User;
use App\Services\DocumentExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentExtractionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_private_queued_task_and_dispatches_ocr_job(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('documents.auto_extract'), [
            'file' => UploadedFile::fake()->create('incoming.pdf', 20, 'application/pdf'),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'queued')
            ->assertJsonStructure(['task_id', 'status_url']);

        $task = DocumentExtractionTask::findOrFail($response->json('task_id'));
        $this->assertSame($user->id, $task->user_id);
        $this->assertTrue(Storage::disk('local')->exists((string) $task->file_path));
        Queue::assertPushedOn('ocr', ProcessDocumentExtraction::class);
    }

    public function test_only_task_owner_can_read_extraction_status(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = DocumentExtractionTask::create([
            'user_id' => $owner->id,
            'file_path' => 'document_extractions/test.pdf',
            'original_name' => 'test.pdf',
            'status' => 'completed',
            'result' => ['title' => 'หนังสือทดสอบ'],
        ]);

        $this->actingAs($other)
            ->getJson(route('documents.auto_extract_status', $task->id))
            ->assertForbidden();

        $this->actingAs($owner)
            ->getJson(route('documents.auto_extract_status', $task->id))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('data.title', 'หนังสือทดสอบ');
    }

    public function test_job_completes_task_and_removes_private_source_file(): void
    {
        Storage::fake('local');
        config(['services.typhoon.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'doc_number' => 'กค 1/2569',
                'doc_date' => '2026-08-31',
                'title' => 'หนังสือทดสอบ',
                'doc_from' => 'หน่วยงานทดสอบ',
            ])]]],
        ])]);

        $task = $this->makeTaskWithStoredFile();
        $service = new DocumentExtractionService(function (string $input, string $output): void {
            file_put_contents($output, json_encode(['status' => 'success', 'text' => 'document text']));
        });

        (new ProcessDocumentExtraction($task->id))->handle($service);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame('หนังสือทดสอบ', $task->result['title']);
        $this->assertFalse(Storage::disk('local')->exists((string) $task->file_path));
    }

    public function test_job_marks_failure_and_removes_private_source_file(): void
    {
        Storage::fake('local');
        $task = $this->makeTaskWithStoredFile();
        $service = new DocumentExtractionService(function (): void {
            throw new RuntimeException('private extractor diagnostic');
        });

        try {
            (new ProcessDocumentExtraction($task->id))->handle($service);
            $this->fail('The extraction job should have failed.');
        } catch (RuntimeException) {
            $task->refresh();
            $this->assertSame('failed', $task->status);
            $this->assertSame('ไม่สามารถสกัดข้อมูลเอกสารได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง', $task->error_message);
            $this->assertFalse(Storage::disk('local')->exists((string) $task->file_path));
        }
    }

    private function makeTaskWithStoredFile(): DocumentExtractionTask
    {
        $user = User::factory()->create();
        $path = 'document_extractions/test.pdf';
        Storage::disk('local')->put($path, '%PDF test');

        return DocumentExtractionTask::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'original_name' => 'test.pdf',
            'status' => 'queued',
        ]);
    }
}
