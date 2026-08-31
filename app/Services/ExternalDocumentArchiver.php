<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExternalDocumentArchiver
{
    private const MAX_BYTES = 25 * 1024 * 1024;
    private const MAX_REDIRECTS = 3;

    public function archive(string $url, string $directory = 'incoming_qr_docs'): array
    {
        $currentUrl = trim($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $this->assertSafePublicUrl($currentUrl);

            $response = Http::withHeaders([
                'Accept' => 'application/pdf,image/*,application/msword,application/vnd.openxmlformats-officedocument.*;q=0.9,*/*;q=0.5',
                'User-Agent' => 'e-Doc QR Document Archiver/1.0',
            ])->withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])->connectTimeout(10)->timeout(30)->get($currentUrl);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (!$location || $redirects === self::MAX_REDIRECTS) {
                    throw new RuntimeException('ลิงก์เปลี่ยนเส้นทางมากเกินไป');
                }
                $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
                continue;
            }

            if (!$response->successful()) {
                throw new RuntimeException('ปลายทางตอบกลับ HTTP ' . $response->status());
            }

            return $this->storeResponse($response, $currentUrl, $directory);
        }

        throw new RuntimeException('ไม่สามารถดาวน์โหลดเอกสารจากลิงก์ได้');
    }

    private function assertSafePublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('รองรับเฉพาะลิงก์ HTTP หรือ HTTPS');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('ไม่รองรับลิงก์ที่มีข้อมูลเข้าสู่ระบบ');
        }

        $host = $parts['host'];
        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_values(array_unique(array_filter(array_merge(
                gethostbynamel($host) ?: [],
                array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
            ))));

        if (!$addresses) {
            throw new RuntimeException('ไม่พบที่อยู่เซิร์ฟเวอร์ของลิงก์');
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('ไม่อนุญาตให้ดาวน์โหลดจากเครือข่ายภายในหรือที่อยู่สงวน');
            }
        }
    }

    private function storeResponse($response, string $finalUrl, string $directory): array
    {
        $declaredSize = (int) ($response->header('Content-Length') ?: 0);
        if ($declaredSize > self::MAX_BYTES) {
            throw new RuntimeException('เอกสารจาก QR มีขนาดเกิน 25 MB');
        }

        $mime = strtolower(trim(explode(';', $response->header('Content-Type') ?: 'application/octet-stream')[0]));
        if ($mime === 'text/html') {
            throw new RuntimeException('ลิงก์นี้เป็นหน้าเว็บไซต์ ไม่ใช่ลิงก์ดาวน์โหลดไฟล์โดยตรง');
        }

        $temporaryPath = tempnam(storage_path('app'), 'qr-archive-');
        $output = fopen($temporaryPath, 'wb');
        $body = $response->toPsrResponse()->getBody();
        $size = 0;

        try {
            while (!$body->eof()) {
                $chunk = $body->read(8192);
                $size += strlen($chunk);
                if ($size > self::MAX_BYTES) {
                    throw new RuntimeException('เอกสารจาก QR มีขนาดเกิน 25 MB');
                }
                fwrite($output, $chunk);
            }
        } finally {
            fclose($output);
        }

        try {
            if ($size === 0) {
                throw new RuntimeException('ไฟล์ที่ดาวน์โหลดไม่มีข้อมูล');
            }

            $originalName = $this->resolveFilename($response->header('Content-Disposition'), $finalUrl, $mime);
            $storedName = now()->format('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . pathinfo($originalName, PATHINFO_EXTENSION);
            $storagePath = trim($directory, '/') . '/' . $storedName;
            $stream = fopen($temporaryPath, 'rb');
            try {
                Storage::disk('public')->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            return [
                'external_attachment_path' => $storagePath,
                'external_original_name' => $originalName,
                'external_mime_type' => $mime,
                'external_file_size' => $size,
                'external_sha256' => hash_file('sha256', $temporaryPath),
                'external_downloaded_at' => now(),
                'external_download_error' => null,
            ];
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function resolveFilename(?string $contentDisposition, string $url, string $mime): string
    {
        $filename = null;
        if ($contentDisposition && preg_match('/filename\*?=(?:UTF-8\'\')?["\']?([^"\';]+)/i', $contentDisposition, $matches)) {
            $filename = rawurldecode(trim($matches[1]));
        }
        $filename = $filename ?: basename(parse_url($url, PHP_URL_PATH) ?: '');
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename ?: 'qr-document');

        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $extensions = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            ];
            $filename .= '.' . ($extensions[$mime] ?? 'bin');
        }

        return mb_substr($filename, 0, 240);
    }
}
