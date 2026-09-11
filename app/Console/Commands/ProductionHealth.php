<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionHealth extends Command
{
    protected $signature = 'edoc:production-health {--json : Emit machine-readable JSON}';

    protected $description = 'Check production database, queues, disk space, and private document storage';

    public function handle(): int
    {
        $checks = [];
        $connection = (string) config('edoc.v2.connection', config('database.default'));

        try {
            $database = DB::connection($connection);
            $database->select('SELECT 1');
            $checks[] = $this->check('database', true, $database->getDriverName());

            $staleBefore = now()->subMinutes((int) config('edoc.queue.stale_after_minutes', 10))->timestamp;
            $staleJobs = Schema::connection($connection)->hasTable('jobs')
                ? (int) $database->table('jobs')->where('available_at', '<=', $staleBefore)->count()
                : 0;
            $checks[] = $this->check('stale_jobs', $staleJobs === 0, (string) $staleJobs);

            $failedLimit = (int) config('edoc.monitoring.failed_jobs_last_hour_max', 0);
            $recentFailures = Schema::connection($connection)->hasTable('failed_jobs')
                ? (int) $database->table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count()
                : 0;
            $checks[] = $this->check('failed_jobs_last_hour', $recentFailures <= $failedLimit, "{$recentFailures}/{$failedLimit}");
        } catch (Throwable $error) {
            $checks[] = $this->check('database', false, $error::class);
        }

        $minimumBytes = (int) config('edoc.monitoring.minimum_free_disk_mb', 2048) * 1024 * 1024;
        $freeBytes = disk_free_space(storage_path());
        $checks[] = $this->check(
            'free_disk_mb',
            $freeBytes !== false && $freeBytes >= $minimumBytes,
            $freeBytes === false ? 'unknown' : (string) round($freeBytes / 1024 / 1024)
        );

        $documentsPath = Storage::disk('documents')->path('');
        $checks[] = $this->check('private_storage', is_dir($documentsPath) && is_writable($documentsPath), $documentsPath);

        if (app()->environment('production')) {
            $checks[] = $this->check('app_debug', ! config('app.debug'), config('app.debug') ? 'enabled' : 'disabled');
        }

        $failed = collect($checks)->contains(fn (array $check) => ! $check['passes']);
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => $failed ? 'fail' : 'ok',
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Check', 'Detail', 'Result'], array_map(fn (array $check) => [
                $check['name'], $check['detail'], $check['passes'] ? 'PASS' : 'FAIL',
            ], $checks));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $name, bool $passes, string $detail): array
    {
        return compact('name', 'passes', 'detail');
    }
}
