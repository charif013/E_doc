<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LineMessagingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineMessagingServiceTest extends TestCase
{
    public function test_it_pushes_a_message_to_a_connected_line_account(): void
    {
        config(['services.line.messaging_token' => 'test-token']);
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $user = new User(['name' => 'ผู้ทดสอบ']);
        $user->id = 10;
        $user->line_id = 'U' . str_repeat('a', 32);

        $sent = app(LineMessagingService::class)->sendToUser($user, 'ข้อความทดสอบ');

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.line.me/v2/bot/message/push'
            && $request['to'] === $user->line_id
            && $request['messages'][0]['text'] === 'ข้อความทดสอบ'
            && $request->hasHeader('Authorization', 'Bearer test-token')
        );
    }

    public function test_it_skips_users_who_have_not_connected_line(): void
    {
        config(['services.line.messaging_token' => 'test-token']);
        Http::fake();

        $user = new User(['name' => 'ผู้ทดสอบ']);

        $this->assertFalse(app(LineMessagingService::class)->sendToUser($user, 'ข้อความทดสอบ'));
        Http::assertNothingSent();
    }
}
