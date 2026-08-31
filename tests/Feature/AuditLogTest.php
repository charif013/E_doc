<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_user_fields_are_redacted_from_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['pin' => 'secret-pin']);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'updated')
            ->orderByDesc('id')->firstOrFail();

        $this->assertSame('[REDACTED]', $log->new_values['pin']);
        $this->assertSame($user->id, $log->user_id);
    }
}
