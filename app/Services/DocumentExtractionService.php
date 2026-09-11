<?php

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DocumentExtractionService
{
    public function __construct(private ?Closure $processRunner = null) {}

    public function extract(UploadedFile $file): array
    {
        return $this->extractPath($file->getRealPath(), $file->extension());
    }

    public function extractPath(string $sourcePath, ?string $extension = null): array
    {
        $directory = storage_path('app/temp_extract');
        File::ensureDirectoryExists($directory);
        $extension = $extension ?: pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin';
        $inputPath = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
        $outputPath = $inputPath.'.json';

        if (! is_file($sourcePath) || ! File::copy($sourcePath, $inputPath)) {
            throw new RuntimeException('ไม่สามารถเตรียมไฟล์สำหรับสกัดข้อมูลได้');
        }

        try {
            $this->runExtractor($inputPath, $outputPath);

            if (! is_file($outputPath)) {
                throw new RuntimeException('ระบบสกัดเอกสารไม่สร้างไฟล์ผลลัพธ์');
            }

            $result = json_decode((string) file_get_contents($outputPath), true);
            if (! is_array($result)) {
                throw new RuntimeException('รูปแบบผลลัพธ์จากระบบสกัดเอกสารไม่ถูกต้อง');
            }
            if (($result['status'] ?? null) === 'error') {
                throw new RuntimeException('ระบบสกัดเอกสารไม่สามารถอ่านข้อความได้');
            }

            return $this->extractFields((string) ($result['text'] ?? ''));
        } finally {
            File::delete([$inputPath, $outputPath]);
        }
    }

    public function extractFields(string $text): array
    {
        $apiKey = config('services.typhoon.api_key');
        if (! $apiKey) {
            throw new RuntimeException('ระบบ AI ยังไม่ได้รับการตั้งค่า');
        }

        $typhoon = app(TyphoonClient::class);
        $response = $typhoon->request($apiKey)
            ->post($typhoon->endpoint(), [
                'model' => config('services.typhoon.model'),
                'messages' => [[
                    'role' => 'user',
                    'content' => "ดึงข้อมูลเอกสารราชการต่อไปนี้และตอบเป็น JSON เท่านั้น โดยมีคีย์ doc_number, doc_date (YYYY-MM-DD), title และ doc_from\n\n{$text}",
                ]],
                'temperature' => 0.1,
            ]);

        if (! $response->successful()) {
            report(new RuntimeException('Typhoon API returned HTTP '.$response->status()));
            throw new RuntimeException('ระบบ AI ไม่สามารถประมวลผลเอกสารได้ในขณะนี้');
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $content = preg_replace('/```(?:json)?\s*(.*?)\s*```/is', '$1', $content) ?? '';
        $data = json_decode(trim($content), true);
        if (! is_array($data)) {
            throw new RuntimeException('ระบบ AI ส่งผลลัพธ์ในรูปแบบที่ไม่ถูกต้อง');
        }

        $keys = ['doc_number', 'doc_date', 'title', 'doc_from'];

        $fields = collect($keys)->mapWithKeys(fn ($key) => [$key => (string) ($data[$key] ?? '')])->all();
        $fields['doc_date'] = $this->normaliseDocumentDate($fields['doc_date']);

        return $fields;
    }

    private function normaliseDocumentDate(string $date): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $matches)) {
            return '';
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if ($year >= 2400) {
            $year -= 543;
        }

        return checkdate($month, $day, $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : '';
    }

    private function runExtractor(string $inputPath, string $outputPath): void
    {
        if ($this->processRunner) {
            ($this->processRunner)($inputPath, $outputPath);

            return;
        }

        $script = base_path((string) config('services.document_extraction.script', 'extract_doc.py'));
        if (! is_file($script)) {
            throw new RuntimeException('ไม่พบโปรแกรมสกัดข้อมูลเอกสาร');
        }

        $process = new Process([
            (string) config('services.document_extraction.python', 'python'),
            $script,
            $inputPath,
            $outputPath,
        ], sys_get_temp_dir());
        $process->setTimeout((int) config('services.document_extraction.timeout', 600));
        $process->mustRun();
    }
}
