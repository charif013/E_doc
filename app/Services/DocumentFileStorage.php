<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentFileStorage
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'documents');
    }

    /** @param resource $contents */
    public function put(string $path, $contents): void
    {
        if (! $this->privateDisk()->put($path, $contents)) {
            throw new RuntimeException("Unable to store private document: {$path}");
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $this->diskFor($path)->exists($path);
    }

    public function path(string $path): string
    {
        if (! $this->exists($path)) {
            throw new RuntimeException("Document file does not exist: {$path}");
        }

        return $this->diskFor($path)->path($path);
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        // ลบทั้ง private location และ legacy public location เพื่อไม่ให้สำเนาเก่ารั่วไหล
        $this->privateDisk()->delete($path);
        $this->legacyDisk()->delete($path);
    }

    public function response(string $path, ?string $name = null, array $headers = [])
    {
        if (! $this->exists($path)) {
            abort(404);
        }

        return $this->diskFor($path)->response($path, $name, $headers);
    }

    private function diskFor(string $path): FilesystemAdapter
    {
        return $this->privateDisk()->exists($path) ? $this->privateDisk() : $this->legacyDisk();
    }

    private function privateDisk(): FilesystemAdapter
    {
        return Storage::disk('documents');
    }

    private function legacyDisk(): FilesystemAdapter
    {
        // อ่านย้อนหลังได้เฉพาะช่วง migration; ไม่มีการเขียนไฟล์ใหม่ลง disk นี้
        return Storage::disk('public');
    }
}
