<?php

namespace Tests\Unit;

use App\Services\ExternalDocumentArchiver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ExternalDocumentArchiverTest extends TestCase
{
    public function test_it_archives_a_public_document_and_records_integrity_metadata(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://93.184.216.34/document.pdf' => Http::response('%PDF-test-content', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="document.pdf"',
            ]),
        ]);

        $result = app(ExternalDocumentArchiver::class)
            ->archive('https://93.184.216.34/document.pdf');

        Storage::disk('public')->assertExists($result['external_attachment_path']);
        $this->assertSame('document.pdf', $result['external_original_name']);
        $this->assertSame('application/pdf', $result['external_mime_type']);
        $this->assertSame(hash('sha256', '%PDF-test-content'), $result['external_sha256']);
        $this->assertNotNull($result['external_downloaded_at']);
    }

    public function test_it_rejects_private_network_urls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('เครือข่ายภายใน');

        app(ExternalDocumentArchiver::class)
            ->archive('http://127.0.0.1/private.pdf');
    }

    public function test_it_does_not_archive_an_html_landing_page_as_a_document(): void
    {
        Http::fake([
            'https://93.184.216.34/share' => Http::response('<html></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('หน้าเว็บไซต์');

        app(ExternalDocumentArchiver::class)
            ->archive('https://93.184.216.34/share');
    }
}
