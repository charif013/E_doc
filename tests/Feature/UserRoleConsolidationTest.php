<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_positions_without_a_dedicated_system_duty_receive_the_general_user_role(): void
    {
        Role::findOrCreate('super-admin');
        Role::findOrCreate('officer');

        $admin = User::factory()->create()->assignRole('super-admin');

        $positions = [
            'รองปลัด อบต.',
            'นักวิเคราะห์นโยบายและแผน',
            'ผช.เจ้าพนักงานป้องกันและบรรเทาสาธารณภัย',
            'นักวิชาการตรวจสอบภายใน',
            'ครู',
        ];

        foreach ($positions as $index => $position) {
            $email = "general-user-{$index}@example.test";

            $this->actingAs($admin)->post(route('users.store'), [
                'name' => "General User {$index}",
                'email' => $email,
                'password' => 'password',
                'employment_type' => 'local_employee',
                'department' => 'กองทดสอบ',
                'division' => 'งานทดสอบ',
                'position' => $position,
                'name_prefix' => 'นาย',
            ])->assertRedirect(route('users.index'));

            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->hasRole('officer'));
            $this->assertCount(1, $user->roles);
        }
    }

    public function test_positions_with_a_dedicated_system_duty_keep_their_role(): void
    {
        foreach (['super-admin', 'executive', 'palad', 'saraban', 'head', 'hr', 'worker'] as $role) {
            Role::findOrCreate($role);
        }

        $admin = User::factory()->create()->assignRole('super-admin');
        $positions = [
            'นายก อบต.' => 'executive',
            'ปลัด อบต.' => 'palad',
            'หัวหน้าสำนักปลัด อบต.' => 'head',
            'นักทรัพยากรบุคคล' => 'hr',
            'เจ้าพนักงานธุรการ' => 'saraban',
            'คนงาน' => 'worker',
        ];

        $sequence = 0;
        foreach ($positions as $position => $role) {
            $sequence++;
            $email = "dedicated-role-{$sequence}@example.test";

            $this->actingAs($admin)->post(route('users.store'), [
                'name' => "Dedicated Role {$sequence}",
                'email' => $email,
                'password' => 'password',
                'employment_type' => $position === 'คนงาน' ? 'general_employee' : 'local_employee',
                'department' => 'สำนักงานปลัด',
                'division' => 'งานทดสอบ',
                'position' => $position,
                'name_prefix' => 'นางสาว',
            ])->assertRedirect(route('users.index'));

            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->hasRole($role));
            $this->assertCount(1, $user->roles);
        }
    }
}
