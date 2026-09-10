<?php

namespace App\Services\V2;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

class MigrationSafetyGuard
{
    private const MUTATING_COMMANDS = [
        'migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback',
    ];

    public function handle(CommandStarting $event): void
    {
        if (! in_array($event->command, self::MUTATING_COMMANDS, true)) {
            return;
        }

        $connection = $event->input?->getParameterOption('--database') ?: config('database.default');
        $database = config("database.connections.{$connection}.database");
        $v2Connection = (string) config('edoc.v2.connection', 'mysql_v2');
        $v2Database = config("database.connections.{$v2Connection}.database");
        if (! $database || ! $v2Database || $database !== $v2Database) {
            return;
        }

        $path = $event->input?->getParameterOption('--path');
        $paths = array_map(
            fn ($value) => trim(str_replace('\\', '/', (string) $value), '/'),
            is_array($path) ? $path : [$path]
        );
        $allowed = trim(str_replace('\\', '/', (string) config('edoc.v2.migration_path')), '/');

        if ($event->command === 'migrate' && $paths === [$allowed]) {
            return;
        }

        throw new RuntimeException(
            'Blocked legacy migration command against the V2 database. Use php artisan edoc:v2-schema instead.'
        );
    }
}
