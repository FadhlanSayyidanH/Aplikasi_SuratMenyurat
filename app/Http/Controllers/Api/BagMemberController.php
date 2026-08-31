<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\Concerns\ValidatesLikeOldPhp;
use App\Http\Controllers\Controller;
use App\Models\BagKasiGrup;
use App\Models\BagKeluar;
use App\Models\BagMember;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/bag_member_add.php, bag_member_remove.php,
 * bag_member_update.php, bag_kasi_grup_create.php, bag_kasi_grup_delete.php
 * (proyek PHP lama) -- rantai approval Surat Keluar (bag_member/
 * bag_kasi_grup, "grup dalam grup"), admin-only.
 */
class BagMemberController extends Controller
{
    use ValidatesLikeOldPhp;

    /** POST /bag_member_add.php */
    public function add(Request $request)
    {
        $authUser = $request->user();

        $bagId = $this->phpValidateInt($request->input('bag_id'));
        $userId = $this->phpValidateInt($request->input('user_id'));
        $urutanRaw = $this->phpValidateInt($this->inputOrDefault($request, 'urutan', 1));
        $urutan = $urutanRaw === false ? 0 : $urutanRaw;
        // Kalau diisi: baris ini seorang KAUR ditambahkan ke DALAM satu
        // grup Kasi ("grup dalam grup") alih-alih jadi anggota datar Bag
        // itu sendiri -- $bagId di atas TETAP wajib diisi juga.
        $kasiGrupIdRaw = $this->phpValidateInt($request->input('kasi_grup_id'));
        $kasiGrupId = $kasiGrupIdRaw ?: null;

        if (!$bagId || !$userId) {
            throw new ApiException('Bag dan user wajib dipilih', 400);
        }

        $bag = BagKeluar::query()->find($bagId);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        if ($kasiGrupId !== null) {
            $grup = BagKasiGrup::query()->find($kasiGrupId);
            if (!$grup || (int) $grup->bag_id !== $bagId) {
                throw new ApiException('Grup Kasi tidak valid untuk Bag ini', 400);
            }
        }

        $user = User::query()->find($userId);
        if (!$user) {
            throw new ApiException('User tidak ditemukan', 404);
        }
        // Admin tidak pernah cocok dengan tahap approval/disposisi manapun
        // -- menambahkannya ke Bag cuma akan membuat tahap yang tidak
        // pernah bisa dipenuhi siapa pun.
        if ($user->role === 'admin') {
            throw new ApiException('Akun role admin tidak bisa jadi anggota Bag', 400);
        }

        // Satu user BOLEH jadi anggota lebih dari satu Bag sekaligus -- yang
        // dicegah di sini cuma duplikat di Bag YANG SAMA (uniq_bag_user).
        $existing = BagMember::query()->where('bag_id', $bagId)->where('user_id', $userId)->exists();
        if ($existing) {
            throw new ApiException("{$user->nama} sudah jadi anggota Bag \"{$bag->nama}\"", 409);
        }

        $member = BagMember::query()->create([
            'bag_id' => $bagId,
            'user_id' => $userId,
            'urutan' => $urutan,
            'kasi_grup_id' => $kasiGrupId,
        ]);

        // Paksa re-login supaya sesi yang sedang aktif langsung mendapat
        // hak akses terbaru (mis. bisa_input_keluar).
        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Tambah {$user->nama} ({$user->username}) ke Bag \"{$bag->nama}\""
        );

