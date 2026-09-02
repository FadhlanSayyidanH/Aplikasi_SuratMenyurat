<?php

namespace App\Services;

use App\Models\BagKasiGrup;
use App\Models\BagKeluar;
use App\Models\BagMasuk;
use App\Models\BagMember;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi 1:1 dari backend/config/bag.php (proyek PHP lama). Helper
 * terpusat untuk "Bag" -- pengelompokan user ke bagian yang bisa diatur
 * admin lewat panel, dipakai bareng oleh BagController (CRUD admin) dan
 * SuratController (bangun rantai approval jalur Surat Keluar + gerbang
 * generik Turmin/Kasubdit Surat Masuk).
 *
 * `bag_keluar` (jalur Surat Keluar, dipakai bag_member/bag_kasi_grup) dan
 * `bag_masuk` (target/gerbang disposisi Surat Masuk, dipakai
 * bag_disposisi_anggota + turmin_user_id/kasubdit_user_id) SENGAJA dua
 * tabel TERPISAH -- lihat komentar migration masing-masing untuk alasan
 * lengkapnya.
 */
class BagService
{
    /** Semua Bag jalur Surat Keluar beserta anggotanya (terurut), bentuk siap-JSON. */
    public function semuaBagKeluar(): array
    {
        $bags = BagKeluar::query()->orderBy('nama')->get()->keyBy('id')->map(fn (BagKeluar $b) => [
            'id' => $b->id,
            'nama' => $b->nama,
            'created_at' => optional($b->created_at)->format('Y-m-d H:i:s'),
            'anggota' => [],
            'kasi_grup' => [],
        ])->all();

        if (!$bags) {
            return [];
        }

        $bagIds = array_keys($bags);

        // Anggota DATAR (kasi_grup_id IS NULL) -- Kaur di bawah satu Kasi
        // TIDAK ikut di sini, muncul bersarang di kasi_grup di bawah.
        $anggotaRows = BagMember::query()
            ->whereIn('bag_id', $bagIds)
            ->whereNull('kasi_grup_id')
            ->with('user:id,username,nama')
            ->orderBy('bag_id')->orderBy('urutan')->orderBy('id')
            ->get();
        foreach ($anggotaRows as $row) {
            $bags[$row->bag_id]['anggota'][] = [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'username' => $row->user->username,
                'nama' => $row->user->nama,
                'urutan' => $row->urutan,
            ];
        }

        // "Grup dalam grup" -- tiap Kasi beserta Kaur di bawahnya.
        $grupRows = BagKasiGrup::query()
            ->whereIn('bag_id', $bagIds)
            ->with('kasi:id,username,nama')
            ->orderBy('bag_id')->orderBy('urutan')->orderBy('id')
            ->get();
        $grupBagId = [];
        $grupById = [];
        foreach ($grupRows as $g) {
            $grupBagId[$g->id] = $g->bag_id;
            $grupById[$g->id] = [
                'id' => $g->id,
                'urutan' => $g->urutan,
                'kasi' => [
                    'id' => $g->kasi->id,
                    'username' => $g->kasi->username,
                    'nama' => $g->kasi->nama,
                ],
                'kaur' => [],
            ];
        }

        if ($grupBagId) {
            $kaurRows = BagMember::query()
                ->whereIn('kasi_grup_id', array_keys($grupBagId))
                ->with('user:id,username,nama')
                ->orderBy('kasi_grup_id')->orderBy('urutan')->orderBy('id')
                ->get();
            foreach ($kaurRows as $row) {
                $grupById[$row->kasi_grup_id]['kaur'][] = [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'username' => $row->user->username,
                    'nama' => $row->user->nama,
                    'urutan' => $row->urutan,
                ];
            }
        }

        foreach ($grupById as $grupId => $grup) {
            $bagId = $grupBagId[$grupId];
            if (isset($bags[$bagId])) {
                $bags[$bagId]['kasi_grup'][] = $grup;
            }
        }

        return array_values($bags);
    }

