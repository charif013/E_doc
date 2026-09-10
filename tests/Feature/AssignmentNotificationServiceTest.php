<?php

namespace Tests\Feature;

use App\Jobs\SendLineNotification;
use App\Models\User;
use App\Services\AssignmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignmentNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_the_exact_user_when_work_is_delegated(): void
    {
        Queue::fake();
        $assignee = User::factory()->create([
            'line_id' => 'U'.str_repeat('a', 32),
            'line_friend_status' => true,
        ]);

        app(AssignmentNotificationService::class)->toUser(
            $assignee,
            'งานทดสอบ',
            'หัวหน้าทดสอบ',
            'รับ/1',
            'https://example.test/documents/1/show'
        );

        Queue::assertPushed(SendLineNotification::class, function ($job) use ($assignee) {
            return $job->userIds === [$assignee->id]
                && str_contains($job->message, 'งานทดสอบ')
                && str_contains($job->message, 'หัวหน้าทดสอบ');
        });
    }

    public function test_it_notifies_only_connected_heads_in_the_assigned_organization_unit(): void
    {
        Queue::fake();
        Role::create(['name' => 'head']);
        $targetHead = User::factory()->create([
            'department' => 'สำนักงานปลัด',
            'division' => 'งานธุรการ',
            'line_id' => 'U'.str_repeat('b', 32),
            'line_friend_status' => true,
        ])->assignRole('head');
        User::factory()->create([
            'department' => 'กองช่าง',
            'line_id' => 'U'.str_repeat('c', 32),
            'line_friend_status' => true,
        ])->assignRole('head');

        app(AssignmentNotificationService::class)->toUnitHeads(
            'งานธุรการ',
            'งานระดับหน่วย',
            'ผู้บริหาร',
            null,
            'https://example.test/documents/2/show'
        );

        Queue::assertPushed(SendLineNotification::class, fn ($job) => $job->userIds === [$targetHead->id]);
    }
}
