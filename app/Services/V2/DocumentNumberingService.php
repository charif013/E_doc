<?php

namespace App\Services\V2;

use App\Models\V2\Document;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentNumberingService
{
    public function __construct(
        private V2WriteGuard $guard,
        private NumberAllocationService $allocations,
    ) {}

    public function allocate(Document $document, int $actorId, int $runningNumber, string $formattedNumber, string $scope): Document
    {
        $this->guard->ensureEnabled();
        if ($runningNumber < 1) {
            throw ValidationException::withMessages(['running_number' => 'เลขลำดับต้องมากกว่าศูนย์']);
        }

        try {
            return DB::connection($document->getConnectionName())->transaction(function () use ($document, $actorId, $runningNumber, $formattedNumber, $scope) {
                $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $fiscalYear = $this->fiscalYear($locked->created_at ?? now());
                $numberType = strtolower($locked->type()->value('code'));

                $this->allocations->allocate(
                    $locked, 'document_id', $numberType, $scope, $fiscalYear,
                    $runningNumber, $formattedNumber, $actorId
                );
                $locked->update([$numberType === 'incoming' ? 'receive_number' : 'document_number' => $formattedNumber]);

                return $locked->fresh();
            }, 3);
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['document_number' => 'เลขนี้ถูกใช้ในชุดเลขเดียวกันแล้ว']);
            }
            throw $error;
        }
    }

    public function fiscalYear(CarbonInterface $date): int
    {
        return $date->month >= 10 ? $date->year + 1 : $date->year;
    }
}
