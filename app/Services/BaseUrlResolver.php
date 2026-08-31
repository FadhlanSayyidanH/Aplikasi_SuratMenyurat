<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Migrasi dari backend/config/url.php (proyek PHP lama) -- deteksi base URL
 * publik (skema + host) dengan benar, termasuk saat request datang lewat
 * reverse proxy/tunnel seperti ngrok (X-Forwarded-Proto/X-Forwarded-Host).
 */
class BaseUrlResolver
{
    public static function resolve(Request $request): string
    {
        $forwardedProto = $request->header('X-Forwarded-Proto');
        if ($forwardedProto) {
            $scheme = trim(explode(',', $forwardedProto)[0]);
        } else {
            $scheme = $request->isSecure() ? 'https' : 'http';
        }

        $host = $request->header('X-Forwarded-Host') ?: $request->getHttpHost();

        return $scheme.'://'.$host;
    }
}
