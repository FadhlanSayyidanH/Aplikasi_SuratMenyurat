<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipakai SETELAH TokenAuthMiddleware -- setara require_admin() lama.
 * 403 kalau user yang sudah login bukan role 'admin'.
 */
class AdminOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            throw new ApiException('Hanya Administrator yang bisa mengakses ini', 403);
        }

        return $next($request);
    }
}