    /** Semua Bag tujuan/gerbang Surat Masuk beserta penerima disposisinya, bentuk siap-JSON. */
    public function semuaBagMasuk(): array
    {
        $bags = BagMasuk::query()
            ->with(['turmin:id,username,nama', 'kasubdit:id,username,nama', 'kabag:id,username,nama'])
            ->orderBy('nama')
            ->get()
            ->keyBy('id')
            ->map(fn (BagMasuk $b) => [
                'id' => $b->id,
                'nama' => $b->nama,
                'created_at' => optional($b->created_at)->format('Y-m-d H:i:s'),
                'turmin' => $b->turmin ? ['id' => $b->turmin->id, 'username' => $b->turmin->username, 'nama' => $b->turmin->nama] : null,
                'kasubdit' => $b->kasubdit ? ['id' => $b->kasubdit->id, 'username' => $b->kasubdit->username, 'nama' => $b->kasubdit->nama] : null,
                'kabag' => $b->kabag ? ['id' => $b->kabag->id, 'username' => $b->kabag->username, 'nama' => $b->kabag->nama] : null,
                'anggota_masuk' => [],
            ])->all();

        if (!$bags) {
            return [];
        }

        $rows = DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->whereIn('bda.bag_id', array_keys($bags))
            ->orderBy('bda.bag_id')->orderBy('bda.urutan')->orderBy('bda.id')
            ->select('bda.id', 'bda.bag_id', 'bda.urutan', 'u.id as user_id', 'u.username', 'u.nama')
            ->get();
        foreach ($rows as $row) {
            $bags[$row->bag_id]['anggota_masuk'][] = [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'username' => $row->username,
                'nama' => $row->nama,
                'urutan' => $row->urutan,
            ];
        }

        return array_values($bags);
    }

    /** Anggota terurut satu Bag keluar (id, user_id, username, nama, urutan), termasuk anggota bersarang. */
    public function anggotaBag(int $bagId): array
    {
        return BagMember::query()
            ->where('bag_id', $bagId)
            ->with('user:id,username,nama')
            ->orderBy('urutan')->orderBy('id')
            ->get()
            ->map(fn (BagMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'username' => $m->user->username,
                'nama' => $m->user->nama,
                'urutan' => $m->urutan,
            ])->all();
    }

    /**
     * Rantai approval LENGKAP (nama, urut) untuk satu jalur Surat Keluar.
     *
     * @throws \RuntimeException kalau Bag/pilihan Kaur tidak valid
     */
    public function rantaiKeluarUntukBag(string $namaBag, ?int $kaurMemberId): array
    {
        $bag = BagKeluar::query()->where('nama', $namaBag)->first();
        if (!$bag) {
            throw new \RuntimeException("Bagian yang dituju tidak valid: $namaBag");
        }

        $rantai = [];

        $adaKasiGrup = BagKasiGrup::query()->where('bag_id', $bag->id)->exists();

        if ($adaKasiGrup) {
            if (!$kaurMemberId) {
                throw new \RuntimeException('Pilih Kaur yang menyiapkan surat ini terlebih dahulu');
            }
            $kaurRow = DB::table('bag_member as bm')
                ->join('users as u', 'u.id', '=', 'bm.user_id')
                ->join('bag_kasi_grup as g', 'g.id', '=', 'bm.kasi_grup_id')
                ->join('users as uk', 'uk.id', '=', 'g.kasi_user_id')
                ->where('bm.id', $kaurMemberId)
                ->where('g.bag_id', $bag->id)
                ->select('u.nama as kaur_nama', 'uk.nama as kasi_nama')
                ->first();
            if (!$kaurRow) {
                throw new \RuntimeException('Kaur yang dipilih tidak valid untuk Bagian ini');
            }
            $rantai[] = $kaurRow->kaur_nama;
            $rantai[] = $kaurRow->kasi_nama;
        }

        $sisa = BagMember::query()
            ->where('bag_id', $bag->id)
            ->whereNull('kasi_grup_id')
            ->with('user:id,nama')
            ->orderBy('urutan')->orderBy('id')
            ->get();
        foreach ($sisa as $row) {
            $rantai[] = $row->user->nama;
        }

        if (!$rantai) {
            throw new \RuntimeException("Bagian yang dituju belum punya anggota: $namaBag");
        }

        return $rantai;
    }

