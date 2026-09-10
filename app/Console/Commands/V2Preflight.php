<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class V2Preflight extends Command
{
    protected $signature = 'edoc:v2-preflight
        {--dump=tmp/edoc_db_pre_v2_20260901.sql : Verified logical dump path}
        {--require-write-enabled : Require both V2 feature flags for a controlled write window}';

    protected $description = 'Run non-mutating readiness checks before a V2 smoke test or cutover';

    public function handle(): int
    {
        $rows = [];
        $blockers = 0;
        $target = DB::connection((string) config('edoc.v2.connection'));
        $source = DB::connection('mysql_legacy_readonly');
        $requiredMigrations = [
            '2026_09_01_000001_create_identity_and_access_tables',
            '2026_09_01_000002_create_document_tables',
            '2026_09_01_000003_create_leave_workflow_and_numbering_tables',
            '2026_09_01_000004_create_booking_audit_and_system_tables',
            '2026_09_01_000005_add_document_transition_metadata',
            '2026_09_02_000006_add_application_compatibility_layer',
            '2026_09_02_000007_enforce_canonical_number_allocations',
            '2026_09_02_000008_normalize_leave_number_sequence_scopes',
            '2026_09_02_000009_replace_legacy_number_allocations_with_view',
            '2026_09_04_000010_preserve_workflow_evidence_and_leave_integrity',
            '2026_09_04_000011_enforce_append_only_audit_tables',
            '2026_09_06_000012_add_line_notification_status_to_users',
            '2026_09_08_000013_backfill_pending_leave_delegate_status',
            '2026_09_10_000014_make_leave_workflow_canonical',
        ];
        $ran = $target->table('migrations')->whereIn('migration', $requiredMigrations)->pluck('migration')->all();
        $this->check($rows, $blockers, 'V2 migrations', count($ran).'/'.count($requiredMigrations), count($ran) === count($requiredMigrations));

        $dump = base_path((string) $this->option('dump'));
        $dumpSize = is_file($dump) ? (int) filesize($dump) : 0;
        $dumpOk = $dumpSize > 0;
        $dumpHash = $dumpOk ? hash_file('sha256', $dump) : false;
        $dumpOk = $dumpOk && is_string($dumpHash);
        $dumpDetail = $dumpOk ? round($dumpSize / 1024, 1).' KiB; '.$dumpHash : 'missing or unreadable';
        $this->check($rows, $blockers, 'Verified logical dump', $dumpDetail, $dumpOk);

        $queuedJobs = $source->table('jobs')->count();
        $this->check($rows, $blockers, 'Legacy queue drained', (string) $queuedJobs, $queuedJobs === 0);
        $targetQueuedJobs = $target->table('jobs')->count();
        $this->check($rows, $blockers, 'V2 queue drained', (string) $targetQueuedJobs, $targetQueuedJobs === 0);
        $this->checkSignatureFiles($rows, $blockers, $target);

        $validationExit = Artisan::call('edoc:v2-validate');
        $this->check($rows, $blockers, 'Database validation', $validationExit === 0 ? 'PASS' : 'FAIL', $validationExit === 0);

        $enabled = (bool) config('edoc.v2.enabled');
        $writeEnabled = (bool) config('edoc.v2.write_enabled');
        $flagOk = $enabled && (! $this->option('require-write-enabled') || $writeEnabled);
        $this->check($rows, $blockers, 'Feature flags', 'enabled='.($enabled ? 'true' : 'false').', write='.($writeEnabled ? 'true' : 'false'), $flagOk);

        $this->table(['Check', 'Detail', 'Result'], $rows);
        if ($blockers > 0) {
            $this->error("Preflight found {$blockers} blocker(s).");

            return self::FAILURE;
        }
        $this->info('V2 preflight passed. This command did not change either database.');

        return self::SUCCESS;
    }

    private function checkSignatureFiles(array &$rows, int &$blockers, ConnectionInterface $target): void
    {
        $disk = Storage::disk('local');
        $missing = 0;
        $mismatch = 0;
        foreach ($target->table('user_signatures')->get(['file_path', 'sha256']) as $signature) {
            $filePath = (string) $signature->file_path;
            if (! $disk->exists($filePath)) {
                $missing++;

                continue;
            }
            $actualHash = hash('sha256', $disk->get($filePath));
            if (! hash_equals((string) $signature->sha256, $actualHash)) {
                $mismatch++;
            }
        }
        foreach ($target->table('workflow_action_evidence')
            ->whereNotNull('signature_path')
            ->get(['signature_path as file_path', 'signature_sha256 as sha256']) as $signature) {
            $filePath = (string) $signature->file_path;
            if (! $disk->exists($filePath)) {
                $missing++;

                continue;
            }
            $actualHash = hash('sha256', $disk->get($filePath));
            if (! hash_equals((string) $signature->sha256, $actualHash)) {
                $mismatch++;
            }
        }
        $this->check($rows, $blockers, 'Signature files and hashes', "missing={$missing}, mismatch={$mismatch}", $missing === 0 && $mismatch === 0);
    }

    private function check(array &$rows, int &$blockers, string $name, string $detail, bool $passes): void
    {
        $rows[] = [$name, $detail, $passes ? 'PASS' : 'BLOCK'];
        if (! $passes) {
            $blockers++;
        }
    }
}
