<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_generate_a_draft_with_configured_typhoon_client(): void
    {
        config([
            'services.typhoon.api_key' => 'test-key',
            'services.typhoon.endpoint' => 'https://typhoon.test/v1/chat/completions',
            'services.typhoon.ca_bundle' => null,
        ]);
        Http::fake([
            'typhoon.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'ข้อความร่างทดสอบ']]],
            ]),
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('ai.generate_draft'), [
                'topic' => 'ขออนุมัติดำเนินการตามแผน',
                'document_context' => [
                    'doc_from' => 'สำนักปลัด องค์การบริหารส่วนตำบลตัวอย่าง',
                    'doc_date' => '2026-10-27',
                    'title' => 'ขออนุมัติดำเนินการตามแผน',
                    'doc_to' => 'นายกองค์การบริหารส่วนตำบล',
                ],
            ]);

        $response->assertOk()->assertJson(['draft' => "\t\tข้อความร่างทดสอบ"]);
        Http::assertSent(function (Request $request) {
            $messages = $request->data()['messages'] ?? [];
            $userPrompt = $messages[1]['content'] ?? '';

            return $request->url() === 'https://typhoon.test/v1/chat/completions'
                && str_contains($userPrompt, 'ส่วนราชการ: สำนักปลัด องค์การบริหารส่วนตำบลตัวอย่าง')
                && str_contains($userPrompt, 'เรื่อง: ขออนุมัติดำเนินการตามแผน')
                && str_contains($userPrompt, 'เรียน: นายกองค์การบริหารส่วนตำบล');
        });
    }

    public function test_ai_draft_fails_safely_when_ca_bundle_is_missing(): void
    {
        config([
            'services.typhoon.api_key' => 'test-key',
            'services.typhoon.ca_bundle' => storage_path('missing-ca.pem'),
        ]);
        Http::preventStrayRequests();

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('ai.generate_draft'), ['topic' => 'ทดสอบระบบ'])
            ->assertStatus(500)
            ->assertJson(['error' => 'ไม่สามารถเชื่อมต่อระบบปัญญาประดิษฐ์ได้']);
    }

    public function test_ai_suggests_a_title_when_the_document_title_is_blank(): void
    {
        config([
            'services.typhoon.api_key' => 'test-key',
            'services.typhoon.endpoint' => 'https://typhoon.test/v1/chat/completions',
            'services.typhoon.ca_bundle' => null,
        ]);
        Http::fake([
            'typhoon.test/*' => Http::response([
                'choices' => [['message' => ['content' => '{"title":"ขออนุมัติจัดซื้อคอมพิวเตอร์","content":"มีความจำเป็นต้องจัดซื้อคอมพิวเตอร์"}']]],
            ]),
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('ai.generate_draft'), [
            'topic' => 'จัดซื้อคอมพิวเตอร์ 3 เครื่อง',
            'document_context' => ['doc_to' => 'นายกองค์การบริหารส่วนตำบล'],
        ]);

        $response->assertOk()->assertJson([
            'title' => 'ขออนุมัติจัดซื้อคอมพิวเตอร์',
            'draft' => "\t\tมีความจำเป็นต้องจัดซื้อคอมพิวเตอร์",
        ]);
    }
}
