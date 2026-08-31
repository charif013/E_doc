<?php

namespace App\Services;

use App\Jobs\SendLineNotification;
use App\Models\User;

class NotificationDispatcher
{
    public function toUser(?User $user, string $message): void
    {
        $this->toUsers($user ? [$user] : [], $message);
    }

    public function toUsers(iterable $users, string $message): void
    {
        $ids = collect($users)
            ->filter(fn ($user) => $user instanceof User && ! empty($user->line_id))
            ->pluck('id')->unique()->values()->all();

        if ($ids !== []) {
            SendLineNotification::dispatch($ids, $message)->afterCommit();
        }
    }
}
