<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistryUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_searches_receive_number_and_keeps_selected_tab(): void
    {
        Role::findOrCreate('saraban');
        $saraban = User::factory()->create()->assignRole('saraban');

        Document::create([
            'doc_type' => 'incoming',
            'receive_number' => 'รับ-ทดสอบ-2569-007',
            'receive_date' => now(),
            'doc_from' => 'หน่วยงานทดสอบ',
            'title' => 'หนังสือที่ค้นจากเลขรับ',
            'status' => 'APPROVED',
            'created_by' => $saraban->id,
        ]);

        $this->actingAs($saraban)->get(route('documents.registry', [
            'search' => 'รับ-ทดสอบ-2569-007',
            'year' => now()->year,
            'tab' => 'incoming',
        ]))
            ->assertOk()
            ->assertSee('หนังสือที่ค้นจากเลขรับ')
            ->assertSee('value="incoming"', false)
            ->assertSee('id="incoming-tab"', false)
            ->assertSee('show active', false);
    }

    public function test_internal_registry_number_uses_a_readable_text_color(): void
    {
        Role::findOrCreate('saraban');
        $saraban = User::factory()->create()->assignRole('saraban');

        Document::create([
            'doc_type' => 'internal',
            'title' => 'บันทึกข้อความทดสอบสีตัวอักษร',
            'status' => 'APPROVED',
            'running_number' => 1,
            'created_by' => $saraban->id,
        ]);

        $this->actingAs($saraban)->get(route('documents.registry', [
            'year' => now()->year,
            'tab' => 'internal',
        ]))
            ->assertOk()
            ->assertSee('registry-internal-number', false)
            ->assertSee('color: #5b21b6 !important', false);
    }
}
