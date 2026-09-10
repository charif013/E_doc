<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureV2Enabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('edoc.v2.enabled'), 404);

        return $next($request);
    }
}
