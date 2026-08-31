<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi dari backend/config/auth.php (proyek PHP lama) -- autentikasi
 * bearer-token stateless (bukan session Laravel). Dua token independen:
 *   - `token` (session, TTL suratapp.auth_token_ttl_seconds) -- dipakai
 *     seluruh endpoint API biasa, lewat header Authorization: Bearer <token>
 *     (fallback ?token= untuk request yang tidak bisa kirim header custom).
 *   - `file_token` (TTL jauh lebih pendek, suratapp.file_token_ttl_seconds)
 *     -- KHUSUS untuk URL lampiran surat (/uploads/...) & callback
 *     OnlyOffice, supaya kebocoran tautan berkas tidak membocorkan sesi API
 *     penuh. Lihat get_or_buat_file_token() untuk kenapa nilainya
 *     dipertahankan (diperpanjang, bukan diganti) selama devices lain masih
 *     memakainya.
 */
class TokenAuthService
{
    public function tokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        $queryToken = $request->query('token');
        if (!empty($queryToken)) {
            return (string) $queryToken;
        }

        return null;
    }

    public function optionalAuth(Request $request): ?User
    {
        $token = $this->tokenFromRequest($request);
        if (!$token) {
            return null;
        }

        return User::query()
            ->where('token', $token)
            ->where('token_expires_at', '>', now())
            ->first();
    }

    public function optionalAuthByFileToken(Request $request): ?User
    {
        $token = $request->query('token');
        if (empty($token)) {
            return null;
        }

        return User::query()
            ->where('file_token', $token)
            ->where('file_token_expires_at', '>', now())
            ->first();
    }

    /**
     * @throws ApiException 401 kalau belum login / sesi berakhir
     */
    public function requireAuth(Request $request): User
    {
        $user = $this->optionalAuth($request);
        if (!$user) {
            throw new ApiException('Belum login atau sesi sudah berakhir, silakan login ulang', 401);
        }

        return $user;
    }

    /**
     * @throws ApiException 401/403
     */
    public function requireAdmin(Request $request): User
    {
        $user = $this->requireAuth($request);
        if ($user->role !== 'admin') {
            throw new ApiException('Hanya Administrator yang bisa mengakses ini', 403);
        }

        return $user;
    }

    /**
     * @throws ApiException 401 kalau file_token tidak ada/tidak valid
     */
    public function requireFileToken(Request $request): User
    {
        $token = $request->query('token');
        if (empty($token)) {
            throw new ApiException('Tautan file sudah tidak berlaku, silakan buka ulang', 401);
        }

        $user = User::query()
            ->where('file_token', $token)
            ->where('file_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            throw new ApiException('Tautan file sudah tidak berlaku, silakan buka ulang', 401);
        }

        return $user;
    }

    /**
     * Token file dipertahankan (diperpanjang TTL, BUKAN diganti nilainya)
     * selama masih berlaku -- lihat komentar panjang di auth.php lama: akun
     * yang sama dipakai di lebih dari satu device/tab sekaligus (polling
     * live-update tiap ~8 detik) tidak boleh saling menimpa token satu sama
     * lain, atau URL file yang sudah ditampilkan di device lain langsung
     * gagal dimuat (401) walau TTL-nya sendiri belum habis.
     */
    public function getOrCreateFileToken(User $user): string
    {
        $ttl = config('suratapp.file_token_ttl_seconds');

        if ($user->file_token && $user->file_token_expires_at && $user->file_token_expires_at->isFuture()) {
            DB::table('users')->where('id', $user->id)->update([
                'file_token_expires_at' => now()->addSeconds($ttl),
            ]);

            return $user->file_token;
        }

        $token = bin2hex(random_bytes(32));
        DB::table('users')->where('id', $user->id)->update([
            'file_token' => $token,
            'file_token_expires_at' => now()->addSeconds($ttl),
        ]);

        return $token;
    }

    public function generateAuthToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
