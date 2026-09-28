<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class OutgoingDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use UsesV2Database;

    public function test_approved_legacy_outgoing_document_can_record_and_track_delivery(): void
    {
        Storage::fake('documents');
        $creator = User::factory()->create();
        $document = Document::create([
            'doc_type' => 'outgoing',
            'title' => 'หนังสือพร้อมส่ง',
            'doc_to' => 'สำนักงานจังหวัดยะลา',
            'status' => 'APPROVED',
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($creator)->post(route('documents.deliveries.store', $document->uuid), [
            'channel' => 'POST',
            'recipient_name' => 'สำนักงานจังหวัดยะลา',
            'recipient_address' => 'จังหวัดยะลา',
            'tracking_number' => 'TH123456789',
            'status' => 'SENT',
            'sent_at' => '2026-09-25T10:30',
            'evidence' => UploadedFile::fake()->create('postal-receipt.pdf', 20, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $delivery = $document->deliveries()->firstOrFail();
        $this->assertSame('SENT', $delivery->status);
        $this->assertSame('TH123456789', $delivery->tracking_number);
        Storage::disk('documents')->assertExists($delivery->evidence_path);

        $this->actingAs($creator)
            ->get(route('documents.deliveries.evidence', [$document->uuid, $delivery->id]))
            ->assertOk();

        $this->actingAs($creator)->patch(route('documents.deliveries.update', [$document->uuid, $delivery->id]), [
            'status' => 'RECEIVED',
            'received_at' => '2026-09-26T09:00',
            'tracking_number' => 'TH123456789',
            'note' => 'ผู้รับลงนามรับแล้ว',
        ])->assertRedirect();

        $this->assertSame('RECEIVED', $delivery->refresh()->status);
        $this->assertNotNull($delivery->received_at);

        $this->actingAs($creator)
            ->get(route('documents.show', $document->uuid))
            ->assertOk()
            ->assertSee('การส่งและติดตามหนังสือออก')
            ->assertSee('เลือกหลักฐาน')
            ->assertSee('ยังไม่ได้เลือกไฟล์')
            ->assertSee('วันและเวลาที่ได้รับ')
            ->assertSee('TH123456789');
    }

    public function test_v2_outgoing_document_uses_the_same_delivery_workflow(): void
    {
        Storage::fake('documents');
        $this->bootV2Database();
        config(['edoc.v2.document_reads' => true, 'edoc.v2.write_enabled' => true]);

        $creator = User::factory()->create();
        $this->seedV2User($creator->id);
        $db = DB::connection('mysql_v2');
        $typeId = $db->table('document_types')->insertGetId([
            'code' => 'OUTGOING', 'name' => 'Outgoing', 'direction' => 'OUTGOING',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $uuid = '10000000-0000-4000-8000-000000000001';
        $documentId = $db->table('documents')->insertGetId([
            'uuid' => $uuid,
            'document_type_id' => $typeId,
            'title' => 'V2 outgoing delivery',
            'recipient_name' => 'หน่วยงานภายนอก',
            'status' => 'COMPLETED',
            'created_by' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($creator)->post(route('documents.deliveries.store', $uuid), [
            'channel' => 'E_DOC',
            'recipient_name' => 'หน่วยงานภายนอก',
            'tracking_number' => 'EDOC-2026-001',
            'status' => 'SENT',
            'sent_at' => '2026-09-25T11:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('outgoing_deliveries', [
            'document_id' => $documentId,
            'channel' => 'E_DOC',
            'tracking_number' => 'EDOC-2026-001',
            'status' => 'SENT',
        ], 'mysql_v2');

        $this->actingAs($creator)
            ->get(route('documents.show', $uuid))
            ->assertOk()
            ->assertSee('การส่งและติดตามหนังสือออก')
            ->assertSee('EDOC-2026-001');
    }

    public function test_draft_outgoing_document_cannot_record_delivery(): void
    {
        $creator = User::factory()->create();
        $document = Document::create([
            'doc_type' => 'outgoing',
            'title' => 'ฉบับร่าง',
            'status' => 'DRAFT',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->post(route('documents.deliveries.store', $document->uuid), [
            'channel' => 'EMAIL',
            'recipient_name' => 'ผู้รับ',
            'status' => 'SENT',
        ])->assertForbidden();
    }
}
