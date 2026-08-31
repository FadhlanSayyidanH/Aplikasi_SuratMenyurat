<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\PimpinanAnggota;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/pimpinan_list.php,
 * pimpinan_anggota_add.php, pimpinan_anggota_remove.php (proyek PHP lama).
 *
 * "Struktur Anggota" -- jajaran akun di bawah satu akun role='pimpinan'
 * (tabel pimpinan_anggota). PURELY untuk menyaring tampilan menu sidebar
 * "Surat Masuk Jajaran" seorang Pimpinan di sisi Flutter -- TIDAK dipakai
 * sebagai batas otorisasi di endpoint mana pun (lihat backend/config/pimpinan.php
 * lama). Karena itu pimpinan_list.php bisa diakses semua akun yang login
 * (bukan cuma admin), sedangkan CRUD-nya (add/remove) tetap admin-only.
 */
class PimpinanController extends Controller
{
    /**
     * GET pimpinan_list.php -- semua akun role='pimpinan' beserta
     * jajarannya (terurut nama), dapat diakses semua akun yang login.
     */
    public function list()
    {
        $daftar = [];

        $pimpinanUsers = User::query()
            ->where('role', 'pimpinan')
            ->orderBy('nama')
            ->get(['id', 'username', 'nama']);

        foreach ($pimpinanUsers as $user) {
            $daftar[$user->id] = [
                'id' => $user->id,
                'username' => $user->username,
                'nama' => $user->nama,
                'anggota' => [],
            ];
        }

        if ($daftar) {
            $anggotaRows = PimpinanAnggota::query()
                ->join('users', 'users.id', '=', 'pimpinan_anggota.anggota_id')
                ->orderBy('users.nama')
                ->get([
                    'pimpinan_anggota.id as id',
                    'pimpinan_anggota.pimpinan_id as pimpinan_id',
                    'users.id as user_id',
                    'users.username as username',
                    'users.nama as nama',
                ]);

            foreach ($anggotaRows as $row) {
                $pimpinanId = (int) $row->pimpinan_id;
                if (!isset($daftar[$pimpinanId])) {
                    continue;
                }
                $daftar[$pimpinanId]['anggota'][] = [
                    'id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'username' => $row->username,
                    'nama' => $row->nama,
                ];
            }
        }

        return response()->json(array_values($daftar));
    }

    /**
     * POST pimpinan_anggota_add.php -- admin only. Tambah satu akun ke
     * jajaran satu akun role='pimpinan'.
     */
    public function add(Request $request)
    {
        $authUser = $request->user();

        $pimpinanId = $this->normalizeId($request->input('pimpinan_id'));
        $anggotaId = $this->normalizeId($request->input('anggota_id'));

        if (!$pimpinanId || !$anggotaId) {
            throw new ApiException('Akun Pimpinan dan anggota wajib dipilih', 400);
        }

        $pimpinan = User::query()->find($pimpinanId, ['username', 'nama', 'role']);

        if (!$pimpinan) {
            throw new ApiException('Akun Pimpinan tidak ditemukan', 404);
        }
        if ($pimpinan->role !== 'pimpinan') {
            throw new ApiException('Akun yang dipilih bukan akun role Pimpinan', 400);
        }

        if ($anggotaId === $pimpinanId) {
            throw new ApiException('Akun Pimpinan tidak bisa jadi anggota jajarannya sendiri', 400);
        }

        $user = User::query()->find($anggotaId, ['username', 'nama', 'role']);

        if (!$user) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        // Admin tidak pernah cocok dengan tujuan disposisi manapun (cuma
        // label tampilan biasa) -- menambahkannya ke jajaran Pimpinan
        // tidak ada gunanya.
        if ($user->role === 'admin') {
            throw new ApiException('Akun role admin tidak bisa jadi anggota jajaran', 400);
        }

        // Satu akun BOLEH jadi anggota jajaran lebih dari satu Pimpinan
        // sekaligus -- yang dicegah di sini cuma duplikat di jajaran
        // Pimpinan YANG SAMA (constraint uniq_pimpinan_anggota).
        $existing = PimpinanAnggota::query()
            ->where('pimpinan_id', $pimpinanId)
            ->where('anggota_id', $anggotaId)
            ->exists();

        if ($existing) {
            throw new ApiException("{$user->nama} sudah jadi anggota jajaran {$pimpinan->nama}", 409);
        }

        $row = PimpinanAnggota::query()->create([
            'pimpinan_id' => $pimpinanId,
            'anggota_id' => $anggotaId,
        ]);

        ActivityLogger::log(
            $request,
            $authUser->username,
            $authUser->nama,
            'update',
            "Tambah {$user->nama} ({$user->username}) ke jajaran {$pimpinan->nama}",
        );

        return response()->json([
            'id' => $row->id,
            'user_id' => $anggotaId,
            'username' => $user->username,
            'nama' => $user->nama,
        ]);
    }

    /**
     * POST pimpinan_anggota_remove.php -- admin only. Keluarkan satu akun
     * dari jajaran Pimpinan.
     */
    public function remove(Request $request)
    {
        $authUser = $request->user();

        $id = $this->normalizeId($request->input('id'));

        if (!$id) {
            throw new ApiException('ID anggota tidak valid', 400);
        }

        $old = PimpinanAnggota::query()
            ->join('users as u', 'u.id', '=', 'pimpinan_anggota.anggota_id')
            ->join('users as p', 'p.id', '=', 'pimpinan_anggota.pimpinan_id')
            ->where('pimpinan_anggota.id', $id)
            ->first([
                'u.username as username',
                'u.nama as nama',
                'p.nama as nama_pimpinan',
            ]);

        if (!$old) {
            throw new ApiException('Anggota tidak ditemukan', 404);
        }

        PimpinanAnggota::query()->where('id', $id)->delete();

        ActivityLogger::log(
            $request,
            $authUser->username,
            $authUser->nama,
            'update',
            "Keluarkan {$old->nama} ({$old->username}) dari jajaran {$old->nama_pimpinan}",
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Setara filter_var($value, FILTER_VALIDATE_INT) lalu pengecekan
     * falsy (!$id) di PHP lama -- string non-integer atau 0 dianggap
     * tidak valid.
     */
    private function normalizeId(mixed $value): ?int
    {
        if (is_bool($value) || is_array($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        if ($filtered === false || $filtered === 0) {
            return null;
        }

        return $filtered;
    }
}
