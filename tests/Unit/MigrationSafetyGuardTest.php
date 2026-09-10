<?php

namespace Tests\Unit;

use App\Services\V2\MigrationSafetyGuard;
use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class MigrationSafetyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('edoc.v2.enabled', true);
        config()->set('edoc.v2.connection', 'mysql_v2');
        config()->set('edoc.v2.migration_path', 'database/migrations-v2');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'e_docv2');
        config()->set('database.connections.mysql_v2.database', 'e_docv2');
    }

    public function test_it_blocks_the_default_legacy_migration_path_on_v2(): void
    {
        $this->expectException(RuntimeException::class);

        app(MigrationSafetyGuard::class)->handle(new CommandStarting(
            'migrate', new ArrayInput([]), new BufferedOutput
        ));
    }

    public function test_it_allows_the_explicit_v2_migration_path(): void
    {
        app(MigrationSafetyGuard::class)->handle(new CommandStarting(
            'migrate',
            new ArrayInput(['--database' => 'mysql_v2', '--path' => 'database/migrations-v2']),
            new BufferedOutput
        ));

        $this->addToAssertionCount(1);
    }
}
