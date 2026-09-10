<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateV2Schema extends Command
{
    protected $signature = 'edoc:v2-schema {--pretend : Show SQL without executing it} {--force : Run in production}';

    protected $description = 'Safely run only the canonical V2 schema migrations';

    public function handle(): int
    {
        $arguments = [
            '--database' => (string) config('edoc.v2.connection', 'mysql_v2'),
            '--path' => (string) config('edoc.v2.migration_path', 'database/migrations-v2'),
            '--force' => (bool) $this->option('force'),
            '--no-interaction' => true,
        ];
        if ($this->option('pretend')) {
            $arguments['--pretend'] = true;
        }

        $exitCode = Artisan::call('migrate', $arguments);
        $this->output->write(Artisan::output());

        return $exitCode;
    }
}
