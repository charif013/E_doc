<?php

namespace App\Services\V2;

use App\Models\V2\Document;
use App\Models\V2\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class DataLifecycleService
{
    public function restoreDocument(int|Document $document): Document
    {
        $document = $this->document($document);
        DB::connection($document->getConnectionName())->transaction(fn () => $document->restore(), 3);

        return $document->refresh();
    }

    public function forceDeleteDocument(int|Document $document): array
    {
        $document = $this->document($document);
        if (! $document->trashed()) {
            throw new LogicException('A document must be soft deleted before it can be permanently deleted.');
        }
        $paths = $this->documentPaths($document);
        $staged = $this->stageFiles($paths);

        try {
            DB::connection($document->getConnectionName())->transaction(fn () => $document->forceDelete(), 3);
        } catch (Throwable $error) {
            $this->restoreStagedFiles($staged);
            throw $error;
        }

        $this->deleteStagedFiles($staged);

        return ['documents' => 1, 'files' => $paths->count()];
    }

    public function restoreLeaveRequest(int|LeaveRequest $leave): LeaveRequest
    {
        $leave = $this->leaveRequest($leave);
        DB::connection($leave->getConnectionName())->transaction(fn () => $leave->restore(), 3);

        return $leave->refresh();
    }

    public function forceDeleteLeaveRequest(int|LeaveRequest $leave): array
    {
        $leave = $this->leaveRequest($leave);
        if (! $leave->trashed()) {
            throw new LogicException('A leave request must be soft deleted before it can be permanently deleted.');
        }
        DB::connection($leave->getConnectionName())->transaction(fn () => $leave->forceDelete(), 3);

        return ['leave_requests' => 1];
    }

    public function purgeExpired(CarbonInterface $cutoff): array
    {
        $documentIds = Document::onlyTrashed()->where('deleted_at', '<=', $cutoff)->pluck('id');
        $leaveIds = LeaveRequest::onlyTrashed()->where('deleted_at', '<=', $cutoff)->pluck('id');
        $result = ['documents' => 0, 'leave_requests' => 0, 'files' => 0];

        foreach ($documentIds as $id) {
            $purged = $this->forceDeleteDocument((int) $id);
            $result['documents'] += $purged['documents'];
            $result['files'] += $purged['files'];
        }
        foreach ($leaveIds as $id) {
            $purged = $this->forceDeleteLeaveRequest((int) $id);
            $result['leave_requests'] += $purged['leave_requests'];
        }

        return $result;
    }

    private function document(int|Document $document): Document
    {
        return $document instanceof Document
            ? $document
            : Document::withTrashed()->findOrFail($document);
    }

    private function leaveRequest(int|LeaveRequest $leave): LeaveRequest
    {
        return $leave instanceof LeaveRequest
            ? $leave
            : LeaveRequest::withTrashed()->findOrFail($leave);
    }

    private function documentPaths(Document $document): Collection
    {
        $db = DB::connection($document->getConnectionName());
        $filePaths = $db->table('document_files')->where('document_id', $document->id)
            ->whereNotNull('file_path')->pluck('file_path');
        // Workflow evidence and its signature are an immutable audit record.
        // They intentionally outlive retention deletion of the business record.
        $paths = $filePaths->filter()->unique()->values();
        $this->assertSafeRelativePaths($paths);

        return $paths;
    }

    private function stageFiles(Collection $paths): array
    {
        $disk = Storage::disk('documents');
        $batch = (string) Str::uuid();
        $staged = [];

        try {
            foreach ($paths as $path) {
                if (! $disk->exists($path)) {
                    continue;
                }
                $target = '.retention-purge/'.$batch.'/'.hash('sha256', $path).'-'.basename($path);
                if (! $disk->move($path, $target)) {
                    throw new RuntimeException("Unable to stage document file: {$path}");
                }
                $staged[$path] = $target;
            }
        } catch (Throwable $error) {
            $this->restoreStagedFiles($staged);
            throw $error;
        }

        return $staged;
    }

    private function restoreStagedFiles(array $staged): void
    {
        $disk = Storage::disk('documents');
        foreach (array_reverse($staged, true) as $original => $temporary) {
            if ($disk->exists($temporary)) {
                $disk->move($temporary, $original);
            }
        }
    }

    private function deleteStagedFiles(array $staged): void
    {
        $disk = Storage::disk('documents');
        foreach ($staged as $temporary) {
            $disk->delete($temporary);
            if ($disk->exists($temporary)) {
                throw new RuntimeException("Database was purged, but a staged file remains: {$temporary}");
            }
        }
    }

    private function assertSafeRelativePaths(Collection $paths): void
    {
        $unsafe = $paths->first(function (string $path) {
            $normalized = str_replace('\\', '/', $path);

            return str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:/', $normalized)
                || in_array('..', explode('/', $normalized), true);
        });

        if ($unsafe !== null) {
            throw new RuntimeException('Unsafe document path detected; permanent deletion aborted.');
        }
    }
}
