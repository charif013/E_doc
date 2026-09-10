<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class ValidateV2Migration extends Command
{
    protected $signature = 'edoc:v2-validate
        {--source=mysql_legacy_readonly : Legacy database connection}
        {--target=mysql_v2 : V2 database connection}
        {--require-parity : Require legacy and V2 business row counts to match}
        {--allow-empty : Do not fail when the V2 business tables are empty}';

    protected $description = 'Validate legacy and V2 database invariants without changing either database';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');
        $source = DB::connection($sourceName);
        $target = DB::connection($targetName);

        $checks = [];
        $blocking = 0;

        $this->add($checks, $blocking, 'Source numbered documents without allocation',
            $source->table('documents as d')
                ->leftJoin('document_number_allocations as a', 'a.document_id', '=', 'd.id')
                ->whereNotNull('d.running_number')->whereNull('a.id')->count(), 0, false);
        $this->add($checks, $blocking, 'Source numbered leave requests without allocation',
            $source->table('leave_requests as l')
                ->leftJoin('document_number_allocations as a', 'a.leave_request_id', '=', 'l.id')
                ->whereNotNull('l.running_number')->whereNull('a.id')->count(), 0);
        $this->add($checks, $blocking, 'Source duplicate document route steps',
            $this->duplicateGroups($source, 'document_routes', ['document_id', 'step_order']), 0);
        $this->add($checks, $blocking, 'Source duplicate access-request pairs',
            $this->duplicateGroups($source, 'document_access_requests', ['document_id', 'user_id']), 0);
        $this->add($checks, $blocking, 'Source invalid room-booking ranges',
            $source->table('room_bookings')->whereColumn('end_time', '<=', 'start_time')->count(), 0);

        $targetDocuments = $target->table('documents')->count();
        $targetBusinessRows = $targetDocuments
            + $target->table('leave_requests')->count()
            + $target->table('room_bookings')->count();
        if (! $this->option('allow-empty') && $targetBusinessRows === 0) {
            $this->add($checks, $blocking, 'V2 contains business data', 0, 1);
        }

        if ($this->option('require-parity')) {
            $pairs = [
                ['users', 'users'],
                ['documents', 'documents'],
                ['leave_requests', 'leave_requests'],
                ['rooms', 'rooms'],
                ['room_bookings', 'room_bookings'],
                ['audit_logs', 'audit_logs'],
            ];
            foreach ($pairs as [$sourceTable, $targetTable]) {
                $expected = $source->table($sourceTable)->count();
                $actual = $target->table($targetTable)->count();
                $this->add($checks, $blocking, "Row count {$sourceTable} -> {$targetTable}", $actual, $expected);
            }

            $metadataMismatches = 0;
            foreach ($source->table('documents')->get(['id', 'signer_name', 'reference_doc', 'remark', 'reject_reason']) as $legacy) {
                $v2 = $target->table('documents')->where('id', $legacy->id)
                    ->first(['signer_name', 'reference_text', 'remark', 'rejection_reason']);
                if (! $v2
                    || $legacy->signer_name !== $v2->signer_name
                    || $legacy->reference_doc !== $v2->reference_text
                    || $legacy->remark !== $v2->remark
                    || $legacy->reject_reason !== $v2->rejection_reason) {
                    $metadataMismatches++;
                }
            }
            $this->add($checks, $blocking, 'V2 document metadata mismatches', $metadataMismatches, 0);

            $sourceEvidence = (int) $source->table('documents')->get()->sum(function ($document) {
                return collect([
                    [$document->created_by, $document->creator_signature],
                    [$document->supervisor_id, $document->supervisor_signature],
                    [$document->palad_id, $document->palad_signature],
                    [$document->nayok_id, $document->nayok_signature],
                ])->filter(fn ($approval) => $approval[0] && $approval[1])->count();
            });
            $targetEvidence = $target->table('workflow_action_evidence')
                ->whereIn('action', ['CREATED', 'SUPERVISOR_APPROVED', 'PALAD_APPROVED', 'EXECUTIVE_APPROVED'])
                ->count();
            $this->add($checks, $blocking, 'Workflow signature evidence rows', $targetEvidence, $sourceEvidence);

        }

        $this->add($checks, $blocking, 'V2 allocations with no or multiple resources',
            $target->table('number_allocations')
                ->whereRaw('(document_id IS NULL) = (leave_request_id IS NULL)')->count(), 0);
        $this->add($checks, $blocking, 'V2 workflow instances with no or multiple resources',
            $target->table('workflow_instances')
                ->whereRaw('(document_id IS NULL) = (leave_request_id IS NULL)')->count(), 0);
        $this->add($checks, $blocking, 'V2 active workflows without an actionable current step',
            $target->table('workflow_instances as instances')
                ->leftJoin('workflow_steps as steps', function ($join) {
                    $join->on('steps.workflow_instance_id', '=', 'instances.id')
                        ->on('steps.step_order', '=', 'instances.current_step')
                        ->where('steps.status', 'PENDING');
                })
                ->where('instances.status', 'IN_PROGRESS')->whereNull('steps.id')->count(), 0);
        $this->add($checks, $blocking, 'V2 invalid room-booking ranges',
            $target->table('room_bookings')->whereColumn('end_time', '<=', 'start_time')->count(), 0);

        $missingLeaveEvidence = $target->table('workflow_steps as steps')
            ->join('workflow_instances as instances', 'instances.id', '=', 'steps.workflow_instance_id')
            ->leftJoin('workflow_action_evidence as evidence', function ($join) {
                $join->on('evidence.workflow_step_id', '=', 'steps.id')
                    ->where('evidence.resource_type', 'LEAVE');
            })
            ->whereNotNull('instances.leave_request_id')
            ->whereIn('steps.status', ['APPROVED', 'REJECTED'])
            ->whereNull('evidence.id')
            ->count();
        $this->add($checks, $blocking, 'V2 leave actions without immutable evidence', $missingLeaveEvidence, 0);

        $this->table(['Check', 'Actual', 'Expected', 'Result'], $checks);
        $this->newLine();
        $blocking === 0
            ? $this->info('V2 validation passed.')
            : $this->error("V2 validation found {$blocking} blocking check(s).");

        return $blocking === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function duplicateGroups(ConnectionInterface $connection, string $table, array $columns): int
    {
        $query = $connection->table($table)->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1');

        return $connection->table($query, 'duplicates')->count();
    }

    private function add(array &$checks, int &$blocking, string $name, int $actual, int $expected, bool $isBlocking = true): void
    {
        $passes = $actual === $expected;
        $checks[] = [$name, $actual, $expected, $passes ? 'PASS' : ($isBlocking ? 'BLOCK' : 'WARN')];
        if (! $passes && $isBlocking) {
            $blocking++;
        }
    }
}
