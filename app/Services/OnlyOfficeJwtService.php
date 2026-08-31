<?php

namespace App\Services;

/**
 * Migrasi 1:1 dari backend/config/onlyoffice.php (proyek PHP lama) -- JWT
 * HS256 hand-rolled (TANPA library luar), dipakai untuk menandatangani
 * config editor yang dikirim ke Document Server & memverifikasi callback
 * yang dikirim BALIK oleh Document Server. Algoritma & urutan byte HARUS
 * sama persis dengan versi lama supaya kompatibel dengan Document Server
 * OnlyOffice sungguhan (yang mengimplementasikan HS256 standar sendiri).
 */
class OnlyOfficeJwtService
{
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(
            $data,
            strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + 4 - strlen($data) % 4,
            '=',
        );

        return base64_decode(strtr($padded, '-_', '+/'));
    }

    /** Bikin JWT HS256 sederhana dari payload array, ditandatangani $secret. */
    public function encode(array $payload, ?string $secret = null): string
    {
        $secret ??= config('suratapp.onlyoffice_jwt_secret');

        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signingInput = "$header.$body";
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));

        return "$signingInput.$signature";
    }

    /**
     * Verifikasi & decode JWT HS256. Null kalau tanda tangan tidak cocok /
     * format tidak valid.
     */
    public function decode(string $token, ?string $secret = null): ?array
    {
        $secret ??= config('suratapp.onlyoffice_jwt_secret');

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        $payload = json_decode($this->base64UrlDecode($body), true);

        return is_array($payload) ? $payload : null;
    }
}
