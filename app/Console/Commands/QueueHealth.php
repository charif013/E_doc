<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueHealth extends Command
{
    protected $signature = 'edoc:queue-health';

    protected $description = 'Report queued and stale application jobs';

    public function handle(): int
    {
        $connection = (string) config('edoc.v2.connection', config('database.default'));
        $db = DB::connection($connection);
        $staleBefore = now()->subMinutes((int) config('edoc.queue.stale_after_minutes', 10))->timestamp;
        $rows = $db->table('jobs')
            ->selectRaw('queue, COUNT(*) as queued, SUM(CASE WHEN available_at <= ? THEN 1 ELSE 0 END) as stale', [$staleBefore])
            ->groupBy('queue')->orderBy('queue')->get();
        $stale = (int) $rows->sum('stale');

        $this->table(['Queue', 'Queued', 'Stale'], $rows->map(fn ($row) => [
            $row->queue, (int) $row->queued, (int) $row->stale,
        ])->all());

        if ($stale > 0) {
            $this->components->error("Queue health check found {$stale} stale job(s).");

            return self::FAILURE;
        }

        $this->components->info('Queue health check passed.');

        return self::SUCCESS;
    }
}
