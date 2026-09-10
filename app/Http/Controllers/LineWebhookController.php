<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.line.messaging_secret');
        if ($secret === '') {
            Log::error('LINE webhook rejected: LINE_BOT_CHANNEL_SECRET is not configured.');

            return response('LINE webhook is not configured.', 503);
        }

        $signature = (string) $request->header('X-Line-Signature', '');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return response('Invalid signature.', 401);
        }

        foreach ((array) $request->input('events', []) as $event) {
            $type = data_get($event, 'type');
            $lineId = data_get($event, 'source.userId');
            if (!in_array($type, ['follow', 'unfollow'], true) || !is_string($lineId)) {
                continue;
            }

            $isFriend = $type === 'follow';
            User::query()->where('line_id', $lineId)->update([
                'line_friend_status' => $isFriend,
                'line_followed_at' => $isFriend ? now() : null,
                'updated_at' => now(),
            ]);
        }

        return response('', 200);
    }
}
