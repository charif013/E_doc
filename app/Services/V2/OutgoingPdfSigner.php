<?php

namespace App\Services\V2;

use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use App\Models\V2\DocumentFile;
use App\Services\DocumentFileStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OutgoingPdfSigner
{
    public function __construct(private DocumentFileStorage $files) {}

    public function stamp(Document $document, LegacyUser $signer): bool
    {
        if ($document->doc_type !== 'outgoing' || ! $document->attachment_path || ! $signer->signature) {
            return false;
        }
        if (! $this->files->exists($document->attachment_path)) {
            return false;
        }

        $token = (string) Str::uuid();
        $tempDirectory = storage_path('app/temp/v2-outgoing-signatures');
        File::ensureDirectoryExists($tempDirectory);
        $signaturePath = $tempDirectory.'/signature-'.$token.'.png';
        $signedStoragePath = 'documents/signed/v2-'.$document->id.'-'.$token.'.pdf';
        $signedAbsolutePath = Storage::disk('documents')->path($signedStoragePath);
        File::ensureDirectoryExists(dirname($signedAbsolutePath));

        try {
            if ($document->source_type === 'system') {
                Pdf::loadView('documents.pdf_outgoing', [
                    'document' => $document,
                    'signatureData' => (string) $signer->signature,
                ])->setOption(['isRemoteEnabled' => true])->save($signedAbsolutePath);
            } else {
                $this->writeBase64Signature((string) $signer->signature, $signaturePath);
                $result = $this->runStamper([
                    config('services.docling.python', 'python'),
                    base_path('scripts/stamper.py'),
                    $this->files->path($document->attachment_path),
                    $signaturePath,
                    $signedAbsolutePath,
                ]);
                if (($result['status'] ?? null) !== 'success') {
                    throw new RuntimeException($result['message'] ?? 'ไม่สามารถประทับลายเซ็นลงใน PDF ได้');
                }
            }
            if (! File::exists($signedAbsolutePath)) {
                throw new RuntimeException('ไม่พบ PDF ฉบับลงนามหลังประมวลผล');
            }

            $oldSignedFiles = $document->files()->where('file_type', 'SIGNED')->get();
            $version = ((int) $oldSignedFiles->max('version_no')) + 1;
            $record = DocumentFile::create([
                'document_id' => $document->id,
                'file_type' => 'SIGNED',
                'original_name' => 'signed-'.basename((string) $document->attachment_path),
                'file_path' => $signedStoragePath,
                'mime_type' => 'application/pdf',
                'file_size' => File::size($signedAbsolutePath),
                'sha256' => hash_file('sha256', $signedAbsolutePath),
                'version_no' => $version,
                'uploaded_by' => $signer->id,
            ]);

            foreach ($oldSignedFiles as $oldFile) {
                $this->files->delete($oldFile->file_path);
                $oldFile->delete();
            }

            return $record->exists;
        } catch (\Throwable $error) {
            $this->files->delete($signedStoragePath);
            report($error);

            return false;
        } finally {
            File::delete($signaturePath);
        }
    }

    private function writeBase64Signature(string $signature, string $path): void
    {
        $encoded = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $signature);
        $binary = base64_decode((string) $encoded, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('ข้อมูลลายเซ็นไม่ถูกต้อง');
        }
        if (File::put($path, $binary) === false) {
            throw new RuntimeException('ไม่สามารถสร้างไฟล์ลายเซ็นชั่วคราวได้');
        }
    }

    /**
     * Symfony Process ตรวจ is_dir(getcwd()) ไม่ผ่านบน PHP/Windows บางชุด แม้ไฟล์ใช้งานได้จริง
     * จึงเรียก proc_open ด้วย argument array เพื่อไม่ผ่าน shell และไม่ต้องตรวจ working directory
     */
    private function runStamper(array $command): array
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('ไม่สามารถเริ่มโปรแกรมประทับลายเซ็นได้');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim((string) $stderr) ?: 'โปรแกรมประทับลายเซ็นทำงานไม่สำเร็จ');
        }

        $result = json_decode((string) $stdout, true);
        if (! is_array($result)) {
            throw new RuntimeException('ผลลัพธ์จากโปรแกรมประทับลายเซ็นไม่ถูกต้อง');
        }

        return $result;
    }
}
