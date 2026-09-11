<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_health_passes_when_dependencies_are_healthy(): void
    {
        config([
            'edoc.monitoring.failed_jobs_last_hour_max' => 100,
            'edoc.monitoring.minimum_free_disk_mb' => 100,
        ]);

        $this->artisan('edoc:production-health')->assertSuccessful();
    }

    public function test_production_health_fails_for_a_stale_job(): void
    {
        $connection = (string) config('edoc.v2.connection');
        config([
            'edoc.queue.stale_after_minutes' => 1,
            'edoc.monitoring.failed_jobs_last_hour_max' => 100,
            'edoc.monitoring.minimum_free_disk_mb' => 100,
        ]);
        DB::connection($connection)->table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(2)->timestamp,
            'created_at' => now()->subMinutes(2)->timestamp,
        ]);

        $this->artisan('edoc:production-health')->assertFailed();
    }
}
