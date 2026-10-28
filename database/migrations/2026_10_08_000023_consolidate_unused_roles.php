<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Roles with a dedicated responsibility remain unchanged. Each user linked
     * to a retired role is moved to officer, the general-user role.
     */
    public function up(): void
    {
        $retiredRoleNames = [
            'deputy-palad',
            'finance',
            'parcel',
            'analyst',
            'engineer',
            'education',
            'health',
            'disaster',
            'auditor',
            'teacher',
            'childcare',
            'viewer',
        ];

        DB::transaction(function () use ($retiredRoleNames): void {
            $retiredRoles = DB::table('roles')
                ->whereIn('name', $retiredRoleNames)
                ->get(['id', 'name']);

            if ($retiredRoles->isEmpty()) {
                return;
            }

            $officerRoleId = DB::table('roles')->where('name', 'officer')->value('id');

            if (! $officerRoleId) {
                $officerRoleId = DB::table('roles')->insertGetId([
                    'name' => 'officer',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $retiredRoleIds = $retiredRoles->pluck('id');

            // Do not silently broaden approval authority when historical or
            // pending workflow steps still depend on a role being retired.
            foreach (['workflow_definition_steps' => 'required_role_id', 'workflow_steps' => 'assigned_role_id'] as $table => $column) {
                if (Schema::hasTable($table) && DB::table($table)->whereIn($column, $retiredRoleIds)->exists()) {
                    throw new RuntimeException(
                        "Cannot retire roles while {$table}.{$column} still references them."
                    );
                }
            }

            $modelRoleLinks = DB::table('model_has_roles')
                ->whereIn('role_id', $retiredRoleIds)
                ->get(['model_type', 'model_id']);

            foreach ($modelRoleLinks as $link) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $officerRoleId,
                    'model_type' => $link->model_type,
                    'model_id' => $link->model_id,
                ]);
            }

            DB::table('model_has_roles')->whereIn('role_id', $retiredRoleIds)->delete();
            DB::table('roles')->whereIn('id', $retiredRoleIds)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * User-to-role assignments cannot be reconstructed reliably after roles
     * have been consolidated, so this data migration is intentionally one-way.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
