<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V2FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_v2_health_endpoint_is_hidden_while_feature_is_disabled(): void
    {
        config(['edoc.v2.enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/v2/health')->assertNotFound();
    }

    public function test_legacy_document_writes_are_blocked_while_v2_reads_are_active(): void
    {
        config(['edoc.v2.document_reads' => true, 'edoc.v2.write_enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/documents/1/review')->assertServiceUnavailable();
    }

    public function test_legacy_document_writes_remain_available_before_v2_reads_are_active(): void
    {
        config(['edoc.v2.document_reads' => false, 'edoc.v2.write_enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/documents/999/review')->assertNotFound();
    }

    public function test_v2_document_route_is_unblocked_only_when_write_flag_is_enabled(): void
    {
        config(['edoc.v2.document_reads' => true, 'edoc.v2.write_enabled' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/documents/1/review')
            ->assertRedirect()
            ->assertSessionHasErrors('pin');
    }
}
