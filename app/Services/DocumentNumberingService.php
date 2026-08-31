<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentNumberAllocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentNumberingService
{
    public function allocate(
        Document $document,
        User $actor,
        ?int $runningNumber,
        string $formattedNumber,
        ?string $status = null
    ): Document {
        try {
            return DB::transaction(function () use ($document, $actor, $runningNumber, $formattedNumber, $status) {
                $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $fiscalYear = $this->fiscalYear($locked->created_at ?? now());
                $scope = $this->scope($locked);

                $historicalConflict = Document::query()
                    ->whereKeyNot($locked->id)
                    ->where('doc_type', $locked->doc_type)
                    ->when($runningNumber !== null, fn ($query) => $query->where('running_number', $runningNumber))
                    ->when($runningNumber === null, fn ($query) => $query->where('doc_number', $formattedNumber))
                    ->whereBetween('created_at', $this->fiscalRange($fiscalYear))
                    ->when($locked->doc_type === 'internal', function ($query) use ($scope) {
                        $query->whereHas('creator', fn ($users) => $users->where('department', $scope));
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($historicalConflict) {
                    throw ValidationException::withMessages([
                        'doc_number' => 'เลขเอกสารนี้ถูกใช้งานแล้ว กรุณาเลือกเลขใหม่',
                    ]);
                }

                DocumentNumberAllocation::where('document_id', $locked->id)->delete();
                DocumentNumberAllocation::create([
                    'number_type' => $locked->doc_type,
                    'scope' => $scope,
                    'fiscal_year' => $fiscalYear,
                    'running_number' => $runningNumber,
                    'formatted_number' => $formattedNumber,
                    'document_id' => $locked->id,
                    'allocated_by' => $actor->id,
                ]);

                $locked->doc_number = $formattedNumber;
                $locked->running_number = $runningNumber;
                if ($status !== null) {
                    $locked->status = $status;
                }
                $locked->save();

                return $locked;
            }, 3);
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'doc_number' => 'เลขเอกสารนี้เพิ่งถูกใช้งานโดยรายการอื่น กรุณารันเลขใหม่',
                ]);
            }

            throw $error;
        }
    }

    public function fiscalYear(CarbonInterface $date): int
    {
        return $date->month >= 10 ? $date->year + 1 : $date->year;
    }

    public function fiscalRange(int $year): array
    {
        return [($year - 1).'-10-01 00:00:00', $year.'-09-30 23:59:59'];
    }

    private function scope(Document $document): string
    {
        if ($document->doc_type !== 'internal') {
            return 'organization';
        }

        return (string) ($document->creator()->value('department') ?: 'organization');
    }
}
