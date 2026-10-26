<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_uses_bootstrap_five_pagination(): void
    {
        $html = (string) (new LengthAwarePaginator([1], 2, 1, 1))->links();

        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_sensitive_user_fields_are_redacted_from_audit_log(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['pin' => 'secret-pin']);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'updated')
            ->orderByDesc('id')->firstOrFail();

        /** @var array<string, mixed> $newValues */
        $newValues = $log->getAttribute('new_values');

        $this->assertSame('[REDACTED]', $newValues['pin']);
        $this->assertSame($user->id, $log->getAttribute('user_id'));
    }

    public function test_audit_logs_are_append_only(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $log = AuditLog::where('auditable_type', User::class)->where('auditable_id', $user->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_super_admin_can_filter_the_activity_history_by_actor(): void
    {
        Role::create(['name' => 'super-admin']);
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        /** @var User $actor */
        $actor = User::factory()->create(['name' => 'ผู้ทดสอบประวัติ']);

        AuditLog::create([
            'user_id' => $actor->id,
            'event' => 'document.test_viewed',
            'auditable_type' => 'App\\Models\\Document',
            'auditable_id' => 99,
            'new_values' => ['status' => 'seen'],
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($admin);
        $response = $this->get(route('admin.audit_logs.index', [
            'event' => 'document.test_viewed',
            'user_id' => $actor->id,
        ]));

        $response->assertOk()
            ->assertSee('ประวัติการใช้งานระบบ')
            ->assertSee('ผู้ดำเนินการ')
            ->assertSee('ผู้ทดสอบประวัติ')
            ->assertSee('document.test_viewed')
            ->assertSee('ดูการเปลี่ยนแปลง');

        /** @var LengthAwarePaginator $logs */
        $logs = $response->viewData('logs');
        $this->assertSame(1, $logs->total());
    }
}
