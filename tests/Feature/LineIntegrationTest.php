<?php

namespace Tests\Feature;

use App\Jobs\SendLineNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_login_requests_the_add_friend_screen(): void
    {
        config([
            'services.line.login_channel_id' => '1234567890',
            'services.line.redirect_uri' => 'https://edoc.example/line/callback',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('line.login'));

        $response->assertRedirectContains('https://access.line.me/oauth2/v2.1/authorize?');
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('aggressive', $query['bot_prompt']);
        $this->assertSame('profile openid', $query['scope']);
        $this->assertNotEmpty(session('line_oauth_state'));
    }

    public function test_callback_marks_a_friend_as_ready_for_notifications(): void
    {
        Queue::fake();
        config([
            'services.line.login_channel_id' => '1234567890',
            'services.line.login_secret' => 'login-secret',
            'services.line.redirect_uri' => 'https://edoc.example/line/callback',
        ]);
        $lineId = 'U'.str_repeat('a', 32);
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'access-token']),
            'api.line.me/v2/profile' => Http::response(['userId' => $lineId]),
            'api.line.me/friendship/v1/status' => Http::response(['friendFlag' => true]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['line_oauth_state' => 'expected-state'])
            ->get(route('line.callback', ['state' => 'expected-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('profile.index'))->assertSessionHas('success');
        $user->refresh();
        $this->assertSame($lineId, $user->line_id);
        $this->assertTrue($user->line_friend_status);
        $this->assertNotNull($user->line_connected_at);
        Queue::assertPushed(SendLineNotification::class);
    }

    public function test_callback_keeps_notifications_disabled_when_user_skips_add_friend(): void
    {
        Queue::fake();
        config([
            'services.line.login_channel_id' => '1234567890',
            'services.line.login_secret' => 'login-secret',
            'services.line.redirect_uri' => 'https://edoc.example/line/callback',
        ]);
        $lineId = 'U'.str_repeat('b', 32);
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'access-token']),
            'api.line.me/v2/profile' => Http::response(['userId' => $lineId]),
            'api.line.me/friendship/v1/status' => Http::response(['friendFlag' => false]),
            'api.line.me/v2/bot/profile/*' => Http::response([], 404),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['line_oauth_state' => 'expected-state'])
            ->get(route('line.callback', ['state' => 'expected-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('profile.index'))->assertSessionHas('warning');
        $this->assertFalse($user->refresh()->line_friend_status);
        Queue::assertNothingPushed();
    }

    public function test_callback_uses_bot_profile_as_a_fallback_when_friendship_status_is_stale(): void
    {
        Queue::fake();
        config([
            'services.line.login_channel_id' => '1234567890',
            'services.line.login_secret' => 'login-secret',
            'services.line.messaging_token' => 'bot-token',
            'services.line.redirect_uri' => 'https://edoc.example/line/callback',
        ]);
        $lineId = 'U'.str_repeat('d', 32);
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'access-token']),
            'api.line.me/v2/profile' => Http::response(['userId' => $lineId]),
            'api.line.me/friendship/v1/status' => Http::response(['friendFlag' => false]),
            'api.line.me/v2/bot/profile/*' => Http::response(['userId' => $lineId]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['line_oauth_state' => 'expected-state'])
            ->get(route('line.callback', ['state' => 'expected-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('profile.index'))->assertSessionHas('success');
        $this->assertTrue($user->refresh()->line_friend_status);
        Queue::assertPushed(SendLineNotification::class);
    }

    public function test_user_can_refresh_line_status_after_adding_the_official_account(): void
    {
        Queue::fake();
        config(['services.line.messaging_token' => 'bot-token']);
        $lineId = 'U'.str_repeat('e', 32);
        Http::fake([
            'api.line.me/v2/bot/profile/*' => Http::response(['userId' => $lineId]),
        ]);
        $user = User::factory()->create([
            'line_id' => $lineId,
            'line_friend_status' => false,
        ]);

        $this->actingAs($user)->post(route('line.refresh_status'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->refresh()->line_friend_status);
        Queue::assertPushed(SendLineNotification::class);
    }

    public function test_signed_webhooks_update_follow_and_unfollow_status(): void
    {
        config(['services.line.messaging_secret' => 'webhook-secret']);
        $lineId = 'U'.str_repeat('c', 32);
        $user = User::factory()->create([
            'line_id' => $lineId,
            'line_friend_status' => false,
        ]);

        $this->postSignedWebhook('follow', $lineId)->assertOk();
        $this->assertTrue($user->refresh()->line_friend_status);

        $this->postSignedWebhook('unfollow', $lineId)->assertOk();
        $this->assertFalse($user->refresh()->line_friend_status);
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.line.messaging_secret' => 'webhook-secret']);

        $this->call('POST', route('line.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => 'invalid',
        ], '{"events":[]}')->assertUnauthorized();
    }

    private function postSignedWebhook(string $type, string $lineId)
    {
        $body = json_encode([
            'events' => [[
                'type' => $type,
                'source' => ['type' => 'user', 'userId' => $lineId],
            ]],
        ], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $body, 'webhook-secret', true));

        return $this->call('POST', route('line.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body);
    }
}
