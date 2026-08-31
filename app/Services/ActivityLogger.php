<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/config/activity_log.php (proyek PHP lama) --
 * mencatat satu baris aktivitas (login, logout, create, update, delete,
 * clear_log) ke tabel activity_log, termasuk IP & user-agent pelaku.
 */
class ActivityLogger
{
    /**
     * IP pelaku untuk kolom activity_log.ip_address -- SENGAJA pakai
     * $request->ip() (REMOTE_ADDR dari koneksi TCP asli) LANGSUNG, BUKAN
     * header X-Forwarded-For. Nginx di server produksi ini menghadap
     * internet LANGSUNG (bukan di belakang reverse proxy/CDN tepercaya
     * lain yang legitimately menyetel header itu), jadi X-Forwarded-For
     * di request MASUK cuma nilai bebas yang dikirim client itu sendiri --
     * mempercayainya berarti siapa pun bisa memalsukan IP yang tercatat di
     * log aktivitas cuma dengan menyetel header itu sendiri.
     */
    public static function clientIp(Request $request): string
    {
        return $request->ip() ?? 'unknown';
    }

    public static function log(
        Request $request,
        ?string $username,
        string $nama,
        string $aksi,
        ?string $keterangan = null,
        ?int $suratId = null,
    ): void {
        ActivityLog::create([
            'surat_id' => $suratId,
            'username' => $username,
            'nama' => $nama,
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'ip_address' => self::clientIp($request),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
