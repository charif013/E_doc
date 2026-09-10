<?php

namespace App\Console\Commands;

use App\Services\V2\DataLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredRetentionData extends Command
{
    protected $signature = 'edoc:purge-retention
        {--months= : Override the configured retention period in calendar months}
        {--dry-run : Report expired records without deleting them}';

    protected $description = 'Permanently purge soft-deleted documents and leave requests after the retention period';

    public function handle(DataLifecycleService $lifecycle): int
    {
        $connection = (string) config('edoc.v2.connection', 'mysql_v2');
        if (! config('edoc.v2.enabled') || ! Schema::connection($connection)->hasTable('documents')) {
            $this->components->info('V2 is disabled or its document tables are unavailable; nothing was purged.');

            return self::SUCCESS;
        }

        $months = $this->option('months') !== null
            ? filter_var($this->option('months'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : (int) config('edoc.retention.months', 3);

        if ($months === false || $months < 1) {
            $this->components->error('Retention months must be an integer greater than zero.');

            return self::INVALID;
        }

        $cutoff = CarbonImmutable::now()->subMonthsNoOverflow($months);
        $db = DB::connection($connection);
        $documentIds = $db->table('documents')
            ->whereNotNull('deleted_at')->where('deleted_at', '<=', $cutoff)->pluck('id');
        $leaveIds = Schema::connection($connection)->hasTable('leave_requests')
            ? $db->table('leave_requests')->whereNotNull('deleted_at')->where('deleted_at', '<=', $cutoff)->pluck('id')
            : collect();

        $this->table(['Retention period', 'Cutoff', 'Documents', 'Leave requests'], [[
            $months.' months', $cutoff->format('Y-m-d H:i:s'), $documentIds->count(), $leaveIds->count(),
        ]]);

        if ($this->option('dry-run')) {
            $this->components->info('Dry run completed; no records or files were deleted.');

            return self::SUCCESS;
        }

        if ($documentIds->isEmpty() && $leaveIds->isEmpty()) {
            $this->components->info('No expired soft-deleted records were found.');

            return self::SUCCESS;
        }

        $purged = $lifecycle->purgeExpired($cutoff);

        $this->components->info(
            "Permanently purged {$purged['documents']} documents, {$purged['leave_requests']} leave requests, and {$purged['files']} referenced files."
        );

        return self::SUCCESS;
    }
}
