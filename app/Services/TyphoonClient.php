<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TyphoonClient
{
    public function request(?string $apiKey = null): PendingRequest
    {
        $apiKey = trim($apiKey ?: (string) config('services.typhoon.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('ระบบ AI ยังไม่ได้รับการตั้งค่า');
        }

        $request = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('services.typhoon.timeout', 60))
            ->retry(2, 250, throw: false);

        $caBundle = trim((string) config('services.typhoon.ca_bundle'));
        if ($caBundle !== '') {
            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new RuntimeException('ไม่พบ CA certificate สำหรับเชื่อมต่อระบบ AI');
            }

            $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    public function endpoint(): string
    {
        return (string) config('services.typhoon.endpoint');
    }
}
