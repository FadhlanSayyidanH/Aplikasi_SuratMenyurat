<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Migrasi dari backend/config/cors.php (proyek PHP lama) -- daftar origin
 * eksplisit (domain produksi + localhost/IP LAN untuk dev), TANPA wildcard.
 * Pola IP LAN sengaja disamakan dengan lib/services/api_config.dart di sisi
 * Flutter supaya tetap konsisten.
 */
class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigin = $this->allowedOrigin($origin);

        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($allowedOrigin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Vary', 'Origin');
        }
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
    }

    private function allowedOrigin(?string $origin): ?string
    {
        if (!$origin) {
            return null;
        }

        $extra = config('suratapp.cors_extra_origin');
        if ($extra && $origin === $extra) {
            return $origin;
        }

        $pattern = '#^https?://(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3}'
            .'|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})'
            .'(:\d+)?$#';

        return preg_match($pattern, $origin) ? $origin : null;
    }
}
