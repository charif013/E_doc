<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('number_allocations') || ! Schema::hasTable('number_sequences')) {
            return;
        }

        DB::transaction(function () {
            $allocations = DB::table('number_allocations as allocations')
                ->join('number_sequences as sequences', 'sequences.id', '=', 'allocations.sequence_id')
                ->join('leave_requests as leaves', 'leaves.id', '=', 'allocations.leave_request_id')
                ->join('users', 'users.id', '=', 'leaves.user_id')
                ->whereNotNull('allocations.leave_request_id')
                ->get([
                    'allocations.id', 'allocations.leave_request_id', 'allocations.running_number',
                    'allocations.formatted_number', 'sequences.fiscal_year', 'users.department',
                ]);

            foreach ($allocations->groupBy(fn ($row) => ($row->department ?: 'ไม่ระบุสังกัด').'|'.$row->fiscal_year) as $group) {
                $scope = $group->first()->department ?: 'ไม่ระบุสังกัด';
                $year = (int) $group->first()->fiscal_year;
                if ($group->duplicates('running_number')->isNotEmpty() || $group->duplicates('formatted_number')->isNotEmpty()) {
                    throw new \RuntimeException("Duplicate leave numbers prevent sequence normalization for {$scope}/{$year}.");
                }

                $sequence = DB::table('number_sequences')->where([
                    'number_type' => 'leave', 'scope' => $scope, 'fiscal_year' => $year,
                ])->lockForUpdate()->first();
                $sequenceId = $sequence?->id ?: DB::table('number_sequences')->insertGetId([
                    'number_type' => 'leave', 'scope' => $scope, 'fiscal_year' => $year,
                    'prefix' => null, 'last_number' => 0, 'padding' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('number_allocations')->whereIn('id', $group->pluck('id'))->update(['sequence_id' => $sequenceId]);
                DB::table('number_sequences')->where('id', $sequenceId)->update([
                    'last_number' => max((int) ($sequence?->last_number ?? 0), (int) $group->max('running_number')),
                    'updated_at' => now(),
                ]);

                if (Schema::hasTable('document_number_allocations')) {
                    DB::table('document_number_allocations')
                        ->whereIn('leave_request_id', $group->pluck('leave_request_id'))
                        ->update(['scope' => $scope, 'updated_at' => now()]);
                }
            }

            DB::table('number_sequences')->where('number_type', 'leave')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('number_allocations')
                    ->whereColumn('number_allocations.sequence_id', 'number_sequences.id'))
                ->delete();
        }, 3);
    }

    public function down(): void
    {
        // Sequence scopes are business data; reverting them could reintroduce duplicate numbering.
    }
};
