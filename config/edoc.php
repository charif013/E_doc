<?php

return [
    'bootstrap_admin' => [
        'email' => env('EDOC_ADMIN_EMAIL'),
        'password' => env('EDOC_ADMIN_PASSWORD'),
    ],

    'retention' => [
        // Soft-deleted documents and leave requests remain recoverable for this period.
        'months' => max(1, (int) env('EDOC_RETENTION_MONTHS', 3)),
    ],

    'v2' => [
        // V2 stays dark until validation and smoke tests are complete.
        'enabled' => env('EDOC_V2_ENABLED', false),
        'write_enabled' => env('EDOC_V2_WRITE_ENABLED', false),
        'connection' => env('EDOC_V2_CONNECTION', 'mysql_v2'),
        'document_reads' => env('EDOC_V2_DOCUMENT_READS', false),
        'migration_path' => env('EDOC_V2_MIGRATION_PATH', 'database/migrations-v2'),
    ],

    'queue' => [
        // Useful for installations that run Laravel's scheduler but do not have Supervisor.
        'run_scheduled_worker' => env('EDOC_RUN_SCHEDULED_QUEUE_WORKER', true),
        'stale_after_minutes' => max(1, (int) env('EDOC_QUEUE_STALE_AFTER_MINUTES', 10)),
    ],

    'monitoring' => [
        'failed_jobs_last_hour_max' => max(0, (int) env('EDOC_FAILED_JOBS_LAST_HOUR_MAX', 0)),
        'minimum_free_disk_mb' => max(100, (int) env('EDOC_MINIMUM_FREE_DISK_MB', 2048)),
    ],
];
