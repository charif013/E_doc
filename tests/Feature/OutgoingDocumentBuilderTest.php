<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\DocumentNumberAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutgoingDocumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_build_an_outgoing_pdf_inside_the_system(): void
    {
        Storage::fake('documents');
        foreach (['officer', 'executive', 'palad', 'deputy-palad', 'head'] as $role) {
            Role::findOrCreate($role);
        }
        $creator = User::factory()->create([
            'name' => 'เจ้าหน้าที่ทดสอบ',
            'department' => 'สำนักปลัด',
        ])->assignRole('officer');
        $reviewer = User::factory()->create();
        $signer = User::factory()->create(['name' => 'ผู้ลงนามทดสอบ'])->assignRole('executive');

        $this->actingAs($creator)
            ->get(route('documents.create_outgoing'))
            ->assertOk()
            ->assertSee(config('edoc.organization.outgoing_address'))
            ->assertDontSee('1. ข้อมูลพื้นฐานหนังสือ')
            ->assertSee('รอธุรการลงทะเบียนเลข')
            ->assertSee('id="doc_date_picker"', false);

        $response = $this->actingAs($creator)->post(route('documents.store_outgoing'), [
            'document_source' => 'system',
            'doc_number' => 'ยล 77301/99',
            'doc_date' => '2026-09-24',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'doc_speed' => 'ปกติ',
            'doc_secret' => 'ไม่มีชั้นความลับ',
            'title' => 'ทดสอบสร้างหนังสือในระบบ',
            'doc_to' => 'ผู้ว่าราชการจังหวัดยะลา',
            'signer_name' => 'ผู้ลงนามทดสอบ — นายก อบต.พร่อน',
            'signer_user_id' => $signer->id,
            'content' => "ย่อหน้าแรกสำหรับทดสอบ\n\nย่อหน้าที่สองสำหรับทดสอบ",
            'closing_text' => 'ขอแสดงความนับถือ',
            'contact_line' => 'โทร. 0 0000 0000',
            // หน้าจอส่งผู้ลงนามมาด้วย แต่ backend ต้องไม่สร้างซ้ำและต้องคงไว้ท้ายสุด
            'routing_users' => [$reviewer->id, $signer->id],
        ]);

        $document = Document::firstOrFail();
        $response->assertRedirect(route('documents.show', $document->uuid));
        $this->assertSame("ย่อหน้าแรกสำหรับทดสอบ\n\nย่อหน้าที่สองสำหรับทดสอบ", $document->content);
        $this->assertNotNull($document->attachment_path);
        $this->assertSame('system', $document->source_type);
        $this->assertSame(config('edoc.organization.outgoing_address'), $document->sender_address);
        $this->assertNull($document->doc_number);
        $this->assertNull($document->running_number);
        $this->assertSame('UNASSIGNED', $document->number_status);
        $this->assertCount(1, $document->revisions);
        $this->assertSame(
            [$creator->id, $reviewer->id, $signer->id],
            $document->routes()->orderBy('step_order')->pluck('user_id')->all()
        );
        Storage::disk('documents')->assertExists($document->attachment_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($document->attachment_path));
        $this->actingAs($creator)
            ->get(route('documents.revision_file', [$document->uuid, 1]))
            ->assertOk();
        $this->actingAs($creator)
            ->get(route('documents.show', $document->uuid))
            ->assertOk()
            ->assertSee('ลงนามหนังสือ')
            ->assertDontSee('<div class="doc-paper', false);
    }

    public function test_outgoing_source_requires_content_or_pdf_for_the_selected_mode(): void
    {
        Role::findOrCreate('officer');
        $creator = User::factory()->create()->assignRole('officer');
        $reviewer = User::factory()->create();
        $signer = User::factory()->create(['name' => 'ผู้ลงนาม']);
        $base = [
            'doc_number' => 'ยล 77301/100',
            'doc_date' => '2026-09-24',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'title' => 'ตรวจ validation',
            'doc_to' => 'ผู้รับ',
            'signer_name' => 'ผู้ลงนาม — นายก อบต.พร่อน',
            'signer_user_id' => $signer->id,
            'routing_users' => [$reviewer->id],
        ];

        $this->actingAs($creator)->post(route('documents.store_outgoing'), $base + [
            'document_source' => 'system',
        ])->assertSessionHasErrors('content');

        $this->actingAs($creator)->post(route('documents.store_outgoing'), $base + [
            'document_source' => 'upload',
        ])->assertSessionHasErrors('file');
    }

    public function test_upload_pdf_mode_hides_the_system_content_and_signature_section(): void
    {
        foreach (['officer', 'executive', 'palad', 'deputy-palad', 'head'] as $role) {
            Role::findOrCreate($role);
        }
        $creator = User::factory()->create()->assignRole('officer');

        $this->actingAs($creator)
            ->withSession(['_old_input' => ['document_source' => 'upload']])
            ->get(route('documents.create_outgoing'))
            ->assertOk()
            ->assertSee('value="upload" checked', false)
            ->assertSee('id="systemComposeSection" style="border-radius:16px;" hidden', false)
            ->assertSee('id="upload_doc_number"', false)
            ->assertSee('id="upload_doc_date_picker"', false);
    }

    public function test_rejected_system_document_can_be_revised_and_regenerates_its_pdf(): void
    {
        Storage::fake('documents');
        Role::findOrCreate('officer');
        $creator = User::factory()->create()->assignRole('officer');
        $signer = User::factory()->create(['name' => 'ผู้ลงนาม']);
        Storage::disk('documents')->put('outgoing_docs/old.pdf', '%PDF old');
        $document = Document::create([
            'doc_type' => 'outgoing',
            'source_type' => 'system',
            'doc_number' => 'ยล 77301/101',
            'doc_date' => '2026-09-24',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'title' => 'ฉบับเดิม',
            'doc_to' => 'ผู้รับเดิม',
            'signer_name' => 'ผู้ลงนาม — นายก อบต.พร่อน',
            'content' => 'เนื้อหาเดิม',
            'attachment_path' => 'outgoing_docs/old.pdf',
            'created_by' => $creator->id,
            'status' => 'REJECTED',
            'current_step' => 1,
        ]);

        $response = $this->actingAs($creator)->put(route('documents.update', $document->uuid), [
            'document_source' => 'system',
            'doc_number' => 'ยล 77301/101',
            'doc_date' => '2026-09-25',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'doc_speed' => 'ปกติ',
            'doc_secret' => 'ปกติ',
            'title' => 'ฉบับแก้ไข',
            'doc_to' => 'ผู้รับใหม่',
            'signer_name' => 'ผู้ลงนาม — นายก อบต.พร่อน',
            'signer_user_id' => $signer->id,
            'content' => "ย่อหน้าที่แก้ไข\n\nรายละเอียดเพิ่มเติม",
            'closing_text' => 'ขอแสดงความนับถือ',
        ]);

        $response->assertRedirect(route('documents.show', $document->uuid));
        $document->refresh();
        $this->assertSame('DRAFT', $document->status);
        $this->assertSame('ฉบับแก้ไข', $document->title);
        $this->assertNull($document->doc_number);
        $this->assertSame('UNASSIGNED', $document->number_status);
        $this->assertCount(1, $document->revisions);
        $this->assertSame('แก้ไขตามข้อเสนอแนะและส่งใหม่', $document->revisions->first()->change_note);
        $this->assertSame(
            [$creator->id, $signer->id],
            $document->routes()->orderBy('step_order')->pluck('user_id')->all()
        );
        Storage::disk('documents')->assertExists('outgoing_docs/old.pdf');
        Storage::disk('documents')->assertExists($document->attachment_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($document->attachment_path));
    }

    public function test_approved_outgoing_document_is_locked_from_editing(): void
    {
        Role::findOrCreate('officer');
        $creator = User::factory()->create()->assignRole('officer');
        $document = Document::create([
            'doc_type' => 'outgoing',
            'source_type' => 'system',
            'title' => 'เอกสารอนุมัติแล้ว',
            'content' => 'เนื้อหา',
            'created_by' => $creator->id,
            'status' => 'APPROVED',
        ]);

        $this->actingAs($creator)
            ->put(route('documents.update', $document->uuid), ['title' => 'พยายามแก้ไข'])
            ->assertForbidden();
    }

    public function test_uploaded_pdf_is_kept_unchanged_while_final_signer_confirmation_is_recorded(): void
    {
        config()->set('edoc.v2.document_reads', false);
        config()->set('edoc.v2.write_enabled', false);
        Storage::fake('documents');

        Role::findOrCreate('officer');
        $creator = User::factory()->create()->assignRole('officer');
        $signer = User::factory()->create([
            'name' => 'ผู้ลงนามไฟล์อัปโหลด',
            'pin' => Hash::make('123456'),
            // การยืนยัน PDF ที่ลงนามมาแล้วใช้บัญชี + PIN และไม่บังคับภาพลายเซ็นในโปรไฟล์
            'signature' => null,
        ])->assignRole('officer');
        $originalPdf = "%PDF-1.4\nPDF already signed outside the system";
        Storage::disk('documents')->put('outgoing_docs/already-signed.pdf', $originalPdf);

        $document = Document::create([
            'doc_type' => 'outgoing',
            'source_type' => 'upload',
            'doc_number' => 'ยล 77301/102',
            'doc_date' => '2026-09-27',
            'title' => 'ทดสอบยืนยันผู้ลงนามไฟล์อัปโหลด',
            'signer_name' => 'ผู้ลงนามไฟล์อัปโหลด — นายก อบต.พร่อน',
            'attachment_path' => 'outgoing_docs/already-signed.pdf',
            'created_by' => $creator->id,
            'status' => 'WAITING_APPROVER',
            'current_step' => 2,
        ]);
        $document->routes()->create([
            'user_id' => $creator->id,
            'step_order' => 1,
            'status' => 'approved',
        ]);
        $finalRoute = $document->routes()->create([
            'user_id' => $signer->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);

        $this->actingAs($signer)
            ->get(route('documents.show', $document->uuid))
            ->assertOk()
            ->assertSee('ยืนยันว่าเป็นผู้ลงนามจริง')
            ->assertSee('ระบบจะไม่แก้ไขไฟล์');

        $this->actingAs($signer)
            ->withHeader('X-Request-ID', 'uploaded-pdf-confirmation-test')
            ->post(route('documents.review', $document->uuid), [
                'pin' => '123456',
                'is_approved' => '1',
                'comment' => 'ขอยืนยันว่าเป็นผู้ลงนามในเอกสารฉบับนี้จริง',
            ])
            ->assertRedirect(route('documents.show', $document->uuid))
            ->assertSessionHas('success', 'ยืนยันผู้ลงนามใน PDF ที่อัปโหลดและบันทึกหลักฐานไว้ในระบบเรียบร้อยแล้ว');

        $document->refresh();
        $this->assertSame('APPROVED', $document->status);
        $this->assertNull($document->signed_path);
        $this->assertSame($originalPdf, Storage::disk('documents')->get($document->attachment_path));
        $this->assertDatabaseHas('document_routes', [
            'id' => $finalRoute->id,
            'status' => 'approved',
            'comment' => 'ขอยืนยันว่าเป็นผู้ลงนามในเอกสารฉบับนี้จริง',
        ]);

        $audit = AuditLog::where('event', 'document.uploaded_pdf_signature_confirmed')
            ->where('auditable_id', $document->id)
            ->firstOrFail();
        $this->assertSame($signer->id, $audit->user_id);
        $this->assertSame($signer->id, $audit->new_values['signer_user_id']);
        $this->assertSame(hash('sha256', $originalPdf), $audit->new_values['source_file_sha256']);

        $this->actingAs($signer)
            ->get(route('documents.show', $document->uuid))
            ->assertOk()
            ->assertSee('ผู้ลงนามยืนยันแล้ว');
    }

    public function test_typing_a_reserved_number_connects_the_uploaded_document_to_the_existing_ledger_slot(): void
    {
        config()->set('edoc.v2.document_reads', false);
        config()->set('edoc.v2.write_enabled', false);
        Storage::fake('documents');
        foreach (['saraban', 'officer', 'executive', 'palad', 'deputy-palad', 'head'] as $role) {
            Role::findOrCreate($role);
        }

        $creator = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('saraban');
        $reviewer = User::factory()->create();
        $signer = User::factory()->create(['name' => 'ผู้ลงนามเลขจอง'])->assignRole('executive');

        $this->actingAs($creator)->post(route('documents.reserve_number_slot'), [
            'type' => 'outgoing',
            'running_number' => 101,
            'doc_number' => 'ยล 77301/101',
        ])->assertRedirect();

        $placeholder = Document::where('title', '[จองเลขเอกสารล่วงหน้า]')->firstOrFail();
        $this->assertSame(101, $placeholder->running_number);

        $response = $this->actingAs($creator)->post(route('documents.store_outgoing'), [
            'document_source' => 'upload',
            'doc_number' => '101',
            'doc_date' => '2026-09-28',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'title' => 'หนังสือที่ใช้เลขจอง 101',
            'doc_to' => 'หน่วยงานปลายทาง',
            'signer_name' => 'ผู้ลงนามเลขจอง — นายก อบต.พร่อน',
            'signer_user_id' => $signer->id,
            'routing_users' => [$reviewer->id],
            'file' => UploadedFile::fake()->create('reserved-101.pdf', 20, 'application/pdf'),
        ]);

        $document = Document::where('title', 'หนังสือที่ใช้เลขจอง 101')->firstOrFail();
        $response->assertRedirect(route('documents.show', $document->uuid));
        $this->assertSame(101, $document->running_number);
        $this->assertSame('ยล ๗๗๓๐๑/๑๐๑', $document->doc_number);
        $this->assertDatabaseMissing('documents', ['id' => $placeholder->id]);
        $this->assertDatabaseHas('document_number_allocations', [
            'number_type' => 'outgoing',
            'running_number' => 101,
            'document_id' => $document->id,
        ]);
        $this->assertSame(1, DocumentNumberAllocation::where('number_type', 'outgoing')
            ->where('running_number', 101)->count());

        $this->actingAs($creator)
            ->get(route('documents.number_ledger', ['type' => 'outgoing']))
            ->assertOk()
            ->assertSee('หนังสือที่ใช้เลขจอง ๑๐๑')
            ->assertSee('ยล ๗๗๓๐๑/๑๐๑');
    }

    public function test_system_outgoing_is_numbered_only_after_the_final_signer_approves(): void
    {
        config()->set('edoc.v2.document_reads', false);
        config()->set('edoc.v2.write_enabled', false);
        Storage::fake('documents');
        foreach (['officer', 'saraban', 'executive'] as $role) {
            Role::findOrCreate($role);
        }
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $creator = User::factory()->create([
            'name' => 'ผู้จัดทำ', 'pin' => Hash::make('123456'), 'signature' => $signature,
        ])->assignRole('officer');
        $reviewer = User::factory()->create([
            'name' => 'ผู้ตรวจ', 'pin' => Hash::make('123456'), 'signature' => $signature,
        ])->assignRole('officer');
        $signer = User::factory()->create([
            'name' => 'ผู้ลงนามสุดท้าย', 'pin' => Hash::make('123456'), 'signature' => $signature,
        ])->assignRole('executive');
        $saraban = User::factory()->create([
            'name' => 'เจ้าหน้าที่สารบรรณ', 'pin' => Hash::make('123456'), 'signature' => null,
        ])->assignRole('saraban');

        $this->actingAs($creator)->post(route('documents.store_outgoing'), [
            'document_source' => 'system',
            // แม้ client ส่งเลขมา backend ต้องไม่รับเลขก่อนอนุมัติ
            'doc_number' => 'ยล 77301/77', 'running_number' => 77,
            'doc_date' => '2026-09-28',
            'doc_type_category' => 'หนังสือภายนอก (กระดาษตราครุฑ)',
            'title' => 'ทดสอบลงเลขหลังลงนาม', 'doc_to' => 'หน่วยงานปลายทาง',
            'signer_name' => 'ผู้ลงนามสุดท้าย — นายก อบต.พร่อน',
            'signer_user_id' => $signer->id,
            'content' => 'เนื้อหาสำหรับทดสอบ',
            'routing_users' => [$reviewer->id],
        ])->assertRedirect();

        $document = Document::where('title', 'ทดสอบลงเลขหลังลงนาม')->firstOrFail();
        $this->assertNull($document->doc_number);
        $this->actingAs($creator)->post(route('documents.sign', $document->uuid), ['pin' => '123456'])->assertRedirect();
        $this->actingAs($reviewer)->post(route('documents.review', $document->uuid), [
            'pin' => '123456', 'is_approved' => '1', 'comment' => 'ตรวจแล้ว',
        ])->assertRedirect();
        $this->actingAs($signer)->post(route('documents.review', $document->uuid), [
            'pin' => '123456', 'is_approved' => '1', 'comment' => 'อนุมัติและลงนาม',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame('WAITING_NUMBERING', $document->status);
        $this->assertSame('UNASSIGNED', $document->number_status);
        $this->assertNull($document->doc_number);
        $this->assertNull($document->signed_path);

        $this->actingAs($saraban)->post(route('documents.assign_number', $document->uuid), [
            'doc_number' => 'ยล ๗๗๓๐๑/๗๗', 'running_number' => 77,
        ])->assertRedirect()->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('APPROVED', $document->status);
        $this->assertSame('CONFIRMED', $document->number_status);
        $this->assertSame('ยล ๗๗๓๐๑/๗๗', $document->doc_number);
        $this->assertNotNull($document->signed_path);
        Storage::disk('documents')->assertExists($document->signed_path);
    }
}
