<?php

namespace App\Http\Middleware;

use App\Services\TokenAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wajibkan token sesi valid (setara require_auth() di backend/config/auth.php
 * lama). User yang berhasil diautentikasi ditaruh di guard default supaya
 * $request->user() / auth()->user() bisa dipakai seperti biasa di
 * controller -- TIDAK menulis session (stateless, sama seperti sebelumnya).
 */
class TokenAuthMiddleware
{
    public function __construct(private TokenAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->requireAuth($request);
        Auth::setUser($user);

        return $next($request);
    }
}
