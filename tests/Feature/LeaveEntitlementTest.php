<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_employee_receives_contract_specific_leave_types(): void
    {
        $user = User::factory()->create([
            'gender' => 'male',
            'employment_type' => 'contract_employee',
            'employment_started_at' => now()->subMonths(8)->toDateString(),
            'vacation_eligible_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('leaves.create'))
            ->assertOk()
            ->assertSee('ลากิจส่วนตัว')
            ->assertSee('ลาอุปสมบท/ประกอบพิธีฮัจย์')
            ->assertSee('ลาพักผ่อน')
            ->assertDontSee('ลาไปช่วยเหลือภริยาที่คลอดบุตร');
    }

    public function test_general_employee_cannot_submit_personal_leave(): void
    {
        $user = User::factory()->create([
            'employment_type' => 'general_employee',
        ]);

        $this->actingAs($user)->from(route('leaves.create'))->post(route('leaves.store'), [
            'leave_type' => 'ลากิจส่วนตัว',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบสิทธิ์',
        ])->assertRedirect(route('leaves.create'))
            ->assertSessionHasErrors('leave_type');
    }
}
