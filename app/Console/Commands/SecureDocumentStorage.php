<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecureDocumentStorage extends Command
{
    protected $signature = 'edoc:secure-document-storage {--commit : Move files after verification}';

    protected $description = 'Move legacy public document files into private document storage';

    private const PREFIXES = [
        'attachments',
        'incoming_docs',
        'outgoing_docs',
        'incoming_qr_docs',
        'documents',
    ];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('documents');
        $paths = collect(self::PREFIXES)
            ->flatMap(fn (string $prefix) => $public->allFiles($prefix))
            ->unique()
            ->values();

        if (! $this->option('commit')) {
            $this->components->info("Dry run: {$paths->count()} public document file(s) would be secured.");
            $this->line('Run again with --commit during a maintenance window.');

            return self::SUCCESS;
        }

        $moved = 0;
        foreach ($paths as $path) {
            $this->moveVerified($public, $private, $path);
            $moved++;
        }

        $this->components->info("Secured {$moved} document file(s); no public document copies remain.");

        return self::SUCCESS;
    }

    private function moveVerified(FilesystemAdapter $public, FilesystemAdapter $private, string $path): void
    {
        $sourceHash = hash_file('sha256', $public->path($path));
        if ($private->exists($path)) {
            $targetHash = hash_file('sha256', $private->path($path));
            if (! hash_equals((string) $sourceHash, (string) $targetHash)) {
                throw new RuntimeException("Private destination differs; public source retained: {$path}");
            }
        } else {
            $stream = $public->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException("Unable to read public document: {$path}");
            }
            try {
                $private->put($path, $stream);
            } finally {
                fclose($stream);
            }
            $targetHash = hash_file('sha256', $private->path($path));
            if (! hash_equals((string) $sourceHash, (string) $targetHash)) {
                $private->delete($path);
                throw new RuntimeException("Hash verification failed; public source retained: {$path}");
            }
        }

        $public->delete($path);
        if ($public->exists($path)) {
            throw new RuntimeException("Verified private copy exists but public source could not be removed: {$path}");
        }
    }
}