    /** Anggota terurut satu Bag masuk UNTUK KEPERLUAN DISPOSISI (bag_disposisi_anggota). */
    public function anggotaBagUntukDisposisi(int $bagId): array
    {
        return DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->where('bda.bag_id', $bagId)
            ->orderBy('bda.urutan')->orderBy('bda.id')
            ->select('bda.id', 'u.id as user_id', 'u.username', 'u.nama', 'bda.urutan')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'username' => $row->username,
                'nama' => $row->nama,
                'urutan' => $row->urutan,
            ])->all();
    }

    /** true kalau $userId terdaftar sebagai kasubdit_user_id di Bag masuk MANAPUN. */
    public function akunKasubditManapun(int $userId): bool
    {
        return BagMasuk::query()->where('kasubdit_user_id', $userId)->exists();
    }

    /**
     * Kasubdit SPESIFIK yang berwenang atas surat masuk yang diinput $userId
     * (akun Turmin) -- null kalau tidak terdaftar di Bag manapun, atau
     * menunjuk ke Kasubdit yang berbeda-beda (ambigu).
     */
    public function deteksiKasubditUntukTurmin(int $userId): ?array
    {
        $rows = DB::table('bag_masuk as bm')
            ->join('users as uk', 'uk.id', '=', 'bm.kasubdit_user_id')
            ->where('bm.turmin_user_id', $userId)
            ->distinct()
            ->select('uk.id', 'uk.username', 'uk.nama')
            ->get();

        if ($rows->count() !== 1) {
            return null;
        }
        $row = $rows->first();

        return ['id' => $row->id, 'username' => $row->username, 'nama' => $row->nama];
    }

    /** true kalau $userId adalah kasubdit_user_id Bag masuk $bagId. */
    public function bagMilikKasubdit(int $bagId, int $userId): bool
    {
        return BagMasuk::query()->where('id', $bagId)->where('kasubdit_user_id', $userId)->exists();
    }

    /**
     * Nama akun Kabag Bag masuk $bagId, null kalau Bag ini belum diatur
     * Kabag-nya (fallback ke perilaku lama: blast langsung ke seluruh
     * bag_disposisi_anggota -- lihat pemanggil di App\Livewire\SuratReview
     * & Api\SuratController).
     */
    public function kabagNamaUntukBag(int $bagId): ?string
    {
        return DB::table('bag_masuk as bm')
            ->join('users as uk', 'uk.id', '=', 'bm.kabag_user_id')
            ->where('bm.id', $bagId)
            ->value('uk.nama');
    }

    /**
     * Bag masuk yang Kabag-nya adalah akun bernama $nama -- dipakai untuk
     * menampilkan checkbox "teruskan ke anggota mana" di kartu disposisi
     * Kabag (App\Livewire\SuratReview). Ambil yang PERTAMA kalau satu akun
     * kebetulan jadi Kabag lebih dari satu Bag (tidak ada constraint unik,
     * sama seperti Turmin/Kasubdit) -- kasus normalnya satu akun Kabag
     * cuma pegang satu Bag.
     */
    public function bagUntukKabagNama(string $nama): ?array
    {
        $bag = DB::table('bag_masuk as bm')
            ->join('users as uk', 'uk.id', '=', 'bm.kabag_user_id')
            ->where('uk.nama', $nama)
            ->select('bm.id', 'bm.nama')
            ->first();

        if (!$bag) {
            return null;
        }

        // Kabag bisa saja SUDAH terdaftar sebagai bag_disposisi_anggota di
        // bag-nya sendiri (dari sebelum fitur ini ada, saat Kasubdit masih
        // blast ke semua anggota termasuk Kabag). Buang dari daftar checkbox
        // "teruskan ke" -- meneruskan surat ke diri sendiri tidak masuk akal
        // (baris disposisi milik Kabag sendiri sudah otomatis dibuat).
        $anggota = array_values(array_filter(
            $this->anggotaBagUntukDisposisi((int) $bag->id),
            fn ($a) => $a['nama'] !== $nama
        ));

        return [
            'id' => $bag->id,
            'nama' => $bag->nama,
            'anggota_masuk' => $anggota,
        ];
    }

    /**
     * Kabag $kabagNama menyetel daftar anggota bag-nya sendiri yang jadi
     * tujuan disposisi surat $suratId -- REKONSILIASI penuh terhadap SUBSET
     * anggota bag_disposisi_anggota bag milik Kabag ini (TIDAK bisa ke Bag
     * lain, TIDAK menyentuh baris Kabag sendiri / Kasubditbinum / approval /
     * anggota Bag lain / tembusan-manual luar struktur):
     *
     *  - anggota dicentang & belum punya baris  -> baris surat_disposisi baru
     *  - anggota bag ini yang TIDAK dicentang & sudah punya baris tapi BELUM
     *    direspon (catatan kosong & diproses_oleh null) -> barisnya DIHAPUS
     *    (inilah perbaikan bug "Kabag uncheck kaur tapi tidak bisa overwrite"
     *    -- dulu fungsi ini insert-only, uncheck tidak berefek apa pun)
     *  - anggota bag ini yang tidak dicentang tapi SUDAH mengisi disposisinya
     *    -> DIBIARKAN (jangan hapus respons/jejak audit), namanya dikembalikan
     *    di 'dipertahankan' supaya caller bisa memberi tahu Kabag
     *
     * Idempotent & aman dipanggil setiap Kabag menekan Simpan, termasuk saat
     * $userIdsTerpilih kosong (berarti Kabag membatalkan semua anggota).
     * Anggota di $userIdsTerpilih yang bukan bagian bag ini diabaikan diam-diam.
     *
     * @param  int[]  $userIdsTerpilih
     * @return array{ditambah: string[], dihapus: string[], dipertahankan: string[]}
     */
    public function teruskanDisposisiKabag(int $suratId, string $kabagNama, array $userIdsTerpilih): array
    {
        $kosong = ['ditambah' => [], 'dihapus' => [], 'dipertahankan' => []];

        $bag = $this->bagUntukKabagNama($kabagNama);
        if (!$bag) {
            return $kosong;
        }

        $idTerpilih = array_map('intval', $userIdsTerpilih);
        $ditambah = $dihapus = $dipertahankan = [];

        foreach ($bag['anggota_masuk'] as $anggota) {
            $nama = $anggota['nama'];
            $dipilih = in_array((int) $anggota['user_id'], $idTerpilih, true);
            $row = DB::table('surat_disposisi')
                ->where('surat_id', $suratId)->where('role', $nama)
                ->first(['catatan', 'diproses_oleh']);

            if ($dipilih) {
                if (!$row) {
                    DB::table('surat_disposisi')->insert(['surat_id' => $suratId, 'role' => $nama, 'ditambah_oleh' => $kabagNama]);
                    $ditambah[] = $nama;
                }

                continue;
            }

            if (!$row) {
                continue;
            }

            $sudahRespon = ($row->catatan !== null && trim($row->catatan) !== '') || $row->diproses_oleh !== null;
            if ($sudahRespon) {
                $dipertahankan[] = $nama;

                continue;
            }

            DB::table('surat_disposisi')->where('surat_id', $suratId)->where('role', $nama)->delete();
            $dihapus[] = $nama;
        }

        return ['ditambah' => $ditambah, 'dihapus' => $dihapus, 'dipertahankan' => $dipertahankan];
    }

    /**
     * id semua Bag masuk tempat akun bernama $nama terdaftar sebagai
     * Penerima Disposisi (bag_disposisi_anggota). Dipakai untuk membatasi
     * "teruskan ke rekan sebag" hanya ke sesama Penerima Disposisi Bag yang
     * sama -- lihat teruskanDisposisiAntarAnggota() & App\Livewire\SuratReview.
     *
     * @return int[]
     */
    public function bagDisposisiIdsUntukNama(string $nama): array
    {
        return DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->where('u.nama', $nama)
            ->pluck('bda.bag_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Sesama Penerima Disposisi meneruskan surat $suratId ke rekan SATU Bag
     * yang sama ($dariNama harus terdaftar di bag_disposisi_anggota). Beda
     * dari teruskanDisposisiKabag():
     *  - target valid = sesama bag_disposisi_anggota Bag(-Bag) milik $dariNama,
     *    minus dirinya sendiri. Kabag TIDAK termasuk.
     *  - anggota boleh MEMBATALKAN (uncheck) hanya baris yang DIA sendiri
     *    tambahkan (`ditambah_oleh === $dariNama`) dan yang penerimanya BELUM
     *    mengisi disposisi. Baris milik Kabag / gerbang / yang sudah direspon
     *    tidak tersentuh.
     *
     * @param  int[]  $userIdsTerpilih
     * @return array{ditambah: string[], dihapus: string[], ditolak: int[]}
     */
    public function teruskanDisposisiAntarAnggota(int $suratId, string $dariNama, array $userIdsTerpilih): array
    {
        $kosong = ['ditambah' => [], 'dihapus' => [], 'ditolak' => []];

        $bagIds = $this->bagDisposisiIdsUntukNama($dariNama);
        if (!$bagIds) {
            return $kosong;
        }

        $dariUserId = (int) DB::table('users')->where('nama', $dariNama)->value('id');

        // Sesama Penerima Disposisi Bag-Bag itu (kecuali $dariNama sendiri).
        $rekan = DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->whereIn('bda.bag_id', $bagIds)
            ->where('u.id', '!=', $dariUserId)
            ->distinct()
            ->pluck('u.nama', 'u.id'); // [user_id => nama]

        $idTerpilih = array_map('intval', $userIdsTerpilih);
        $ditambah = $dihapus = $ditolak = [];

        // Tambah: dicentang, rekan sebag valid, belum punya baris.
        foreach ($idTerpilih as $uid) {
            if (!$rekan->has($uid)) {
                $ditolak[] = $uid;

                continue;
            }
            $nama = $rekan->get($uid);
            $ada = DB::table('surat_disposisi')->where('surat_id', $suratId)->where('role', $nama)->exists();
            if (!$ada) {
                DB::table('surat_disposisi')->insert(['surat_id' => $suratId, 'role' => $nama, 'ditambah_oleh' => $dariNama]);
                $ditambah[] = $nama;
            }
        }

        // Batalkan: rekan sebag valid yang TIDAK dicentang, barisnya dibuat
        // $dariNama sendiri, dan penerimanya belum merespon.
        foreach ($rekan as $uid => $nama) {
            if (in_array((int) $uid, $idTerpilih, true)) {
                continue;
            }
            $row = DB::table('surat_disposisi')
                ->where('surat_id', $suratId)->where('role', $nama)
                ->first(['catatan', 'diproses_oleh', 'ditambah_oleh']);
            if (!$row || $row->ditambah_oleh !== $dariNama) {
                continue;
            }
            $sudahRespon = ($row->catatan !== null && trim($row->catatan) !== '') || $row->diproses_oleh !== null;
            if ($sudahRespon) {
                continue;
            }
            DB::table('surat_disposisi')->where('surat_id', $suratId)->where('role', $nama)->delete();
            $dihapus[] = $nama;
        }

        return ['ditambah' => $ditambah, 'dihapus' => $dihapus, 'ditolak' => $ditolak];
    }

    /** true kalau $nama valid sebagai role di surat_disposisi. */
    public function namaValidUntukDisposisi(string $nama): bool
    {
        if ($nama === 'Kasubditbinum') {
            return true;
        }

        $anggotaBiasa = DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->where('u.nama', $nama)
            ->exists();
        if ($anggotaBiasa) {
            return true;
        }

        // Kabag SENDIRI tidak pernah terdaftar di bag_disposisi_anggota
        // (kolomnya sendiri, bag_masuk.kabag_user_id) -- tanpa ini,
        // Kabag tidak bisa mengisi disposisinya sendiri sama sekali
        // (ditolak "Pejabat tujuan disposisi tidak valid" sebelum sempat
        // sampai ke logika teruskanDisposisiKabag()).
        $kabag = DB::table('bag_masuk as bm')
            ->join('users as uk', 'uk.id', '=', 'bm.kabag_user_id')
            ->where('uk.nama', $nama)
            ->exists();
        if ($kabag) {
            return true;
        }

        // Tembusan Manual (SuratReview::simpan()) -- akun bebas pilih di
        // luar struktur Bag, tidak terdaftar di bag_disposisi_anggota
        // ataupun sebagai Kabag mana pun. Cek generik lewat surat_disposisi
        // saja (tanpa surat_id -- fungsi ini memang tidak menerimanya):
        // aman karena pemanggil (SuratReview::simpanDisposisi() &
        // Api\SuratController::updateDisposisi()) SELALU diikuti pengecekan
        // baris surat_disposisi utk surat_id spesifik tepat setelah ini,
        // jadi kombinasi keduanya tetap terikat ke surat yang benar.
        return DB::table('surat_disposisi')->where('role', $nama)->exists();
    }

    /**
     * Deteksi otomatis jalur Surat Keluar akun $userId sendiri (Bag mana +
     * id bag_member Kaur-nya kalau relevan). Null = tidak bisa dipastikan
     * tunggal, client jatuh ke pemilihan manual.
     */
    public function deteksiJalurKeluarAkun(int $userId): ?array
    {
        [$bagIds, $kaurMemberIdPerBag] = $this->bagIdsUntukUser($userId);

        if (count($bagIds) !== 1) {
            return null;
        }
        $bagId = array_key_first($bagIds);
        $kaurMemberId = $kaurMemberIdPerBag[$bagId] ?? null;

        if ($kaurMemberId === null) {
            $butuhKaur = BagKasiGrup::query()->where('bag_id', $bagId)->exists();
            if ($butuhKaur) {
                return null;
            }
        }

        $nama = BagKeluar::query()->find($bagId)?->nama;

        return ['bag_id' => $bagId, 'bag_nama' => $nama, 'kaur_member_id' => $kaurMemberId];
    }

    /** Nama SATU Bag jalur Surat Keluar tempat $userId terlibat, dipakai menu "Progress Surat Keluar". */
    public function bagKeluarSaya(int $userId): ?string
    {
        [$bagIds] = $this->bagIdsUntukUser($userId);

        if (count($bagIds) !== 1) {
            return null;
        }

        return BagKeluar::query()->find(array_key_first($bagIds))?->nama;
    }

    /**
     * @return array{0: array<int, bool>, 1: array<int, int>} [bagIds (set), kaurMemberIdPerBag]
     */
    private function bagIdsUntukUser(int $userId): array
    {
        $bagIds = [];
        $kaurMemberIdPerBag = [];

        $nested = DB::table('bag_member as bm')
            ->join('bag_kasi_grup as g', 'g.id', '=', 'bm.kasi_grup_id')
            ->where('bm.user_id', $userId)
            ->select('bm.id', 'g.bag_id')
            ->get();
        foreach ($nested as $row) {
            $bagIds[$row->bag_id] = true;
            $kaurMemberIdPerBag[$row->bag_id] = $row->id;
        }

        $flat = BagMember::query()->where('user_id', $userId)->whereNull('kasi_grup_id')->pluck('bag_id');
        foreach ($flat as $bagId) {
            $bagIds[$bagId] = true;
        }

        $kasi = BagKasiGrup::query()->where('kasi_user_id', $userId)->pluck('bag_id');
        foreach ($kasi as $bagId) {
            $bagIds[$bagId] = true;
        }

        return [$bagIds, $kaurMemberIdPerBag];
    }
}
