<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LineMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLineNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 60, 300];

    public function __construct(public array $userIds, public string $message)
    {
        $this->onQueue('notifications');
    }

    public function handle(LineMessagingService $line): void
    {
        if (empty(config('services.line.messaging_token'))) {
            return;
        }

        $users = User::whereKey($this->userIds)
            ->whereNotNull('line_id')
            ->where('line_friend_status', true)
            ->get();
        $sent = $line->sendToUsers($users, $this->message);
        if ($sent < $users->count()) {
            throw new \RuntimeException('ส่ง LINE notification ไม่ครบทุกผู้รับ');
        }
    }
}
