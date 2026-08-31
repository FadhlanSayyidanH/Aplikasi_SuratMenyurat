<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\Concerns\ValidatesLikeOldPhp;
use App\Http\Controllers\Controller;
use App\Models\BagDisposisiAnggota;
use App\Models\BagMasuk;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/bag_disposisi_anggota_add.php,
 * bag_disposisi_anggota_remove.php, bag_disposisi_anggota_update.php,
 * bag_set_turmin.php, bag_set_kasubdit.php (proyek PHP lama) -- gerbang
 * disposisi Surat Masuk (bag_masuk), SEPENUHNYA LEPAS dari bag_member/
 * bag_kasi_grup (rantai approval Surat Keluar Bag yang sama, lihat
 * BagMemberController), admin-only.
 */
class BagMasukController extends Controller
{
    use ValidatesLikeOldPhp;

    /**
     * POST /bag_disposisi_anggota_add.php. CATATAN: beda dengan
     * bag_member_add.php, proyek PHP lama TIDAK memaksa re-login di sini --
     * jadi penerima disposisi tidak mengubah hak akses akun (bukan gerbang
     * seperti turmin_user_id/kasubdit_user_id), sengaja TIDAK ada
     * invalidasi token.
     */
    public function disposisiAnggotaAdd(Request $request)
    {
        $authUser = $request->user();

        $bagId = $this->phpValidateInt($request->input('bag_id'));
        $userId = $this->phpValidateInt($request->input('user_id'));
        $urutanRaw = $this->phpValidateInt($this->inputOrDefault($request, 'urutan', 1));
        $urutan = $urutanRaw === false ? 0 : $urutanRaw;

        if (!$bagId || !$userId) {
            throw new ApiException('Bag dan user wajib dipilih', 400);
        }

        $bag = BagMasuk::query()->find($bagId);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        $user = User::query()->find($userId);
        if (!$user) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        if ($user->role === 'admin') {
            throw new ApiException('Akun role admin tidak bisa jadi penerima disposisi', 400);
        }

        $existing = BagDisposisiAnggota::query()->where('bag_id', $bagId)->where('user_id', $userId)->exists();
        if ($existing) {
            throw new ApiException("{$user->nama} sudah jadi penerima disposisi Bag \"{$bag->nama}\"", 409);
        }

        $row = BagDisposisiAnggota::query()->create([
            'bag_id' => $bagId,
            'user_id' => $userId,
            'urutan' => $urutan,
        ]);

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Tambah {$user->nama} ({$user->username}) ke penerima disposisi Bag \"{$bag->nama}\""
        );

        return response()->json([
            'id' => $row->id,
            'user_id' => $userId,
            'username' => $user->username,
            'nama' => $user->nama,
            'urutan' => $urutan,
        ]);
    }

    /** POST /bag_disposisi_anggota_remove.php -- tidak menyentuh bag_member/bag_kasi_grup Bag yang sama. */
    public function disposisiAnggotaRemove(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        if (!$id) {
            throw new ApiException('ID anggota tidak valid', 400);
        }

        $old = BagDisposisiAnggota::query()->with(['user:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            throw new ApiException('Anggota tidak ditemukan', 404);
        }

        $namaUser = $old->user->nama;
        $usernameUser = $old->user->username;
        $namaBag = $old->bag->nama;

        $old->delete();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Keluarkan {$namaUser} ({$usernameUser}) dari penerima disposisi Bag \"{$namaBag}\""
        );

        return response()->json(['ok' => true]);
    }

    /** POST /bag_disposisi_anggota_update.php -- ubah urutan, dipakai drag-and-drop reorder panel admin. */
    public function disposisiAnggotaUpdate(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        $urutan = $this->phpValidateInt($request->input('urutan'));

        if (!$id || $urutan === false) {
            throw new ApiException('ID anggota dan urutan wajib diisi', 400);
        }

        $old = BagDisposisiAnggota::query()->with(['user:id,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            throw new ApiException('Anggota tidak ditemukan', 404);
        }

        $namaUser = $old->user->nama;
        $namaBag = $old->bag->nama;

        $old->urutan = $urutan;
        $old->save();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Ubah urutan {$namaUser} di penerima disposisi Bag \"{$namaBag}\" jadi $urutan"
        );

        return response()->json(['id' => $id, 'urutan' => $urutan]);
    }

    /** POST /bag_set_turmin.php -- gerbang Surat Masuk khusus role 'turmin'. */
    public function setTurmin(Request $request)
    {
        return $this->setGateSlot($request, 'turmin_user_id', 'turmin', 'Turmin');
    }

    /** POST /bag_set_kasubdit.php -- gerbang Surat Masuk khusus role 'pimpinan'. */
    public function setKasubdit(Request $request)
    {
        return $this->setGateSlot($request, 'kasubdit_user_id', 'pimpinan', 'Kasubdit');
    }

    /**
     * bag_set_turmin.php & bag_set_kasubdit.php sama persis strukturnya di
     * proyek lama (hanya kolom target & role yang beda) -- disatukan di
     * sini supaya tidak duplikasi, tapi pesan error/response tetap identik
     * baris-per-baris dengan aslinya.
     */
    private function setGateSlot(Request $request, string $column, string $requiredRole, string $label)
    {
        $authUser = $request->user();

        $bagId = $this->phpValidateInt($request->input('bag_id'));
        // user_id boleh null/kosong untuk mengosongkan slot.
        $userIdRaw = $request->input('user_id');
        $userId = $userIdRaw === null ? null : $this->phpValidateInt($userIdRaw);

        if (!$bagId) {
            throw new ApiException('Bag tidak valid', 400);
        }
        if ($userIdRaw !== null && !$userId) {
            throw new ApiException('User tidak valid', 400);
        }

        $bag = BagMasuk::query()->find($bagId);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        $user = null;
        if ($userId) {
            $user = User::query()->find($userId);
            if (!$user) {
                throw new ApiException('User tidak ditemukan', 404);
            }
            if ($user->role !== $requiredRole) {
                throw new ApiException("Akun $label harus akun dengan role \"$requiredRole\"", 400);
            }
        }

        $oldUserId = $bag->{$column};
        $oldUsername = $oldUserId ? User::query()->find($oldUserId)?->username : null;

        $bag->{$column} = $userId;
        $bag->save();

        // Ganti slot gerbang berdampak ke hak akses -- paksa re-login akun
        // LAMA (kehilangan slot ini) & akun BARU (dapat slot ini).
        if ($oldUsername !== null) {
            User::query()->where('username', $oldUsername)->update(['token' => null, 'token_expires_at' => null]);
        }
        if ($user !== null) {
            User::query()->where('username', $user->username)->update(['token' => null, 'token_expires_at' => null]);
        }

        $keterangan = $user !== null
            ? "Atur Akun $label Bag \"{$bag->nama}\" jadi {$user->nama} ({$user->username})"
            : "Kosongkan Akun $label Bag \"{$bag->nama}\"";
        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'update', $keterangan);

        return response()->json([
            'bag_id' => $bagId,
            strtolower($label) => $user !== null ? ['id' => $user->id, 'username' => $user->username, 'nama' => $user->nama] : null,
        ]);
    }
}
