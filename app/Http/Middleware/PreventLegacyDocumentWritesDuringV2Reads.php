<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventLegacyDocumentWritesDuringV2Reads
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('edoc.v2.document_reads') && ! config('edoc.v2.write_enabled')) {
            abort(503, 'ระบบเอกสาร อยู่ในช่วงทดสอบแบบอ่านอย่างเดียว กรุณาลองใหม่หลังเปิดระบบเขียน ');
        }

        return $next($request);
    }
}
