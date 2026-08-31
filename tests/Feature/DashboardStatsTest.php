<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_draft_is_counted_as_waiting_for_action(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        Document::create([
            'doc_type' => 'internal',
            'title' => 'รอลงนามนำส่ง',
            'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('home'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['waiting'] === 1);
    }

    public function test_someone_elses_draft_is_not_counted_as_users_waiting_work(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $other = User::factory()->create()->assignRole('officer');
        Document::create([
            'doc_type' => 'internal',
            'title' => 'ร่างของผู้อื่น',
            'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)->get(route('home'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0 && $stats['waiting'] === 0);
    }
}
