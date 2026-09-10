<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecureDocumentStorageCommandTest extends TestCase
{
    public function test_it_dry_runs_without_moving_public_documents(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('public')->put('attachments/example.pdf', 'document');

        $this->artisan('edoc:secure-document-storage')->assertSuccessful();

        Storage::disk('public')->assertExists('attachments/example.pdf');
        Storage::disk('documents')->assertMissing('attachments/example.pdf');
    }

    public function test_it_moves_verified_documents_out_of_public_storage(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('public')->put('incoming_docs/example.pdf', 'document');

        $this->artisan('edoc:secure-document-storage --commit')->assertSuccessful();

        Storage::disk('public')->assertMissing('incoming_docs/example.pdf');
        Storage::disk('documents')->assertExists('incoming_docs/example.pdf');
        $this->assertSame('document', Storage::disk('documents')->get('incoming_docs/example.pdf'));
    }
}
