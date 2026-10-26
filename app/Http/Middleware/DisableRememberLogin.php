<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DisableRememberLogin
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * Disable Laravel's persistent "remember me" cookie while leaving normal
     * sessions intact. A previous remember-me cookie is deleted on the next
     * request, so it cannot sign a user in automatically.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $recallerName = $this->auth->guard()->getRecallerName();

        if ($request->cookies->has($recallerName)) {
            $request->cookies->remove($recallerName);
            Cookie::queue(Cookie::forget($recallerName));
        }

        return $next($request);
    }
}
