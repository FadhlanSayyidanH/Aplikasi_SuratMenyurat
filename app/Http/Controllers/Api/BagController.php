<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\Concerns\ValidatesLikeOldPhp;
use App\Http\Controllers\Controller;
use App\Models\BagKeluar;
use App\Models\BagMasuk;
use App\Services\ActivityLogger;
use App\Services\BagService;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/bag_list.php, bag_create.php, bag_update.php,
 * bag_delete.php, jalur_keluar_saya.php (proyek PHP lama).
 *
 * bag_keluar (jalur Surat Keluar) & bag_masuk (gerbang Surat Masuk) SEPENUHNYA
 * terpisah (lihat komentar BagService) -- endpoint create/update/delete di
 * sini melayani KEDUANYA lewat flag `untuk_keluar`, persis proyek lama.
 */
class BagController extends Controller
{
    use ValidatesLikeOldPhp;

    public function __construct(private BagService $bagService) {}

    /**
     * GET /bag_list.php?mode=keluar|masuk -- WAJIB bisa diakses semua akun
     * login (bukan cuma admin), dipakai form Surat Keluar & checkbox
     * disposisi Surat Masuk. CRUD Bag tetap admin-only (rute lain di bawah).
     */
    public function list(Request $request)
    {
        $mode = $request->query('mode', '');

        if ($mode === 'keluar') {
            return response()->json($this->bagService->semuaBagKeluar());
        }
        if ($mode === 'masuk') {
            return response()->json($this->bagService->semuaBagMasuk());
        }

        throw new ApiException('Parameter mode wajib "keluar" atau "masuk"', 400);
    }

    /** POST /bag_create.php -- admin-only. */
    public function create(Request $request)
    {
        $authUser = $request->user();

        $nama = trim((string) $request->input('nama', ''));
        // Menentukan tabel target (bag_keluar vs bag_masuk) -- SEPENUHNYA
        // menentukan identitas Bag ini sejak dibuat, tidak bisa diubah
        // belakangan.
        $untukKeluar = $this->phpBool($request->input('untuk_keluar'), true);

        if ($nama === '') {
            throw new ApiException('Nama Bag wajib diisi', 400);
        }

        $model = $untukKeluar ? BagKeluar::class : BagMasuk::class;

        if ($model::query()->where('nama', $nama)->exists()) {
            throw new ApiException('Sudah ada Bag dengan nama tersebut di konteks ini', 409);
        }

        $bag = $model::query()->create(['nama' => $nama]);

        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'create', "Tambah Bag \"$nama\"");

        return response()->json($untukKeluar
            ? ['id' => $bag->id, 'nama' => $nama, 'anggota' => [], 'kasi_grup' => []]
            : ['id' => $bag->id, 'nama' => $nama, 'turmin' => null, 'kasubdit' => null, 'anggota_masuk' => []]);
    }

    /** POST /bag_update.php -- admin-only. */
    public function update(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        $nama = trim((string) $request->input('nama', ''));
        // bag_keluar & bag_masuk punya id space independen -- WAJIB tahu
        // tabel mana yang dituju id ini.
        $untukKeluar = $this->phpBool($request->input('untuk_keluar'), true);

        if (!$id) {
            throw new ApiException('ID Bag tidak valid', 400);
        }
        if ($nama === '') {
            throw new ApiException('Nama Bag wajib diisi', 400);
        }

        $model = $untukKeluar ? BagKeluar::class : BagMasuk::class;

        $bag = $model::query()->find($id);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        if ($model::query()->where('nama', $nama)->where('id', '!=', $id)->exists()) {
            throw new ApiException('Sudah ada Bag dengan nama tersebut di konteks ini', 409);
        }

        $namaLama = $bag->nama;
        $bag->nama = $nama;
        $bag->save();

        ActivityLogger::log(
            $request, $authUser->username, $authUser->nama, 'update',
            "Ubah Bag \"$namaLama\" menjadi \"$nama\""
        );

        return response()->json(['id' => $id, 'nama' => $nama]);
    }

    /**
     * POST /bag_delete.php -- admin-only. Menghapus Bag TIDAK menyentuh
     * data surat historis (surat_approval/surat_disposisi.role sudah
     * snapshot string nama terpisah). bag_member/bag_kasi_grup (bag_keluar)
     * atau bag_disposisi_anggota (bag_masuk) ikut terhapus lewat FK ON
     * DELETE CASCADE -- Bag di tabel LAINNYA (walau nama sama) tidak
     * tersentuh.
     */
    public function delete(Request $request)
    {
        $authUser = $request->user();

        $id = $this->phpValidateInt($request->input('id'));
        $untukKeluar = $this->phpBool($request->input('untuk_keluar'), true);

        if (!$id) {
            throw new ApiException('ID Bag tidak valid', 400);
        }

        $model = $untukKeluar ? BagKeluar::class : BagMasuk::class;

        $bag = $model::query()->find($id);
        if (!$bag) {
            throw new ApiException('Bag tidak ditemukan', 404);
        }

        $nama = $bag->nama;
        $bag->delete();

        ActivityLogger::log($request, $authUser->username, $authUser->nama, 'delete', "Hapus Bag \"$nama\"");

        return response()->json(['ok' => true]);
    }

    /**
     * GET /jalur_keluar_saya.php -- semua akun login (bukan admin-only),
     * dipakai surat_form_screen.dart (deteksi otomatis) & dashboard_screen
     * .dart (menu "Progress Surat Keluar", field progress_bag_nama TERPISAH
     * dari bag_id/bag_nama/kaur_member_id, jangan dicampur).
     */
    public function jalurKeluarSaya(Request $request)
    {
        $authUser = $request->user();

        $hasil = $this->bagService->deteksiJalurKeluarAkun((int) $authUser->id);
        $progressBagNama = $this->bagService->bagKeluarSaya((int) $authUser->id);

        return response()->json([
            'bag_id' => $hasil['bag_id'] ?? null,
            'bag_nama' => $hasil['bag_nama'] ?? null,
            'kaur_member_id' => $hasil['kaur_member_id'] ?? null,
            'progress_bag_nama' => $progressBagNama,
        ]);
    }
}
