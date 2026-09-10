<?php

namespace App\Services\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumberAllocationService
{
    public function allocate(
        Model $resource,
        string $resourceColumn,
        string $numberType,
        string $scope,
        int $fiscalYear,
        int $runningNumber,
        string $formattedNumber,
        ?int $actorId,
        mixed $allocatedAt = null,
        string $errorField = 'document_number'
    ): object {
        if (! in_array($resourceColumn, ['document_id', 'leave_request_id'], true)) {
            throw new \InvalidArgumentException('Unsupported numbering resource.');
        }
        if ($runningNumber < 1 || trim($formattedNumber) === '') {
            throw ValidationException::withMessages([$errorField => 'เลขลำดับและเลขที่เอกสารไม่ถูกต้อง']);
        }

        $connection = $resource->getConnectionName();
        $db = DB::connection($connection);

        try {
            return $db->transaction(function () use (
                $db, $resource, $resourceColumn, $numberType, $scope, $fiscalYear,
                $runningNumber, $formattedNumber, $actorId, $allocatedAt, $errorField
            ) {
                $resource->newQuery()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();
                $sequenceKey = [
                    'number_type' => strtolower($numberType),
                    'scope' => $scope,
                    'fiscal_year' => $fiscalYear,
                ];

                $sequence = $db->table('number_sequences')->where($sequenceKey)->lockForUpdate()->first();
                if (! $sequence) {
                    $db->table('number_sequences')->insertOrIgnore($sequenceKey + [
                        'prefix' => null,
                        'last_number' => 0,
                        'padding' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sequence = $db->table('number_sequences')->where($sequenceKey)->lockForUpdate()->first();
                }
                if (! $sequence) {
                    throw new \RuntimeException('Unable to create or lock the number sequence.');
                }

                $existing = $db->table('number_allocations')
                    ->where($resourceColumn, $resource->getKey())->first();
                $conflict = $db->table('number_allocations')
                    ->where('sequence_id', $sequence->id)
                    ->where(function ($query) use ($runningNumber, $formattedNumber) {
                        $query->where('running_number', $runningNumber)
                            ->orWhere('formatted_number', $formattedNumber);
                    })
                    ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                    ->lockForUpdate()
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages([$errorField => 'เลขนี้ถูกใช้ในชุดเลขเดียวกันแล้ว']);
                }

                $payload = [
                    'sequence_id' => $sequence->id,
                    'running_number' => $runningNumber,
                    'formatted_number' => $formattedNumber,
                    'document_id' => $resourceColumn === 'document_id' ? $resource->getKey() : null,
                    'leave_request_id' => $resourceColumn === 'leave_request_id' ? $resource->getKey() : null,
                    'allocated_by' => $actorId,
                    'allocated_at' => $allocatedAt ?: now(),
                ];

                $existing
                    ? $db->table('number_allocations')->where('id', $existing->id)->update($payload)
                    : $db->table('number_allocations')->insert($payload);
                $db->table('number_sequences')->where('id', $sequence->id)->update([
                    'last_number' => max((int) $sequence->last_number, $runningNumber),
                    'updated_at' => now(),
                ]);

                return $db->table('number_allocations')->where($resourceColumn, $resource->getKey())->first();
            }, 3);
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([$errorField => 'เลขนี้เพิ่งถูกใช้ในชุดเลขเดียวกัน กรุณาเลือกเลขใหม่']);
            }
            throw $error;
        }
    }
}
