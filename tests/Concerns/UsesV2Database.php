<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

trait UsesV2Database
{
    protected function bootV2Database(): void
    {
        config()->set('database.connections.mysql_v2', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('edoc.v2.enabled', true);
        config()->set('edoc.v2.write_enabled', true);
        DB::purge('mysql_v2');

        $exitCode = Artisan::call('migrate', [
            '--database' => 'mysql_v2',
            '--path' => 'database/migrations-v2',
            '--force' => true,
        ]);
        Assert::assertSame(0, $exitCode, Artisan::output());
    }

    protected function seedV2User(int $id = 1): void
    {
        DB::connection('mysql_v2')->table('users')->insert([
            'id' => $id,
            'name' => "V2 User {$id}",
            'email' => "v2-user-{$id}@example.test",
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'pin_reset_requested' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function seedV2Document(int $id = 1, int $creatorId = 1): void
    {
        $db = DB::connection('mysql_v2');
        $db->table('document_types')->insertOrIgnore([
            'id' => 1, 'code' => 'INTERNAL', 'name' => 'Internal', 'direction' => 'INTERNAL',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('documents')->insert([
            'id' => $id,
            'uuid' => '00000000-0000-4000-8000-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT),
            'document_type_id' => 1,
            'title' => "Document {$id}",
            'status' => 'DRAFT',
            'created_by' => $creatorId,
            'created_at' => '2026-09-01 00:00:00',
            'updated_at' => '2026-09-01 00:00:00',
        ]);
    }
}
