<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unauthorized',
            'email' => 'unauthorized@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertNotFound();
    }

    public function test_private_document_file_requires_document_authorization(): void
    {
        Storage::fake('documents');
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $other = User::factory()->create()->assignRole('officer');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'Private file', 'status' => 'DRAFT',
            'created_by' => $owner->id, 'attachment_path' => 'attachments/private.pdf',
        ]);
        Storage::disk('documents')->put($document->attachment_path, '%PDF-private');

        $url = route('documents.file', [$document->uuid, 'main']);
        $this->actingAs($other)->get($url)->assertForbidden();
        $response = $this->actingAs($owner)->get($url)->assertOk();
        $this->assertSame('%PDF-private', $response->streamedContent());
    }

    public function test_confidential_file_requires_recent_pin_unlock(): void
    {
        Storage::fake('documents');
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'Confidential file', 'status' => 'DRAFT',
            'doc_secret' => 'ลับ', 'created_by' => $owner->id,
            'attachment_path' => 'attachments/confidential.pdf',
        ]);
        Storage::disk('documents')->put($document->attachment_path, '%PDF-secret');
        $url = route('documents.file', [$document->uuid, 'main']);

        $this->actingAs($owner)->get($url)->assertForbidden();
        $response = $this->actingAs($owner)
            ->withSession(['secret_unlocked_'.$document->id => now()->timestamp])
            ->get($url)->assertOk();
        $this->assertSame('%PDF-secret', $response->streamedContent());
    }
}