        return response()->json([
            'id' => $member->id,
            'user_id' => $userId,
            'username' => $user->username,
            'nama' => $user->nama,
            'urutan' => $urutan,
        ]);
    }

    /** POST /bag_member_remove.php */
    public function remove(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        if (!$id) {
            throw new ApiException('ID anggota tidak valid', 400);
        }

        $old = BagMember::query()->with(['user:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            throw new ApiException('Anggota tidak ditemukan', 404);
        }

        $userId = $old->user_id;
        $namaUser = $old->user->nama;
        $usernameUser = $old->user->username;
        $namaBag = $old->bag->nama;

        $old->delete();

        $target = User::query()->find($userId);
        if ($target) {
            $target->token = null;
            $target->token_expires_at = null;
            $target->save();
        }

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Keluarkan {$namaUser} ({$usernameUser}) dari Bag \"{$namaBag}\""
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /bag_member_update.php -- ubah urutan SATU anggota. CATATAN:
     * proyek PHP lama TIDAK memaksa re-login di sini (beda dengan add/
     * remove/kasi_grup_create/kasi_grup_delete) -- perubahan urutan saja
     * tidak mengubah hak akses, jadi sengaja TIDAK ada invalidasi token.
     */
    public function update(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        $urutan = $this->phpValidateInt($request->input('urutan'));

        if (!$id || $urutan === false) {
            throw new ApiException('ID anggota dan urutan wajib diisi', 400);
        }

        $old = BagMember::query()->with(['user:id,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            throw new ApiException('Anggota tidak ditemukan', 404);
        }

        $namaBag = $old->bag->nama;
        $namaUser = $old->user->nama;

        $old->urutan = $urutan;
        $old->save();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Ubah urutan {$namaUser} di Bag \"{$namaBag}\" jadi $urutan"
        );

        return response()->json(['id' => $id, 'urutan' => $urutan]);
    }

    /**
     * POST /bag_kasi_grup_create.php -- buat satu grup Kasi baru di dalam
     * satu Bag. Kaur ditambahkan belakangan satu-satu lewat
     * bag_member_add.php (param kasi_grup_id).
     */
    public function kasiGrupCreate(Request $request)
    {
        $authUser = $request->user();

        $bagId = $this->phpValidateInt($request->input('bag_id'));
        $kasiUserId = $this->phpValidateInt($request->input('kasi_user_id'));

        if (!$bagId || !$kasiUserId) {
            throw new ApiException('Bag dan akun Kasi wajib dipilih', 400);
        }

        $bag = BagKeluar::query()->find($bagId);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        $user = User::query()->find($kasiUserId);
        if (!$user) {
            throw new ApiException('Akun tidak ditemukan', 404);
        }
        if ($user->role === 'admin') {
            throw new ApiException('Akun role admin tidak bisa jadi Kasi', 400);
        }

        $existing = BagKasiGrup::query()->where('bag_id', $bagId)->where('kasi_user_id', $kasiUserId)->exists();
        if ($existing) {
            throw new ApiException("{$user->nama} sudah jadi Kasi di Bag \"{$bag->nama}\"", 409);
        }

        $urutan = (int) (BagKasiGrup::query()->where('bag_id', $bagId)->max('urutan')) + 1;

        $grup = BagKasiGrup::query()->create([
            'bag_id' => $bagId,
            'kasi_user_id' => $kasiUserId,
            'urutan' => $urutan,
        ]);

        // Paksa re-login supaya sesi yang sedang aktif langsung mendapat
        // hak akses terbaru -- pola sama seperti bag_member_add.php.
        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Tambah grup Kasi {$user->nama} ({$user->username}) ke Bag \"{$bag->nama}\""
        );

        return response()->json([
            'id' => $grup->id,
            'urutan' => $urutan,
            'kasi' => ['id' => $kasiUserId, 'username' => $user->username, 'nama' => $user->nama],
            'kaur' => [],
        ]);
    }

    /**
     * POST /bag_kasi_grup_delete.php -- hapus satu grup Kasi. SELURUH Kaur
     * di bawahnya ikut terhapus (FK bag_member.kasi_grup_id ON DELETE
     * CASCADE), bukan dipindah jadi anggota datar.
     */
    public function kasiGrupDelete(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        if (!$id) {
            throw new ApiException('ID grup tidak valid', 400);
        }

        $old = BagKasiGrup::query()->with(['kasi:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->kasi || !$old->bag) {
            throw new ApiException('Grup tidak ditemukan', 404);
        }

        $kasiUserId = $old->kasi_user_id;
        $kasiUsername = $old->kasi->username;
        $kasiNama = $old->kasi->nama;
        $namaBag = $old->bag->nama;

        $kaurUserIds = BagMember::query()->where('kasi_grup_id', $id)->pluck('user_id')->all();

        $old->delete();

        // Paksa re-login Kasi & seluruh Kaur yang barusan ikut terhapus.
        $idsToInvalidate = [...$kaurUserIds, $kasiUserId];
        User::query()->whereIn('id', $idsToInvalidate)->update(['token' => null, 'token_expires_at' => null]);

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Hapus grup Kasi {$kasiNama} ({$kasiUsername}) beserta ".count($kaurUserIds)
                ." Kaur dari Bag \"{$namaBag}\""
        );

        return response()->json(['ok' => true]);
    }
}
