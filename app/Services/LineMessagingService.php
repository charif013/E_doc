<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingService
{
    public function sendToUser(?User $user, string $message): bool
    {
        if (!$user || empty($user->line_id)) {
            return false;
        }

        $token = config('services.line.messaging_token');
        if (empty($token)) {
            Log::warning('LINE notification skipped: LINE_BOT_TOKEN is not configured.');
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 250)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $user->line_id,
                    'messages' => [[
                        'type' => 'text',
                        'text' => mb_substr($message, 0, 5000),
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('LINE push message failed.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('LINE push message exception.', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendToUsers(iterable $users, string $message): int
    {
        $collection = $users instanceof Collection ? $users : collect($users);

        return $collection
            ->filter(fn ($user) => $user instanceof User && !empty($user->line_id))
            ->unique('id')
            ->sum(fn (User $user) => $this->sendToUser($user, $message) ? 1 : 0);
    }

    public function usersWithRoles(array|string $roles, ?string $department = null): Collection
    {
        return User::role($roles)
            ->when($department, fn ($query) => $query->where('department', $department))
            ->whereNotNull('line_id')
            ->where('line_id', '!=', '')
            ->get();
    }
}
