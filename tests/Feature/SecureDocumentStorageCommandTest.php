<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecureDocumentStorageCommandTest extends TestCase
{
    public function test_it_dry_runs_without_moving_public_documents(): void
    {
        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::fake('public');
        /** @var FilesystemAdapter $documentsDisk */
        $documentsDisk = Storage::fake('documents');
        $publicDisk->put('attachments/example.pdf', 'document');

        $this->artisan('edoc:secure-document-storage')->assertSuccessful();

        $publicDisk->assertExists('attachments/example.pdf');
        $documentsDisk->assertMissing('attachments/example.pdf');
    }

    public function test_it_moves_verified_documents_out_of_public_storage(): void
    {
        /** @var FilesystemAdapter $publicDisk */
        $publicDisk = Storage::fake('public');
        /** @var FilesystemAdapter $documentsDisk */
        $documentsDisk = Storage::fake('documents');
        $publicDisk->put('incoming_docs/example.pdf', 'document');

        $this->artisan('edoc:secure-document-storage --commit')->assertSuccessful();

        $publicDisk->assertMissing('incoming_docs/example.pdf');
        $documentsDisk->assertExists('incoming_docs/example.pdf');
        $this->assertSame('document', $documentsDisk->get('incoming_docs/example.pdf'));
    }
}
