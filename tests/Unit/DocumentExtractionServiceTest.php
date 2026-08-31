<?php

namespace Tests\Unit;

use App\Services\DocumentExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentExtractionServiceTest extends TestCase
{
    public function test_it_extracts_fields_and_removes_temporary_files(): void
    {
        Storage::fake('local');
        config(['services.typhoon.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '```json'.json_encode([
                'doc_number' => 'กค 1/2569', 'doc_date' => '2026-08-28',
                'title' => 'ทดสอบ', 'doc_from' => 'กรมทดสอบ',
            ]).'```']]],
        ])]);

        $before = glob(storage_path('app/temp_extract/*')) ?: [];
        $service = new DocumentExtractionService(function (string $input, string $output): void {
            file_put_contents($output, json_encode(['status' => 'success', 'text' => 'document text']));
        });
        $result = $service->extract(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'));

        $this->assertSame('ทดสอบ', $result['title']);
        $this->assertSame($before, glob(storage_path('app/temp_extract/*')) ?: []);
    }

    public function test_it_rejects_invalid_extractor_json_and_cleans_up(): void
    {
        $before = glob(storage_path('app/temp_extract/*')) ?: [];
        $service = new DocumentExtractionService(function (string $input, string $output): void {
            file_put_contents($output, 'not-json');
        });

        $this->expectException(RuntimeException::class);
        try {
            $service->extract(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'));
        } finally {
            $this->assertSame($before, glob(storage_path('app/temp_extract/*')) ?: []);
        }
    }

    public function test_it_hides_upstream_error_content(): void
    {
        config(['services.typhoon.api_key' => 'test-key']);
        Http::fake(['*' => Http::response('secret upstream diagnostic', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ระบบ AI ไม่สามารถประมวลผลเอกสารได้ในขณะนี้');
        (new DocumentExtractionService)->extractFields('document text');
    }

    public function test_it_rejects_invalid_ai_json(): void
    {
        config(['services.typhoon.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'not-json']]],
        ])]);

        $this->expectException(RuntimeException::class);
        (new DocumentExtractionService)->extractFields('document text');
    }

    public function test_it_converts_buddhist_year_to_christian_year(): void
    {
        config(['services.typhoon.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'doc_number' => 'กค 1/2569',
                'doc_date' => '2569-08-31',
                'title' => 'ทดสอบ',
                'doc_from' => 'กรมทดสอบ',
            ])]]],
        ])]);

        $result = (new DocumentExtractionService)->extractFields('document text');

        $this->assertSame('2026-08-31', $result['doc_date']);
    }
}
