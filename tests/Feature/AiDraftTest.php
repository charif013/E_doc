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

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('ai.generate_draft'), ['topic' => 'ทดสอบระบบ']);

        $response->assertOk()->assertJson(['draft' => "\t\tข้อความร่างทดสอบ"]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://typhoon.test/v1/chat/completions');
    }

    public function test_ai_draft_fails_safely_when_ca_bundle_is_missing(): void
    {
        config([
            'services.typhoon.api_key' => 'test-key',
            'services.typhoon.ca_bundle' => storage_path('missing-ca.pem'),
        ]);
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create())
            ->postJson(route('ai.generate_draft'), ['topic' => 'ทดสอบระบบ'])
            ->assertStatus(500)
            ->assertJson(['error' => 'ไม่สามารถเชื่อมต่อระบบปัญญาประดิษฐ์ได้']);
    }
}
