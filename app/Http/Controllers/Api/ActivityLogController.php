<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/activity_log_list.php, activity_log_clear.php
 * (proyek PHP lama). Kedua endpoint admin-only -- lihat require_admin() di
 * backend/config/auth.php lama, sekarang ditegakkan lewat middleware
 * auth.token + auth.admin pada routes/api_activitylog.php.
 */
class ActivityLogController extends Controller
{
    public function list(Request $request)
    {
        // LIMIT sebagai jaring pengaman -- tabel ini SATU-SATUNYA yang
        // tumbuh terus tanpa batas seiring waktu (mencatat tiap
        // login/create/update/delete di seluruh aplikasi). 2000 baris
        // terbaru jauh lebih dari cukup untuk kebutuhan audit praktis
        // Administrator, dan mencegah respons ini tumbuh tanpa batas kalau
        // log-nya sudah terkumpul bertahun-tahun.
        $rows = ActivityLog::query()
            ->orderByDesc('waktu')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        // Bentuk manual (bukan andalkan serialisasi default Eloquent) supaya
        // 'waktu' tetap berupa string "Y-m-d H:i:s" apa adanya seperti hasil
        // mysqli fetch_assoc() di PHP lama, bukan ISO-8601 (format default
        // cast 'datetime' Eloquent saat di-JSON-kan).
        $data = $rows->map(fn (ActivityLog $row) => [
            'id' => $row->id,
            'surat_id' => $row->surat_id,
            'waktu' => $row->waktu->format('Y-m-d H:i:s'),
            'username' => $row->username,
            'nama' => $row->nama,
            'aksi' => $row->aksi,
            'keterangan' => $row->keterangan,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
        ]);

        return response()->json($data);
    }

    public function clear(Request $request)
    {
        $user = $request->user();

        $total = ActivityLog::query()->count();

        ActivityLog::query()->delete();

        // Log "log dihapus" tetap tercatat -- dibuat SETELAH penghapusan
        // supaya log tidak pernah benar-benar kosong, selalu menyisakan
        // jejak siapa & kapan log dibersihkan.
        ActivityLogger::log(
            $request,
            $user->username,
            $user->nama,
            'clear_log',
            "Menghapus $total catatan log sebelumnya",
        );

        return response()->json(['cleared' => $total]);
    }
}
