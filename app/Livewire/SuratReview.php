<?php

namespace App\Livewire;

use App\Livewire\Concerns\MengelolaRantaiSuratKeluar;
use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\SuratFile;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BagService;
use App\Services\BaseUrlResolver;
use App\Services\SuratFileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Ruang kerja tinjau/approval satu surat -- migrasi dari
 * lib/screens/surat_review_screen.dart (proyek Flutter lama). SELURUH
 * aturan bisnis di sini SENGAJA disalin baris-per-baris dari
 * App\Http\Controllers\Api\SuratController (updateStatus, updateDisposisi,
 * approvalLompat, approvalMundur, konfirmasiKaur, resubmit, deleteDitolak,
 * editResetProgress, recomputeStatusSurat) -- BUKAN memanggil controller
 * itu / endpoint API lewat HTTP, karena komponen ini jalan di sesi web
 * (Livewire), bukan lewat token bearer API.
 *
 * Viewer/editor dokumen (OnlyOffice, coret-coret foto) SENGAJA disederhanakan
 * jadi tautan "Buka" ke route('surat-file.editor'/'surat-file.annotate') --
 * navigasi di tab yang sama, bukan dialog pratinjau PDF/gambar terpisah.
 */
#[Layout('layouts.app', ['title' => 'Tinjau Surat'])]
class SuratReview extends Component
{
    use WithFileUploads;
    use MengelolaRantaiSuratKeluar;

    public Surat $surat;

    // --- Ubah Rantai Proses (lihat bisaEditRantai()) ---
    public bool $sedangEditRantai = false;

    /**
     * Snapshot rantai persetujuan ASLI (nama & status) saat panel "Ubah
     * Rantai Proses" dibuka -- dipakai simpanEditRantai() membandingkan
     * posisi-per-posisi dengan $rantaiManual, DAN dipakai blade menandai
     * tahap mana yang "akan tetap" kalau tidak disentuh (lihat
     * mulaiEditRantai()). Setiap elemen: ['nama' => string, 'status' => string].
     */
    public array $rantaiManualAsal = [];

    // --- Form Keputusan (approve/reject tahap milik akun ini) ---
    public ?string $keputusan = null;

    public string $catatan = '';

    /** @var array<int, string> kode instruksi terpilih (gerbang Kasubdit Surat Masuk) */
    public array $instruksiDisposisi = [];

    /** @var array<int, int> id Bag tujuan disposisi terpilih (gerbang Kasubdit Surat Masuk) */
    public array $bagTujuanTerpilih = [];

    public bool $isSubmitting = false;

    public ?string $error = null;

    // --- Isi Disposisi per pejabat tujuan (Surat Masuk) ---
    /** @var array<string, string> nama => catatan yang sedang diketik */
    public array $disposisiCatatan = [];

    /**
     * @var array<string, int[]> nama Kabag => user_id anggota bag-nya yang
     *      dicentang untuk diteruskan (checkbox baru di kartu disposisi
     *      Kabag) -- lihat simpanDisposisi() & BagService::teruskanDisposisiKabag().
     */
    public array $kabagAnggotaTerpilih = [];

    /**
     * @var array<string, int[]> nama pemilik kartu disposisi (Penerima
     *      Disposisi biasa) => user_id rekan SATU Bag yang dicentang untuk
     *      diteruskan -- lihat simpanDisposisi() &
     *      BagService::teruskanDisposisiAntarAnggota(). Beda dari
     *      $kabagAnggotaTerpilih: ini untuk anggota biasa, bukan Kabag.
     */
    public array $rekanSebagTerpilih = [];

    /** @var array<string, bool> */
    public array $isSubmittingRole = [];

    /** @var array<string, string|null> */
    public array $errorRole = [];

    /**
     * Tembusan manual (gerbang Kasubdit Surat Masuk) -- akun BEBAS pilih di
     * luar struktur Bag/Kabag di atas, tetap ikut jadi target
     * surat_disposisi dan bisa isi disposisinya sendiri lewat mekanisme
     * "Isi Disposisi per Pejabat" yang SUDAH ADA (tidak berubah). Sengaja
     * tidak dipakai bareng $rantaiManual/$cariUserManual milik
     * MengelolaRantaiSuratKeluar -- itu untuk rantai APPROVAL berurutan
     * Surat Keluar, semantiknya beda (tembusan di sini paralel/independen,
     * bukan bergiliran).
     *
     * @var array<int, array{user_id:int, nama:string}>
     */
    public array $tembusanManualTerpilih = [];

    public string $cariUserTembusan = '';

    // --- Bag tujuan disposisi (gerbang Kasubdit) ---
    /** @var array<int, array> */
    public array $bags = [];

    public bool $sedangMuatBag = true;

    public ?string $errorMuatBag = null;

    // --- Ambil Alih / Ambil Alih untuk Revisi ---
    public bool $sedangAmbilAlih = false;

    public bool $sedangBukaRevisi = false;

    // --- Ajukan Ulang / Hapus Surat (Surat Keluar Ditolak) ---
    public bool $sedangAjukanUlang = false;

    public bool $sedangHapusSurat = false;

    public ?string $errorAjukanUlang = null;

    // --- Konfirmasi/Abaikan notifikasi Kaur ---
    public bool $sedangKonfirmasiKaur = false;

    // --- Kelola file lampiran (Surat Keluar) ---
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public $newFiles = [];

    public bool $sedangUnggahFile = false;

    public ?int $sedangHapusFileId = null;

    public ?string $errorFile = null;

    public ?int $editingFileId = null;

    public string $editingFileName = '';

    // --- Edit data awal surat (perbaiki salah ketik, dsb) ---
    public bool $sedangEditData = false;

    public string $editNomorSurat = '';

    public string $editNamaPengaju = '';

    public string $editTanggal = '';

    public string $editKlasifikasi = '';

    public string $editKlasifikasiLainnya = '';

    public string $editPerihal = '';

    public string $editKeterangan = '';

    public ?string $errorEditData = null;

    // --- Riwayat aktivitas ---
    public bool $showRiwayat = false;

    /** @var array<int, object> */
    public array $riwayat = [];

    public function mount(Surat $surat): void
    {
        $this->authorizeAccess($surat);
        $this->surat = $surat;

        $tahapSaya = $this->cariTahapSaya();
        if ($tahapSaya && $tahapSaya->status !== 'menunggu') {
            // Revisi: pra-isi form dengan keputusan/catatan yang pernah dibuat
            // sendiri sebelumnya, supaya terlihat apa yang mau diubah.
            $this->keputusan = $tahapSaya->status;
            $this->instruksiDisposisi = $tahapSaya->instruksi ? explode(',', $tahapSaya->instruksi) : [];
            $this->catatan = (string) ($tahapSaya->catatan ?? '');
        } elseif (!$tahapSaya && $surat->jenis === 'keluar' && $surat->status !== 'menunggu') {
            // Surat keluar lama tanpa rantai approval per-tahap.
            $this->keputusan = $surat->status;
            $this->catatan = (string) ($surat->catatan_proses ?? '');
        }

        $namaSudahDidisposisi = $this->disposisiRows()->pluck('role')->all();
        foreach ($this->disposisiRows() as $d) {
            $this->disposisiCatatan[$d->role] = $d->catatan ?? '';

            // Pra-centang checkbox "teruskan ke" kalau $d->role akun Kabag
            // -- supaya Kabag lihat siapa saja yang SUDAH diteruskan
            // sebelumnya (bukan cuma daftar kosong tiap buka ulang).
            $kabagInfo = $this->kabagInfoUntuk($d->role);
            if ($kabagInfo) {
                $this->kabagAnggotaTerpilih[$d->role] = collect($kabagInfo['anggota_masuk'])
                    ->filter(fn ($a) => in_array($a['nama'], $namaSudahDidisposisi, true))
                    ->pluck('user_id')->all();

                continue;
            }

            // Pra-centang "teruskan ke rekan sebag" untuk kartu milik akun
            // yang sedang login (Penerima Disposisi biasa) -- rekan yang
            // SUDAH punya baris tampil tercentang (yang terkunci tetap
            // tercentang; yang "punya + hak + belum respon" bisa di-uncheck).
            if (Auth::user() && Auth::user()->nama === $d->role) {
                $rekanInfo = $this->rekanSebagUntuk($d->role);
                if ($rekanInfo) {
                    $this->rekanSebagTerpilih[$d->role] = collect($rekanInfo['anggota'])
                        ->filter(fn ($a) => $a['punya_baris'])
                        ->pluck('user_id')->all();
                }
            }
        }

        $this->muatBagDisposisi();
    }

    /**
     * Batas keamanan sama seperti SuratController::list() -- akun bukan
     * admin/pimpinan hanya boleh membuka surat yang benar-benar melibatkan
     * mereka (salah satu tahap approval, tujuan disposisi, atau pernah
     * memproses tahapnya).
     */
    private function authorizeAccess(Surat $surat): void
    {
        abort_unless($surat->bolehDiaksesOleh(Auth::user()), 403, 'Anda tidak berhak melihat surat ini.');
    }

    /** wire:poll -- live update ringan, cuma menyegarkan field baca $surat (status dsb), TIDAK menyentuh input form yang sedang diketik. */
    public function pollRefresh(): void
    {
        $fresh = Surat::find($this->surat->id);
        if ($fresh) {
            $this->surat = $fresh;
        }
    }

    private function reloadAfterAction(): void
    {
        $this->surat = Surat::findOrFail($this->surat->id);
    }

    // =========================================================================
    // Query helper (selalu baca langsung dari DB supaya selalu "live" --
    // Livewire me-render ulang seluruh komponen tiap interaksi, jadi tidak
    // perlu cache manual).
    // =========================================================================

    public function approvalRows()
    {
        return DB::table('surat_approval')->where('surat_id', $this->surat->id)->orderBy('urutan')->get();
    }

    /**
     * Nama approver PALING AKHIR di rantai approval Surat Keluar surat ini
     * (urutan tertinggi) -- BUKAN selalu Kasubditbinum lagi, karena sejak
     * SuratForm punya opsi "berhenti di sini" (lihat SuratForm::submit()),
     * rantai bisa dipotong lebih pendek dan berhenti di siapa saja (mis.
     * Kabag). Dipakai bisaEditApproval()/terkunciSetelahDisetujui() untuk
     * menentukan siapa yang MASIH boleh mengubah keputusan setelah surat
     * disetujui sepenuhnya -- SELALU approver akhir surat ITU SENDIRI,
     * bukan role sistem 'pimpinan' yang cuma kebetulan cocok kalau
     * rantainya penuh sampai Kasubditbinum.
     */
    public function approverAkhirNama(): ?string
    {
        if ($this->surat->jenis !== 'keluar') {
            return null;
        }

        return $this->approvalRows()->sortByDesc('urutan')->first()->role ?? null;
    }

    public function disposisiRows()
    {
        return DB::table('surat_disposisi')->where('surat_id', $this->surat->id)->orderBy('id')->get();
    }

    /**
     * null kalau $nama BUKAN akun Kabag terdaftar di Bag masuk manapun,
     * kalau IYA kembalikan info Bag-nya (termasuk anggota_masuk untuk
     * checkbox "teruskan ke" di kartu disposisi Kabag) -- lihat
     * resources/views/livewire/partials/surat-review-form-masuk.blade.php.
     */
    public function kabagInfoUntuk(string $nama): ?array
    {
        return app(BagService::class)->bagUntukKabagNama($nama);
    }

    /**
     * null kalau $nama BUKAN Penerima Disposisi (bag_disposisi_anggota) Bag
     * manapun. Kalau IYA, kembalikan daftar rekan SATU Bag yang bisa dituju
     * "teruskan ke rekan sebag" -- lihat kartu disposisi di
     * resources/views/livewire/partials/surat-review-form-masuk.blade.php.
     *
     * `bisa_uncheck` = baris rekan itu ADA, dibuat oleh $nama sendiri
     * (ditambah_oleh === $nama), dan penerimanya BELUM merespon -- hanya
     * itu yang boleh dibatalkan anggota (baris Kabag/gerbang/sudah-direspon
     * terkunci).
     *
     * @return array{bag: string, anggota: array<int, array{user_id:int, nama:string, punya_baris:bool, bisa_uncheck:bool}>}|null
     */
    public function rekanSebagUntuk(string $nama): ?array
    {
        $svc = app(BagService::class);
        $bagIds = $svc->bagDisposisiIdsUntukNama($nama);
        if (!$bagIds) {
            return null;
        }

        $namaBag = DB::table('bag_masuk')->whereIn('id', $bagIds)->orderBy('nama')->value('nama') ?? '';

        $dariUserId = (int) DB::table('users')->where('nama', $nama)->value('id');
        $rekanRows = DB::table('bag_disposisi_anggota as bda')
            ->join('users as u', 'u.id', '=', 'bda.user_id')
            ->whereIn('bda.bag_id', $bagIds)
            ->where('u.id', '!=', $dariUserId)
            ->orderBy('bda.urutan')->orderBy('bda.id')
            ->select('u.id as user_id', 'u.nama')
            ->distinct()
            ->get();

        $disposisi = DB::table('surat_disposisi')
            ->where('surat_id', $this->surat->id)
            ->get(['role', 'catatan', 'diproses_oleh', 'ditambah_oleh'])
            ->keyBy('role');

        $anggota = [];
        $sudahAda = [];
        foreach ($rekanRows as $r) {
            if (isset($sudahAda[$r->user_id])) {
                continue;
            }
            $sudahAda[$r->user_id] = true;

            $row = $disposisi->get($r->nama);
            $punyaBaris = $row !== null;
            $bisaUncheck = false;
            if ($punyaBaris) {
                $belumRespon = ($row->catatan === null || trim($row->catatan) === '') && $row->diproses_oleh === null;
                $bisaUncheck = $row->ditambah_oleh === $nama && $belumRespon;
            }

            $anggota[] = [
                'user_id' => (int) $r->user_id,
                'nama' => $r->nama,
                'punya_baris' => $punyaBaris,
                'bisa_uncheck' => $bisaUncheck,
            ];
        }

        return ['bag' => $namaBag, 'anggota' => $anggota];
    }

    /** @return array<int, array> bentuk siap-tampil sama persis seperti detailResponse() API. */
    public function fileRows(): array
    {
        $baseUrl = BaseUrlResolver::resolve(request());

        return app(SuratFileService::class)->getSuratFiles($this->surat->id, $baseUrl, Auth::user());
    }

    public function disposisiNamaMentah(): array
    {
        return DB::table('surat_disposisi')->where('surat_id', $this->surat->id)->pluck('role')->all();
    }

    private function fileExt(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    public function isFoto(string $name): bool
    {
        return in_array($this->fileExt($name), ['jpg', 'jpeg', 'png'], true);
    }

    public function isPdf(string $name): bool
    {
        return $this->fileExt($name) === 'pdf';
    }

    // =========================================================================
    // Cermin persis getter Dart di surat_review_screen.dart / Surat model.
    // =========================================================================

    private function bolehKlaimKasubditMasuk(): bool
    {
        return Auth::user()->role === 'pimpinan';
    }

    public function cariTahapSaya(): ?object
    {
        $nama = Auth::user()->nama;
        $rows = $this->approvalRows();
        foreach ($rows as $row) {
            if ($row->role === $nama) {
                return $row;
            }
        }
        if ($this->surat->jenis === 'masuk' && $this->bolehKlaimKasubditMasuk()) {
            foreach ($rows as $row) {
                if ($row->role === 'Kasubdit' && ($row->status === 'menunggu' || $row->diproses_oleh === $nama)) {
                    return $row;
                }
            }
        }

        return null;
    }

    private function bisaDiprosesOleh(string $nama, bool $bolehKlaimKasubditMasuk = false): bool
    {
        $rows = $this->approvalRows();
        $milikSaya = null;
        foreach ($rows as $row) {
            if ($row->role === $nama) {
                $milikSaya = $row;
                break;
            }
        }
        if (!$milikSaya && $this->surat->jenis === 'masuk' && $bolehKlaimKasubditMasuk) {
            foreach ($rows as $row) {
                if ($row->role === 'Kasubdit' && ($row->status === 'menunggu' || $row->diproses_oleh === $nama)) {
                    $milikSaya = $row;
                    break;
                }
            }
        }
        if (!$milikSaya) {
            return false;
        }
        foreach ($rows as $row) {
            if ($row->urutan < $milikSaya->urutan && $row->status !== 'disetujui') {
                return false;
            }
        }

        return true;
    }

    public function tahapAktif(): ?object
    {
        if ($this->surat->status !== 'menunggu') {
            return null;
        }
        $menunggu = $this->approvalRows()->where('status', 'menunggu');
        if ($menunggu->isEmpty()) {
            return null;
        }

        return $menunggu->sortBy('urutan')->first();
    }

    public function bisaEditApproval(): bool
    {
        $user = Auth::user();
        if ($user->role === 'turmin' && $this->surat->jenis !== 'keluar') {
            return false;
        }
        if (!$this->bisaDiprosesOleh($user->nama, $this->bolehKlaimKasubditMasuk())) {
            return false;
        }
        if ($this->surat->jenis === 'keluar' && $this->surat->status === 'disetujui' && $user->nama !== $this->approverAkhirNama()) {
            return false;
        }

        return true;
    }

    public function terkunciSetelahDisetujui(): bool
    {
        $user = Auth::user();

        return $this->surat->jenis === 'keluar'
            && $this->surat->status === 'disetujui'
            && $user->nama !== $this->approverAkhirNama()
            && $this->bisaDiprosesOleh($user->nama);
    }

    public function bisaProsesBelumGiliran(): bool
    {
        if ($this->surat->jenis !== 'keluar') {
            return false;
        }
        if ($this->surat->status !== 'menunggu') {
            return false;
        }
        $tahapSaya = $this->cariTahapSaya();
        if (!$tahapSaya || $tahapSaya->status !== 'menunggu') {
            return false;
        }

        return !$this->bisaDiprosesOleh(Auth::user()->nama);
    }

    public function isRevisiApproval(): bool
    {
        $tahapSaya = $this->cariTahapSaya();

        return $tahapSaya !== null && $tahapSaya->status !== 'menunggu';
    }

    public function revisiPerluDibukaDulu(): bool
    {
        return $this->surat->jenis === 'keluar' && $this->bisaEditApproval() && $this->isRevisiApproval();
    }

    public function adaTahapSetelahnyaDiproses(): bool
    {
        $tahapSaya = $this->cariTahapSaya();
        if (!$tahapSaya) {
            return false;
        }
        foreach ($this->approvalRows() as $row) {
            if ($row->urutan > $tahapSaya->urutan && $row->status !== 'menunggu') {
                return true;
            }
        }

        return false;
    }

    public function perluKonfirmasiUbahKeputusan(): bool
    {
        return $this->surat->jenis === 'keluar' && $this->isRevisiApproval() && $this->adaTahapSetelahnyaDiproses();
    }

    public function isGateAkhirDisposisi(): bool
    {
        $tahap = $this->cariTahapSaya();

        return $this->surat->jenis === 'masuk' && $tahap !== null && $tahap->role === 'Kasubdit';
    }

    public function gateDisposisiLolos(): bool
    {
        return $this->approvalRows()->isEmpty() || $this->surat->status === 'disetujui';
    }

    public function instruksiDisposisiFinal(): array
    {
        foreach ($this->approvalRows() as $row) {
            if ($row->role === 'Kasubdit') {
                return $row->instruksi ? explode(',', $row->instruksi) : [];
            }
        }

        return [];
    }

    /**
     * true kalau $user (dari $suratRowFresh/$this->surat) adalah Turmin
     * yang MENGINPUT surat masuk ini sendiri, dan gerbang Kasubdit belum
     * memutuskan. Dicek lewat kolom diproses_oleh baris approval urutan
     * PERTAMA (diisi $dibuatOleh = nama akun saat SuratForm::submit()) --
     * BUKAN lewat kecocokan role, karena role gerbang Surat Masuk cuma
     * label generik TETAP 'Turmin'/'Kasubdit' (lihat komentar
     * surat-review-tahap-tile.blade.php), tidak pernah cocok nama akun
     * manapun secara langsung -- itu sebabnya bisaEditApproval() tidak
     * bisa dipakai untuk mengenali "penginput asli".
     *
     * SENGAJA cuma berlaku untuk surat MILIK SENDIRI dan cuma sebelum
     * Kasubdit memutuskan -- TIDAK mengubah aturan Turmin read-only
     * terhadap KEPUTUSAN approve/reject (itu tetap murni hak Kasubdit,
     * lihat bisaEditApproval()); ini cuma jalur terpisah supaya Turmin
     * bisa membetulkan salah input miliknya sendiri sebelum diproses lebih
     * lanjut, bukan ikut memutuskan.
     */
    private function bisaTurminKelolaSuratSendiri(?object $suratRowFresh = null): bool
    {
        $suratId = $suratRowFresh->id ?? $this->surat->id;
        $jenis = $suratRowFresh->jenis ?? $this->surat->jenis;
        $status = $suratRowFresh->status ?? $this->surat->status;

        if ($jenis !== 'masuk' || $status !== 'menunggu') {
            return false;
        }

        $rows = DB::table('surat_approval')->where('surat_id', $suratId)->orderBy('urutan')->get();
        $tahapTurmin = $rows->firstWhere('urutan', 1);
        $tahapKasubdit = $rows->firstWhere('urutan', 2);

        return $tahapTurmin !== null
            && $tahapTurmin->diproses_oleh === Auth::user()->nama
            && $tahapKasubdit !== null
            && $tahapKasubdit->status === 'menunggu';
    }

    /**
     * Boleh kelola lampiran (tambah/hapus/ganti nama) untuk KEDUA jenis
     * surat. Surat Keluar: dibatasi ke pengaju pertama (Kaur) atau pemegang
     * giliran saat ini (SuratFileService::bolehKelolaFileSuratKeluar() --
     * cari baris approval PERTAMA yang statusnya masih 'menunggu').
     *
     * Surat Masuk memakai gerbang yang SAMA dengan Edit Data Surat
     * (bisaEditApproval()) DITAMBAH bisaTurminKelolaSuratSendiri() --
     * true untuk Kasubdit (tahap aktif) MAUPUN Turmin yang menginput surat
     * ini sendiri (selama Kasubdit belum memutuskan). Turmin TETAP tidak
     * bisa mengelola lampiran surat masuk MILIK ORANG LAIN, konsisten
     * dengan sifat read-only-nya terhadap keputusan approve/reject.
     *
     * $suratRowFresh opsional: action methods mutasi (deleteFile/renameFile/
     * uploadFiles) SELALU mengoper baris surat yang baru saja dibaca ULANG
     * langsung dari DB di method masing-masing -- bukan $this->surat yang
     * cuma ter-refresh tiap wire:poll (bisa saja beberapa detik basi) --
     * supaya keputusan izin untuk MUTASI selalu berdasar status TERKINI.
     * Dipanggil TANPA argumen (pakai $this->surat) hanya untuk kebutuhan
     * tampilan (show/hide tombol), yang boleh sedikit basi.
     */
    public function bisaKelolaFile(?object $suratRowFresh = null): bool
    {
        $suratId = $suratRowFresh->id ?? $this->surat->id;
        $jenis = $suratRowFresh->jenis ?? $this->surat->jenis;
        $status = $suratRowFresh->status ?? $this->surat->status;

        if ($status === 'disetujui') {
            return false;
        }

        if ($jenis === 'keluar') {
            return app(SuratFileService::class)->bolehKelolaFileSuratKeluar($suratId, $status, Auth::user());
        }

        // Surat Masuk: tidak ada alur "ajukan ulang" seperti Surat Keluar
        // (bisaAjukanUlang() di bawah masih sengaja keluar-saja, di luar
        // cakupan perubahan ini) -- kelola lampiran dibatasi selagi surat
        // masih berjalan (status 'menunggu').
        return $status === 'menunggu'
            && ($this->bisaEditApproval() || $this->bisaTurminKelolaSuratSendiri($suratRowFresh));
    }

    /**
     * Boleh mengedit data awal (nomor surat, tanggal, klasifikasi, perihal,
     * dst -- lihat simpanEditData()) kalau ada salah ketik. SENGAJA pakai
     * gerbang IZIN YANG SAMA PERSIS dengan bisaEditApproval() (siapa yang
     * berhak memutuskan approve/reject tahap aktif SEKARANG) DITAMBAH
     * bisaTurminKelolaSuratSendiri() (Turmin boleh membetulkan data surat
     * masuk yang dia input sendiri, selama Kasubdit belum memutuskan) --
     * lihat komentar masing-masing method itu untuk alasan lengkap. Otomatis
     * terkunci begitu surat sudah final (disetujui/ditolak) kecuali
     * pimpinan, sama seperti keputusan approval itu sendiri.
     */
    public function bisaEditDataAwal(): bool
    {
        return $this->bisaEditApproval() || $this->bisaTurminKelolaSuratSendiri();
    }

    /**
     * Boleh mengubah rantai proses (lihat mulaiEditRantai()/simpanEditRantai())
     * kalau ini Surat Keluar, MASIH berjalan (status 'menunggu' -- begitu
     * final/disetujui/ditolak, susunan rantai jadi bagian dari riwayat yang
     * tidak boleh diutak-atik lagi), DAN gilirannya sedang di tangan akun
     * ini SEKARANG (gerbang sama persis dengan bisaEditApproval()).
     *
     * Jalur Bag/Kaur BAKU (otomatis) HANYA mengganti tahap yang belum
     * diproses (status 'menunggu') -- tahap sebelumnya yang sudah disetujui
     * tidak tersentuh. Rantai MANUAL beda: orangnya bebas menyusun ulang
     * SELURUH rantai (termasuk tahap yang sudah disetujui) -- keputusan lama
     * dipertahankan HANYA untuk posisi yang persis sama dengan rantai lama;
     * begitu ada yang beda di satu posisi, posisi itu dan seterusnya diganti
     * jadi tahap 'menunggu' baru -- lihat simpanEditRantai().
     */
    public function bisaEditRantai(): bool
    {
        return $this->surat->jenis === 'keluar'
            && $this->surat->status === 'menunggu'
            && $this->bisaEditApproval();
    }

    /**
     * Pratinjau rantai PENGGANTI yang sedang disusun di panel "Ubah Rantai
     * Proses" -- lihat App\Livewire\Concerns\MengelolaRantaiSuratKeluar::hitungAlurRuteKeluar().
     * Cuma dipakai jalur OTOMATIS (blade tidak memakai properti ini untuk
     * mode manual -- itu langsung menampilkan $rantaiManual apa adanya).
     */
    public function getAlurRuteBaruProperty(): array
    {
        if ($this->modeManual) {
            return $this->hitungAlurRuteKeluar();
        }

        return $this->buangYangSudahDisetujui($this->hitungAlurRuteKeluar());
    }

    /**
     * Buang siapa pun di rute PENGGANTI (jalur Bag/Kaur BAKU/otomatis) yang
     * tahapnya sudah 'disetujui' -- BagService::rantaiKeluarUntukBag() SELALU
     * mengembalikan rute LENGKAP dari Kaur (sama seperti dipakai SuratForm
     * bikin surat baru), jadi kalau dipakai APA ADANYA di sini (edit rantai
     * mid-proses) dia akan menyisipkan ulang orang yang tahapnya sudah
     * disetujui sebagai tahap BARU (status 'menunggu') -- surat jadi
     * "mundur" minta approval kedua, alih-alih cuma melanjutkan dari giliran
     * sekarang. Rantai MANUAL TIDAK lewat sini -- itu punya aturannya
     * sendiri (posisi yang sama dipertahankan, lihat simpanEditRantai()).
     */
    private function buangYangSudahDisetujui(array $anggotaBaru): array
    {
        $historiDisetujui = SuratApproval::query()
            ->where('surat_id', $this->surat->id)
            ->where('status', 'disetujui')
            ->pluck('role')
            ->all();

        if (!$historiDisetujui) {
            return $anggotaBaru;
        }

        return array_values(array_filter(
            $anggotaBaru,
            fn (string $nama) => !in_array($nama, $historiDisetujui, true),
        ));
    }

    public function bisaAjukanUlang(): bool
    {
        if ($this->surat->jenis !== 'keluar') {
            return false;
        }
        if ($this->surat->status !== 'ditolak') {
            return false;
        }

        return $this->bisaKelolaFile();
    }

    public function tampilkanFooterTambahFile(): bool
    {
        return $this->bisaKelolaFile() && $this->surat->status !== 'ditolak';
    }

    public function perluKonfirmasiKaur(): bool
    {
        if ($this->surat->jenis !== 'keluar') {
            return false;
        }
        if (!in_array($this->surat->status, ['disetujui', 'ditolak'], true)) {
            return false;
        }
        if ($this->surat->konfirmasi_kaur_at !== null) {
            return false;
        }
        $stage1 = $this->approvalRows()->firstWhere('urutan', 1);

        return $stage1 && $stage1->role === Auth::user()->nama;
    }

    public function labelTahapSebelumnyaMenunggu(): string
    {
        $tahapSaya = $this->cariTahapSaya();
        if (!$tahapSaya) {
            return '';
        }

        return $this->approvalRows()
            ->where('urutan', '<', $tahapSaya->urutan)
            ->where('status', 'menunggu')
            ->pluck('role')->implode(', ');
    }

    /** true kalau mengedit file ini sekarang akan membatalkan keputusan yang sudah ada (perlu konfirmasi eksplisit). */
    public function fileNeedsResetConfirm(): bool
    {
        if ($this->surat->jenis !== 'keluar') {
            return false;
        }
        if ($this->surat->status !== 'menunggu') {
            return false;
        }
        $tahapSaya = $this->cariTahapSaya();
        if (!$tahapSaya) {
            return false;
        }
        if ($tahapSaya->status !== 'menunggu') {
            return true;
        }

        return $this->approvalRows()->where('urutan', '>', $tahapSaya->urutan)->where('status', '!=', 'menunggu')->isNotEmpty();
    }

    // =========================================================================
    // Aksi -- masing-masing cermin 1:1 method SuratController terkait.
    // =========================================================================

    /**
     * Buka form edit data awal (nomor surat, tanggal, klasifikasi, perihal,
     * keterangan, nama pengaju) -- pra-isi dari data surat saat ini. Field
     * yang TIDAK ditawarkan di sini SENGAJA dikecualikan: `jenis` dan
     * `kabag_dituju` (bagian tujuan Surat Keluar) karena mengubahnya berarti
     * membangun ulang seluruh rantai approval dari nol -- di luar cakupan
     * "betulkan salah ketik", kalau jalur/bagiannya sendiri yang keliru itu
     * kasus untuk hapus & buat ulang surat, bukan sekadar edit data.
     */
    public function mulaiEditData(): void
    {
        if (!$this->bisaEditDataAwal()) {
            return;
        }

        $this->errorEditData = null;
        $this->editNomorSurat = (string) ($this->surat->nomor_surat ?? '');
        $this->editNamaPengaju = (string) ($this->surat->nama_pengaju ?? '');
        $this->editTanggal = optional($this->surat->tanggal)->format('Y-m-d') ?? '';
        $this->editPerihal = (string) $this->surat->perihal;
        $this->editKeterangan = (string) ($this->surat->keterangan ?? '');

        $klasifikasiSekarang = (string) ($this->surat->klasifikasi ?? '');
        if ($klasifikasiSekarang !== '' && !in_array($klasifikasiSekarang, Surat::KLASIFIKASI_OPTIONS, true)) {
            // Nilai lama tidak/tidak lagi ada di daftar baku -- perlakukan
            // sebagai "Lainnya" supaya isinya tidak diam-diam hilang.
            $this->editKlasifikasi = Surat::KLASIFIKASI_LAINNYA;
            $this->editKlasifikasiLainnya = $klasifikasiSekarang;
        } else {
            $this->editKlasifikasi = $klasifikasiSekarang !== '' ? $klasifikasiSekarang : Surat::KLASIFIKASI_OPTIONS[0];
            $this->editKlasifikasiLainnya = '';
        }

        $this->sedangEditData = true;
    }

    public function batalEditData(): void
    {
        $this->sedangEditData = false;
        $this->errorEditData = null;
        $this->resetErrorBag();
    }

    /**
     * Simpan perubahan data awal. Validasi & field wajib per jenis SENGAJA
     * meniru persis App\Livewire\Surat\SuratForm (form buat surat baru) --
     * supaya aturannya konsisten antara "buat" dan "edit". TIDAK mengubah
     * status/rantai approval sama sekali -- field-field ini murni deskriptif,
     * bukan bagian dari keputusan yang sudah diberikan pejabat manapun,
     * jadi berbeda dari editDocumentAndOpen() (ganti dokumen) yang memang
     * SENGAJA mereset progress karena isi dokumennya berubah.
     */
    public function simpanEditData(): void
    {
        $this->errorEditData = null;

        if (!$this->bisaEditDataAwal()) {
            $this->errorEditData = 'Anda tidak berhak mengubah data surat ini.';

            return;
        }

        $tanggal = trim($this->editTanggal);
        $perihal = trim($this->editPerihal);
        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !strtotime($tanggal)) {
            $this->errorEditData = 'Tanggal wajib diisi dengan format yang valid';

            return;
        }
        if ($perihal === '') {
            $this->errorEditData = 'Perihal wajib diisi';

            return;
        }

        $klasifikasi = $this->editKlasifikasi === Surat::KLASIFIKASI_LAINNYA
            ? trim($this->editKlasifikasiLainnya)
            : $this->editKlasifikasi;
        if ($klasifikasi === '') {
            $this->errorEditData = 'Klasifikasi surat wajib diisi';

            return;
        }

        $nomorSurat = trim($this->editNomorSurat);
        $namaPengaju = trim($this->editNamaPengaju);
        if ($this->surat->jenis === 'masuk') {
            if ($nomorSurat === '') {
                $this->errorEditData = 'Nomor surat wajib diisi';

                return;
            }
            if ($namaPengaju === '') {
                $this->errorEditData = 'Nama pengirim surat wajib diisi';

                return;
            }
        }

        $this->surat->tanggal = $tanggal;
        $this->surat->perihal = $perihal;
        $this->surat->klasifikasi = $klasifikasi;
        $this->surat->keterangan = $this->editKeterangan !== '' ? $this->editKeterangan : null;
        if ($this->surat->jenis === 'masuk') {
            $this->surat->nomor_surat = $nomorSurat;
            $this->surat->nama_pengaju = $namaPengaju;
        }
        $this->surat->save();

        $user = Auth::user();
        $jenisLabel = $this->surat->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Edit data awal $jenisLabel ({$this->surat->perihal})",
            $this->surat->id,
        );

        $this->sedangEditData = false;
        $this->reloadAfterAction();
    }

    /**
     * Buka panel "Ubah Rantai Proses". Jalur Bag/Kaur BAKU (otomatis)
     * prefill SAMA seperti SuratForm (deteksi otomatis dari Bag/Kaur akun
     * ini kalau ada). Rantai MANUAL beda dari sebelumnya -- sekarang
     * PRA-DIISI dengan seluruh rantai persetujuan surat ini SEKARANG (bukan
     * kosong), supaya orangnya bisa menyunting/menggeser/menghapus/menambah
     * SIAPA SAJA di posisi manapun; $rantaiManualAsal menyimpan salinan
     * snapshotnya untuk dibandingkan posisi-per-posisi oleh simpanEditRantai()
     * (lihat komentarnya) & buat penanda "akan tetap" di blade.
     */
    public function mulaiEditRantai(): void
    {
        if (!$this->bisaEditRantai()) {
            return;
        }

        $this->jalurError = false;
        $this->kaurError = false;
        $this->modeManual = false;
        $this->cariUserManual = '';
        $this->bagTerpilihId = null;
        $this->kaurMemberId = null;
        $this->jalurOtomatis = false;
        $this->bagTerpilihIdOtomatis = null;
        $this->kaurMemberIdOtomatis = null;
        $this->loadBagsKeluar();

        $existing = SuratApproval::query()
            ->where('surat_id', $this->surat->id)
            ->orderBy('urutan')
            ->get(['role', 'status']);

        // role di surat_approval NORMALNYA sama persis dengan users.nama
        // pemiliknya -- dicocokkan sekali lewat query gabungan (bukan per
        // baris) supaya tetap efisien walau rantainya panjang. Kalau nama
        // pejabatnya berubah/terhapus sejak tahap itu tercatat, user_id-nya
        // dibiarkan null (getOpsiUserManualProperty() sudah menyaring null
        // ini, jadi tidak bikin pencarian error).
        $namaKeUserId = User::query()
            ->whereIn('nama', $existing->pluck('role')->unique())
            ->pluck('id', 'nama');

        $this->rantaiManual = $existing->map(fn ($row) => [
            'user_id' => $namaKeUserId[$row->role] ?? null,
            'nama' => $row->role,
        ])->all();
        $this->rantaiManualAsal = $existing->map(fn ($row) => [
            'nama' => $row->role,
            'status' => $row->status,
        ])->all();

        $this->sedangEditRantai = true;
    }

    /**
     * Panjang AWALAN (dari posisi 1) rantai manual yang sedang disusun yang
     * masih PERSIS sama dengan rantai asal (lihat $rantaiManualAsal) --
     * dipakai blade menandai tahap mana yang keputusannya (disetujui/
     * ditolak) akan dipertahankan kalau disimpan sekarang (lihat
     * simpanEditRantai(), pakai logika pencocokan yang SAMA PERSIS).
     */
    public function getPrefixRantaiManualSamaProperty(): int
    {
        $anggota = array_column($this->rantaiManual, 'nama');
        $n = 0;
        foreach ($this->rantaiManualAsal as $asal) {
            if (($anggota[$n] ?? null) !== $asal['nama']) {
                break;
            }
            $n++;
        }

        return $n;
    }

    public function batalEditRantai(): void
    {
        $this->sedangEditRantai = false;
        $this->jalurError = false;
        $this->kaurError = false;
        $this->resetErrorBag();
    }

    /**
     * Simpan rantai baru. Jalur MANUAL dan jalur Bag/Kaur BAKU (otomatis)
     * punya aturan beda -- lihat masing-masing cabang di bawah & komentar
     * bisaEditRantai().
     */
    public function simpanEditRantai(): void
    {
        $this->errorEditData = null;

        if (!$this->bisaEditRantai()) {
            $this->errorEditData = 'Anda tidak berhak mengubah rantai proses surat ini.';

            return;
        }

        $tahapSaya = $this->cariTahapSaya();
        if (!$tahapSaya || $tahapSaya->status !== 'menunggu') {
            $this->errorEditData = 'Giliran Anda pada surat ini sudah tidak aktif.';

            return;
        }

        $user = Auth::user();

        if ($this->modeManual) {
            $this->simpanEditRantaiManual($user);

            return;
        }

        $this->simpanEditRantaiOtomatis($user, $tahapSaya->urutan);
    }

    /**
     * Simpan rantai MANUAL -- orangnya bebas menyusun ulang SELURUH rantai
     * (posisi 1..N), termasuk tahap yang sudah 'disetujui'. Dibandingkan
     * POSISI-PER-POSISI dengan rantai asal ($rantaiManualAsal, snapshot dari
     * saat panel dibuka -- lihat mulaiEditRantai()): AWALAN (dari posisi 1)
     * yang PERSIS sama -- nama & urutannya identik -- dipertahankan apa
     * adanya (baris surat_approval-nya, termasuk status/reviewed_by/
     * reviewed_at/review_note, TIDAK disentuh sama sekali). Begitu ketemu
     * posisi pertama yang beda (digeser/dihapus/diganti), posisi itu DAN
     * SETERUSNYA dihapus lalu diganti baris baru berstatus 'menunggu' sesuai
     * rantai yang baru disusun -- termasuk kalau yang beda itu tahap yang
     * TADINYA sudah disetujui (orangnya SADAR memilih itu, bukan kecelakaan
     * -- lihat getOpsiUserManualProperty() di trait, tidak ada nama yang
     * dikunci).
     */
    private function simpanEditRantaiManual(User $user): void
    {
        $anggotaBaru = array_column($this->rantaiManual, 'nama');
        if (!$anggotaBaru) {
            $this->jalurError = true;

            return;
        }

        $existing = SuratApproval::query()
            ->where('surat_id', $this->surat->id)
            ->orderBy('urutan')
            ->get(['id', 'role']);

        $prefixLen = 0;
        foreach ($existing as $row) {
            if (($anggotaBaru[$prefixLen] ?? null) !== $row->role) {
                break;
            }
            $prefixLen++;
        }

        $idDihapus = $existing->slice($prefixLen)->pluck('id');
        $anggotaBaruSisa = array_slice($anggotaBaru, $prefixLen);

        DB::transaction(function () use ($idDihapus, $anggotaBaruSisa, $prefixLen) {
            if ($idDihapus->isNotEmpty()) {
                SuratApproval::query()->whereIn('id', $idDihapus)->delete();
            }

            foreach ($anggotaBaruSisa as $offset => $role) {
                SuratApproval::query()->create([
                    'surat_id' => $this->surat->id,
                    'urutan' => $prefixLen + $offset + 1,
                    'role' => $role,
                    'status' => 'menunggu',
                ]);
            }

            // Rantai manual tidak "dimiliki" Bag manapun -- sama seperti
            // SuratForm (lihat MengelolaRantaiSuratKeluar), lepas dari
            // apakah rantai lamanya dulu dibuat lewat jalur Bag/Kaur baku.
            $this->surat->kabag_dituju = null;
            $this->surat->save();
        });

        // Kalau semua tahap yang tersisa ternyata sudah 'disetujui' (mis.
        // rantainya dipotong pas berhenti di orang yang sudah setuju semua,
        // tidak ada 'menunggu' baru sama sekali) -- surat ini SELESAI, harus
        // langsung ke-ACC penuh, bukan ngambang di status 'menunggu' tanpa
        // ada lagi yang bisa memprosesnya. simpan() (keputusan biasa) selalu
        // panggil ini setelah menulis surat_approval; edit rantai tadinya
        // TIDAK, itu penyebab bug ini.
        $this->recomputeStatusSurat($this->surat->id);

        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Ubah rantai proses Surat Keluar ({$this->surat->perihal}) secara manual, tahap baru mulai posisi ".($prefixLen + 1),
            $this->surat->id,
        );

        $this->sedangEditRantai = false;
        $this->reloadAfterAction();
    }

    /**
     * Simpan rantai jalur Bag/Kaur BAKU (otomatis) -- HANYA menyentuh baris
     * surat_approval yang masih 'menunggu' (tahap milik akun ini dan
     * seterusnya). Tahap-tahap SEBELUM itu (sudah 'disetujui') dibiarkan apa
     * adanya sebagai riwayat -- lihat buangYangSudahDisetujui(). Nomor
     * urutan baris baru melanjutkan dari urutan tahap aktif saat ini, PERSIS
     * seperti cara SuratForm::submit() membangun rantai dari nol -- bedanya
     * di sini nomor urutannya tidak mulai dari 1.
     */
    private function simpanEditRantaiOtomatis(User $user, int $urutanTahapSaya): void
    {
        $bagNama = $this->bagTerpilih['nama'] ?? '';
        if ($bagNama === '') {
            $this->jalurError = true;

            return;
        }
        if ($this->perluPilihKaur && !$this->kaurMemberId) {
            $this->kaurError = true;

            return;
        }
        try {
            $anggotaBaru = app(BagService::class)->rantaiKeluarUntukBag($bagNama, $this->kaurMemberId);
        } catch (\RuntimeException $e) {
            $this->errorEditData = $e->getMessage();

            return;
        }

        $anggotaBaru = $this->buangYangSudahDisetujui($anggotaBaru);
        if (!$anggotaBaru) {
            $this->errorEditData = 'Jalur Bag/Kaur yang dipilih tidak punya tahap baru untuk diproses -- semua tahapnya sudah disetujui sebelumnya. Pilih jalur lain atau pakai rantai manual.';

            return;
        }

        DB::transaction(function () use ($anggotaBaru, $bagNama, $urutanTahapSaya) {
            // Cuma tahap yang BELUM diproses -- tahap 'disetujui' sebelum
            // posisi ini TIDAK ikut terhapus (lihat komentar bisaEditRantai()).
            SuratApproval::query()
                ->where('surat_id', $this->surat->id)
                ->where('status', 'menunggu')
                ->delete();

            foreach ($anggotaBaru as $index => $role) {
                SuratApproval::query()->create([
                    'surat_id' => $this->surat->id,
                    'urutan' => $urutanTahapSaya + $index,
                    'role' => $role,
                    'status' => 'menunggu',
                ]);
            }

            $this->surat->kabag_dituju = $bagNama;
            $this->surat->save();
        });

        // Lihat komentar di simpanEditRantaiManual() -- recompute WAJIB
        // dipanggil di sini juga supaya konsisten, meski di jalur otomatis
        // ini praktiknya jarang menghasilkan nol tahap tersisa (sudah
        // dijaga oleh guard buangYangSudahDisetujui() di atas).
        $this->recomputeStatusSurat($this->surat->id);

        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Ubah rantai proses Surat Keluar ({$this->surat->perihal}), mulai tahap $urutanTahapSaya",
            $this->surat->id,
        );

        $this->sedangEditRantai = false;
        $this->reloadAfterAction();
    }

    /** Cermin SuratController::updateStatus(). */
    public function simpan(): void
    {
        $this->error = null;
        $user = Auth::user();
        $id = $this->surat->id;
        $isGateAkhir = $this->isGateAkhirDisposisi();

        if ($isGateAkhir) {
            if (empty($this->instruksiDisposisi)) {
                $this->error = 'Pilih minimal satu instruksi disposisi terlebih dahulu';

                return;
            }
        } elseif ($this->keputusan === null) {
            $this->error = 'Pilih ACC atau Revisi terlebih dahulu';

            return;
        }

        $this->isSubmitting = true;

        $keputusan = $isGateAkhir ? 'disetujui' : $this->keputusan;
        $catatan = trim($this->catatan);
        $catatanValue = $catatan === '' ? null : $catatan;
        $diprosesOleh = $user->nama;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow) {
            $this->error = 'Surat tidak ditemukan';
            $this->isSubmitting = false;

            return;
        }

        if ($suratRow->jenis === 'masuk' && $user->role === 'turmin') {
            $this->error = 'Akun Turmin hanya bisa melihat, tidak bisa memproses approval';
            $this->isSubmitting = false;

            return;
        }

        $bagService = app(BagService::class);

        $stage = DB::table('surat_approval')->where('surat_id', $id)->where('role', $diprosesOleh)->first();

        if (!$stage && $suratRow->jenis === 'masuk') {
            $stageKasubdit = DB::table('surat_approval')->where('surat_id', $id)->where('role', 'Kasubdit')
                ->where(function ($q) use ($diprosesOleh) {
                    $q->where('status', 'menunggu')->orWhere('diproses_oleh', $diprosesOleh);
                })->first();

            $kasubditGerbangUserId = $suratRow->kasubdit_gerbang_user_id === null ? null : (int) $suratRow->kasubdit_gerbang_user_id;
            $berhakKlaimMenunggu = $kasubditGerbangUserId === null
                ? $bagService->akunKasubditManapun((int) $user->id)
                : $kasubditGerbangUserId === (int) $user->id;

            if ($stageKasubdit && ($stageKasubdit->status !== 'menunggu' || $berhakKlaimMenunggu)) {
                $stage = $stageKasubdit;
            }
        }

        if (!$stage) {
            $this->error = 'Anda bukan salah satu tahap approval pada surat ini';
            $this->isSubmitting = false;

            return;
        }

        if ($suratRow->jenis === 'keluar' && $suratRow->status === 'disetujui') {
            // Approver PALING AKHIR rantai surat INI (bisa siapa saja sejak ada opsi
            // "berhenti di sini" di SuratForm -- BUKAN selalu Kasubditbinum/pimpinan
            // lagi) -- lihat komentar SuratReview::approverAkhirNama().
            $approverAkhirNama = DB::table('surat_approval')->where('surat_id', $id)->orderByDesc('urutan')->value('role');
            if ($diprosesOleh !== $approverAkhirNama) {
                $this->error = 'Surat sudah disetujui sepenuhnya, hanya '.($approverAkhirNama ?? 'approver akhir').' yang bisa mengubah keputusan sekarang';
                $this->isSubmitting = false;

                return;
            }
        }

        $disposisiBagIds = array_values(array_filter(array_map('intval', $this->bagTujuanTerpilih)));
        $disposisiList = [];

        $instruksiList = array_values(array_filter(array_map('trim', $this->instruksiDisposisi), fn ($v) => $v !== ''));

        $isGateAkhirDisposisi = $suratRow->jenis === 'masuk' && $stage->role === 'Kasubdit';
        $instruksiValid = config('suratapp.instruksi_disposisi_valid');

        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            if (!$instruksiList) {
                $this->error = 'Pilih minimal satu instruksi disposisi terlebih dahulu';
                $this->isSubmitting = false;

                return;
            }
            foreach ($instruksiList as $kode) {
                if (!array_key_exists($kode, $instruksiValid)) {
                    $this->error = 'Instruksi tidak valid: '.$kode;
                    $this->isSubmitting = false;

                    return;
                }
            }
            foreach ($disposisiBagIds as $bagId) {
                if (!$bagService->bagMilikKasubdit($bagId, (int) $user->id)) {
                    $this->error = 'Anda bukan Kasubdit Bag yang dipilih: '.$bagId;
                    $this->isSubmitting = false;

                    return;
                }

                // Bag ini SUDAH diatur Kabag-nya -- surat berhenti dulu di
                // Kabag (isi disposisi + pilih anggota lewat checkbox,
                // lihat simpanDisposisi()), TIDAK langsung diblast ke
                // seluruh anggota seperti sebelumnya.
                $kabagNama = $bagService->kabagNamaUntukBag($bagId);
                if ($kabagNama !== null) {
                    if (!in_array($kabagNama, $disposisiList, true)) {
                        $disposisiList[] = $kabagNama;
                    }

                    continue;
                }

                // Bag belum punya Kabag terdaftar -- perilaku lama tetap
                // berlaku, blast langsung ke seluruh anggota.
                $anggota = $bagService->anggotaBagUntukDisposisi($bagId);
                if (!$anggota) {
                    $this->error = 'Bag tidak valid atau belum punya anggota: '.$bagId;
                    $this->isSubmitting = false;

                    return;
                }
                foreach ($anggota as $a) {
                    if (!in_array($a['nama'], $disposisiList, true)) {
                        $disposisiList[] = $a['nama'];
                    }
                }
            }

            // Tembusan Manual: akun bebas pilih di luar struktur Bag di
            // atas. $nama DIAMBIL ULANG dari DB via user_id (bukan dipakai
            // langsung dari state Livewire sisi klien) supaya tidak bisa
            // dipalsukan jadi nama akun lain -- sama seperti pola
            // pilihUserManual()/teruskanDisposisiKabag() di tempat lain.
            foreach ($this->tembusanManualTerpilih as $t) {
                $userId = (int) ($t['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                $u = User::query()->whereNotIn('role', ['admin', 'turmin'])->find($userId, ['nama']);
                if ($u && !in_array($u->nama, $disposisiList, true)) {
                    $disposisiList[] = $u->nama;
                }
            }
        }

        $instruksiValue = ($isGateAkhirDisposisi && $keputusan === 'disetujui' && $instruksiList)
            ? implode(',', $instruksiList)
            : null;

        $adaSebelumBelumSetuju = DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)
            ->where('status', '!=', 'disetujui')->exists();

        if ($adaSebelumBelumSetuju) {
            $this->error = 'Belum giliran Anda untuk memproses surat ini';
            $this->isSubmitting = false;

            return;
        }

        $statusLama = $stage->status;
        $isRevisi = $statusLama !== 'menunggu';

        DB::table('surat_approval')->where('id', $stage->id)->update([
            'status' => $keputusan,
            'instruksi' => $instruksiValue,
            'catatan' => $catatanValue,
            'diproses_oleh' => $diprosesOleh,
            'diproses_at' => now(),
        ]);

        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            DB::table('surat_disposisi')->where('surat_id', $id)->delete();
            foreach ($disposisiList as $role) {
                DB::table('surat_disposisi')->insert(['surat_id' => $id, 'role' => $role]);
            }
            $disposisiValue = $disposisiList ? implode(',', $disposisiList) : null;
            DB::table('surat')->where('id', $id)->update(['disposisi' => $disposisiValue]);
        }

        $this->recomputeStatusSurat($id);

        $totalStages = DB::table('surat_approval')->where('surat_id', $id)->count();

        $labelLama = match ($statusLama) {
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => null,
        };
        $labelBaru = $keputusan === 'disetujui' ? 'Disetujui' : 'Ditolak';
        $verbaBaru = $keputusan === 'disetujui' ? 'menyetujui' : 'menolak';
        $jenisLabel = $suratRow->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
        $labelNomorSurat = $suratRow->nomor_surat ? "$jenisLabel {$suratRow->nomor_surat}" : $jenisLabel;
        $keterangan = "$labelNomorSurat ({$suratRow->perihal}): ".($isRevisi
            ? "{$stage->role} mengubah keputusan tahap {$stage->urutan}/$totalStages dari $labelLama menjadi $labelBaru"
            : "{$stage->role} $verbaBaru tahap {$stage->urutan}/$totalStages");
        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            $instruksiLabels = array_map(fn ($kode) => $instruksiValid[$kode] ?? $kode, $instruksiList);
            $keterangan .= ', instruksi: '.implode(', ', $instruksiLabels).($disposisiList
                ? ', diteruskan ke: '.implode(', ', $disposisiList)
                : ' (tanpa tujuan disposisi)');
        }

        ActivityLogger::log(request(), null, $diprosesOleh, 'update', $keterangan, $id);

        $this->isSubmitting = false;
        $this->reloadAfterAction();
        $this->dispatch('notify', message: 'Keputusan berhasil disimpan.');
    }

    public function konfirmasiDanSimpanRevisi(): void
    {
        // wire:confirm sudah menampilkan dialog konfirmasinya di sisi Blade
        // (lihat tombol Simpan) -- begitu action ini terpanggil pengguna
        // sudah menyetujui, langsung lanjut simpan seperti biasa.
        $this->simpan();
    }

    /** Cermin private recomputeStatusSurat() di SuratController. */
    private function recomputeStatusSurat(int $suratId): void
    {
        $rows = DB::table('surat_approval')->where('surat_id', $suratId)->orderBy('urutan')->get();

        $ditolak = $rows->where('status', 'ditolak')->values();
        $menunggu = $rows->where('status', 'menunggu')->values();

        if ($ditolak->isNotEmpty()) {
            $final = $ditolak->sortByDesc(fn ($r) => (string) $r->diproses_at)->first();
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'ditolak',
                'catatan_proses' => $final->catatan,
                'diproses_oleh' => $final->diproses_oleh,
                'diproses_at' => $final->diproses_at,
            ]);
        } elseif ($menunggu->isNotEmpty()) {
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
            ]);
        } else {
            $final = $rows->last();
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'disetujui',
                'catatan_proses' => $final->catatan,
                'diproses_oleh' => $final->diproses_oleh,
                'diproses_at' => $final->diproses_at,
            ]);
        }
    }

    /** Cermin SuratController::approvalLompat() -- tombol "Ambil Alih". */
    public function ambilAlih(): void
    {
        $this->sedangAmbilAlih = true;
        $this->error = null;
        $user = Auth::user();
        $id = $this->surat->id;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow || $suratRow->jenis !== 'keluar' || $suratRow->status !== 'menunggu') {
            $this->error = 'Surat ini sudah selesai diproses';
            $this->sedangAmbilAlih = false;

            return;
        }
        $stage = DB::table('surat_approval')->where('surat_id', $id)->where('role', $user->nama)->first();
        if (!$stage) {
            $this->error = 'Anda bukan salah satu tahap approval pada surat ini';
            $this->sedangAmbilAlih = false;

            return;
        }
        if ($stage->status !== 'menunggu') {
            $this->error = 'Tahap Anda sudah pernah diproses sebelumnya';
            $this->sedangAmbilAlih = false;

            return;
        }

        $tahapSebelumnya = DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)->where('status', 'menunggu')
            ->orderBy('urutan')->get();

        if ($tahapSebelumnya->isEmpty()) {
            $this->error = 'Sudah giliran Anda, tidak perlu melompat tahap';
            $this->sedangAmbilAlih = false;

            return;
        }

        $catatanAuto = "Dilewati -- otomatis disetujui karena {$user->nama} memproses tahapnya lebih dulu";
        $autoOleh = 'Sistem (dilewati)';
        DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)->where('status', 'menunggu')
            ->update(['status' => 'disetujui', 'catatan' => $catatanAuto, 'diproses_oleh' => $autoOleh, 'diproses_at' => now()]);

        $daftarTahap = $tahapSebelumnya->pluck('role')->implode(', ');
        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Surat Keluar ({$suratRow->perihal}): {$user->nama} memproses tahap {$stage->urutan} "
                ."sebelum gilirannya, tahap $daftarTahap otomatis disetujui",
            $id,
        );

        $this->reloadAfterAction();
        $this->sedangAmbilAlih = false;
    }

    /** Cermin SuratController::approvalMundur() -- tombol "Ambil Alih untuk Revisi". */
    public function bukaRevisi(): void
    {
        $this->sedangBukaRevisi = true;
        $this->error = null;
        $user = Auth::user();
        $id = $this->surat->id;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow || $suratRow->jenis !== 'keluar') {
            $this->error = 'Surat ini sudah selesai diproses';
            $this->sedangBukaRevisi = false;

            return;
        }

        $approvalRows = DB::table('surat_approval')->where('surat_id', $id)->orderBy('urutan')->get();
        $tahapSaya = $approvalRows->firstWhere('role', $user->nama);

        // PENGECUALIAN (bukan cerminan aslinya -- di aplikasi lama & original
        // PHP, tombol ini SELALU ditolak keras begitu status surat bukan lagi
        // 'menunggu', bahkan untuk tahap TERAKHIR yang baru saja menyelesaikan
        // rantai approval-nya sendiri). SENGAJA diizinkan di sini: tahap
        // TERAKHIR (bukan tahap manapun) boleh mengambil alih untuk revisi
        // keputusannya SENDIRI meskipun surat sudah final ('disetujui' ATAU
        // 'ditolak' oleh tahap terakhir itu sendiri) -- supaya kalau mereka
        // salah ACC/Revisi, tidak perlu proses "Ajukan Ulang" penuh hanya
        // untuk membetulkan keputusan akhir milik mereka sendiri.
        $urutanTerakhir = $approvalRows->max('urutan');
        $sayaTahapTerakhir = $tahapSaya && (int) $tahapSaya->urutan === (int) $urutanTerakhir;
        $bolehMeskipunFinal = in_array($suratRow->status, ['disetujui', 'ditolak'], true) && $sayaTahapTerakhir;

        if ($suratRow->status !== 'menunggu' && !$bolehMeskipunFinal) {
            $this->error = 'Surat ini sudah selesai diproses';
            $this->sedangBukaRevisi = false;

            return;
        }

        if (!$tahapSaya) {
            $this->error = 'Anda bukan salah satu tahap approval pada surat ini';
            $this->sedangBukaRevisi = false;

            return;
        }
        if ($tahapSaya->status === 'menunggu') {
            $this->error = 'Tahap Anda belum pernah diproses, tidak perlu diambil alih untuk revisi';
            $this->sedangBukaRevisi = false;

            return;
        }
        foreach ($approvalRows as $row) {
            if ($row->urutan < $tahapSaya->urutan && $row->status !== 'disetujui') {
                $this->error = 'Tahap sebelum Anda belum sepenuhnya disetujui';
                $this->sedangBukaRevisi = false;

                return;
            }
        }

        DB::table('surat_approval')->where('surat_id', $id)->where('urutan', '>=', $tahapSaya->urutan)->update([
            'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);
        DB::table('surat')->where('id', $id)->update([
            'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);

        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Surat Keluar ({$suratRow->perihal}): {$user->nama} mengambil alih untuk revisi, "
                ."rantai approval direset mulai tahap {$tahapSaya->urutan} ({$user->nama})",
            $id,
        );

        $this->reloadAfterAction();
        $this->keputusan = null;
        $this->catatan = '';
        $this->instruksiDisposisi = [];
        $this->sedangBukaRevisi = false;
    }

    /** Cermin SuratController::konfirmasiKaur(). */
    public function konfirmasiKaur(): void
    {
        $this->sedangKonfirmasiKaur = true;
        $this->error = null;
        $user = Auth::user();
        $id = $this->surat->id;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow || $suratRow->jenis !== 'keluar' || !in_array($suratRow->status, ['disetujui', 'ditolak'], true)) {
            $this->error = 'Surat ini belum disetujui sepenuhnya ataupun ditolak';
            $this->sedangKonfirmasiKaur = false;

            return;
        }
        $stage1 = DB::table('surat_approval')->where('surat_id', $id)->where('urutan', 1)->first();
        if (!$stage1 || $stage1->role !== $user->nama) {
            $this->error = 'Hanya pengaju pertama (Kaur) surat ini yang boleh mengonfirmasi/mengabaikan';
            $this->sedangKonfirmasiKaur = false;

            return;
        }

        DB::table('surat')->where('id', $id)->update(['konfirmasi_kaur_at' => now()]);

        $keterangan = $suratRow->status === 'ditolak'
            ? "Abaikan notifikasi Surat Keluar yang ditolak ({$suratRow->perihal}), tidak akan ditindaklanjuti"
            : "Konfirmasi penerimaan Surat Keluar yang sudah disetujui ({$suratRow->perihal})";
        ActivityLogger::log(request(), null, $user->nama, 'update', $keterangan, $id);

        $this->reloadAfterAction();
        $this->sedangKonfirmasiKaur = false;
    }

    /** Cermin SuratController::resubmit() -- tombol "Ajukan Ulang". */
    public function ajukanUlang(): void
    {
        $this->sedangAjukanUlang = true;
        $this->errorAjukanUlang = null;
        $user = Auth::user();
        $id = $this->surat->id;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow) {
            $this->errorAjukanUlang = 'Surat tidak ditemukan';
            $this->sedangAjukanUlang = false;

            return;
        }
        if ($suratRow->jenis !== 'keluar') {
            $this->errorAjukanUlang = 'Hanya Surat Keluar yang bisa diajukan ulang di sini';
            $this->sedangAjukanUlang = false;

            return;
        }
        if ($suratRow->status !== 'ditolak') {
            $this->errorAjukanUlang = 'Surat cuma bisa diajukan ulang saat statusnya Ditolak';
            $this->sedangAjukanUlang = false;

            return;
        }
        $roles = DB::table('surat_approval')->where('surat_id', $id)->pluck('role')->all();
        if (!in_array($user->nama, $roles, true)) {
            $this->errorAjukanUlang = 'Hanya pejabat di rantai approval surat ini yang boleh mengajukan ulang';
            $this->sedangAjukanUlang = false;

            return;
        }

        DB::table('surat_approval')->where('surat_id', $id)->update([
            'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);
        DB::table('surat')->where('id', $id)->update([
            'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);

        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Ajukan ulang Surat Keluar ({$suratRow->perihal}), rantai approval direset dari tahap pertama", $id,
        );

        $this->reloadAfterAction();
        $this->keputusan = null;
        $this->catatan = '';
        $this->sedangAjukanUlang = false;
        $this->dispatch('notify', message: 'Surat berhasil diajukan ulang.');
    }

    /** Cermin SuratController::deleteDitolak() -- tombol "Hapus Surat". */
    public function hapusSurat()
    {
        $this->sedangHapusSurat = true;
        $this->errorAjukanUlang = null;
        $user = Auth::user();
        $id = $this->surat->id;

        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow) {
            $this->errorAjukanUlang = 'Surat tidak ditemukan';
            $this->sedangHapusSurat = false;

            return;
        }
        if ($suratRow->jenis !== 'keluar') {
            $this->errorAjukanUlang = 'Hanya Surat Keluar yang bisa dihapus di sini';
            $this->sedangHapusSurat = false;

            return;
        }
        if ($suratRow->status !== 'ditolak') {
            $this->errorAjukanUlang = 'Surat cuma bisa dihapus di sini saat statusnya Ditolak';
            $this->sedangHapusSurat = false;

            return;
        }
        $roles = DB::table('surat_approval')->where('surat_id', $id)->pluck('role')->all();
        if (!in_array($user->nama, $roles, true)) {
            $this->errorAjukanUlang = 'Hanya pejabat di rantai approval surat ini yang boleh menghapusnya';
            $this->sedangHapusSurat = false;

            return;
        }

        $fileNames = DB::table('surat_file')->where('surat_id', $id)->pluck('file_name')->all();
        $perihal = $suratRow->perihal;

        DB::table('surat')->where('id', $id)->delete();

        ActivityLogger::log(request(), null, $user->nama, 'delete', "Menghapus Surat Keluar yang ditolak ($perihal)");

        $this->hapusFileFisik($fileNames);

        return redirect()->route('dashboard');
    }

    private function hapusFileFisik(array $fileNames): void
    {
        $uploadsDir = config('suratapp.uploads_path');
        $previewCacheDir = config('suratapp.preview_cache_path');
        foreach ($fileNames as $fileName) {
            $path = $uploadsDir.'/'.$fileName;
            if (is_file($path)) {
                @unlink($path);
            }
            $cachedPdf = $previewCacheDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';
            if (is_file($cachedPdf)) {
                @unlink($cachedPdf);
            }
        }
    }

    /** Cermin SuratController::editResetProgress(), lalu navigasi ke halaman editor. */
    public function editDocumentAndOpen(int $fileId): void
    {
        $user = Auth::user();
        $id = $this->surat->id;
        $suratRow = DB::table('surat')->where('id', $id)->first();

        if ($suratRow && $suratRow->jenis === 'keluar' && $suratRow->status === 'menunggu') {
            $approvalRows = DB::table('surat_approval')->where('surat_id', $id)->orderBy('urutan')->get();
            $tahapSaya = $approvalRows->firstWhere('role', $user->nama);

            $bolehEdit = false;
            if ($tahapSaya) {
                $bolehEdit = true;
                foreach ($approvalRows as $row) {
                    if ($row->urutan < $tahapSaya->urutan && $row->status !== 'disetujui') {
                        $bolehEdit = false;
                        break;
                    }
                }
            }

            if ($bolehEdit) {
                DB::table('surat_approval')->where('surat_id', $id)->where('urutan', '>=', $tahapSaya->urutan)->update([
                    'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
                ]);
                DB::table('surat')->where('id', $id)->update([
                    'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
                ]);

                ActivityLogger::log(
                    request(), null, $user->nama, 'update',
                    "Edit dokumen Surat Keluar ({$suratRow->perihal}), rantai approval direset mulai tahap {$tahapSaya->urutan} ({$user->nama})",
                    $id,
                );

                $this->reloadAfterAction();
            }
        }

        $file = DB::table('surat_file')->where('id', $fileId)->first();
        $routeName = $file && $this->isFoto($file->file_original_name) ? 'surat-file.annotate' : 'surat-file.editor';
        $this->redirect(route($routeName, $fileId));
    }

    /** Cermin SuratController::updateDisposisi() -- Isi Disposisi per pejabat tujuan (Surat Masuk). */
    public function simpanDisposisi(string $nama): void
    {
        $this->errorRole[$nama] = null;
        $user = Auth::user();

        if ($user->role === 'turmin') {
            $this->errorRole[$nama] = 'Akun Turmin hanya bisa melihat, tidak bisa mengisi disposisi';

            return;
        }

        $teks = trim($this->disposisiCatatan[$nama] ?? '');
        if ($teks === '') {
            $this->errorRole[$nama] = 'Isi Disposisi wajib diisi';

            return;
        }

        if (!app(BagService::class)->namaValidUntukDisposisi($nama)) {
            $this->errorRole[$nama] = 'Pejabat tujuan disposisi tidak valid';

            return;
        }
        if ($user->nama !== $nama) {
            $this->errorRole[$nama] = 'Anda hanya boleh mengisi disposisi milik Anda sendiri';

            return;
        }

        $id = $this->surat->id;
        $exists = DB::table('surat_disposisi as sd')
            ->join('surat as s', 's.id', '=', 'sd.surat_id')
            ->where('sd.surat_id', $id)->where('sd.role', $nama)->where('s.jenis', 'masuk')
            ->exists();
        if (!$exists) {
            $this->errorRole[$nama] = 'Surat masuk untuk pejabat ini tidak ditemukan';

            return;
        }

        $gateCount = DB::table('surat_approval')->where('surat_id', $id)->count();
        if ($gateCount > 0) {
            $suratStatus = DB::table('surat')->where('id', $id)->value('status');
            if ($suratStatus !== 'disetujui') {
                $this->errorRole[$nama] = 'Surat ini belum disetujui Turmin/Kasubditbinum, belum bisa diisi disposisinya';

                return;
            }
        }

        $this->isSubmittingRole[$nama] = true;

        DB::table('surat_disposisi')->where('surat_id', $id)->where('role', $nama)->update([
            'catatan' => $teks, 'diproses_oleh' => $user->nama, 'diproses_at' => now(),
        ]);

        ActivityLogger::log(
            request(), null, $user->nama, 'update',
            "Isi Disposisi $nama - Surat Masuk {$this->surat->nomor_surat} ({$this->surat->perihal})", $id,
        );

        // Kalau $nama ini akun Kabag (bag_masuk.kabag_user_id), rekonsiliasi
        // checkbox "teruskan ke anggota" di kartu yang sama: anggota yang
        // dicentang ditambahkan, yang di-UNCHECK dan belum merespon dihapus
        // -- lihat BagService::teruskanDisposisiKabag(). Dipanggil TANPA
        // syarat ada yang dicentang (uncheck semua = batalkan semua anggota).
        $catatanTambahan = '';
        if ($this->kabagInfoUntuk($nama)) {
            $userIdsTerpilih = array_values(array_filter(array_map('intval', $this->kabagAnggotaTerpilih[$nama] ?? [])));
            $hasil = app(BagService::class)->teruskanDisposisiKabag($id, $nama, $userIdsTerpilih);

            if ($hasil['ditambah'] || $hasil['dihapus']) {
                $suratRow = DB::table('surat')->where('id', $id)->first();
                $csv = $suratRow && $suratRow->disposisi ? explode(',', $suratRow->disposisi) : [];
                $csv = array_values(array_diff($csv, $hasil['dihapus']));
                foreach ($hasil['ditambah'] as $n) {
                    if (!in_array($n, $csv, true)) {
                        $csv[] = $n;
                    }
                }
                DB::table('surat')->where('id', $id)->update(['disposisi' => $csv ? implode(',', $csv) : null]);

                $bagian = [];
                if ($hasil['ditambah']) {
                    $bagian[] = 'diteruskan ke: '.implode(', ', $hasil['ditambah']);
                }
                if ($hasil['dihapus']) {
                    $bagian[] = 'dibatalkan ke: '.implode(', ', $hasil['dihapus']);
                }
                ActivityLogger::log(
                    request(), null, $user->nama, 'update',
                    "Kabag $nama memperbarui tujuan disposisi Surat Masuk {$this->surat->nomor_surat} ({$this->surat->perihal}) -- ".implode('; ', $bagian), $id,
                );
            }

            if ($hasil['dipertahankan']) {
                $catatanTambahan = ' '.implode(', ', $hasil['dipertahankan'])
                    .' sudah mengisi disposisi, tidak ikut dibatalkan.';
            }
        } elseif ($this->rekanSebagUntuk($nama)) {
            // $nama Penerima Disposisi biasa (bukan Kabag): "teruskan ke
            // rekan sebag" -- centang = tambah rekan; uncheck = batalkan
            // TAPI hanya rekan yang $nama sendiri tambahkan & belum direspon.
            // Lihat BagService::teruskanDisposisiAntarAnggota().
            $rekanIds = array_values(array_filter(array_map('intval', $this->rekanSebagTerpilih[$nama] ?? [])));
            $hasil = app(BagService::class)->teruskanDisposisiAntarAnggota($id, $nama, $rekanIds);

            if ($hasil['ditambah'] || $hasil['dihapus']) {
                $suratRow = DB::table('surat')->where('id', $id)->first();
                $csv = $suratRow && $suratRow->disposisi ? explode(',', $suratRow->disposisi) : [];
                $csv = array_values(array_diff($csv, $hasil['dihapus']));
                foreach ($hasil['ditambah'] as $n) {
                    if (!in_array($n, $csv, true)) {
                        $csv[] = $n;
                    }
                }
                DB::table('surat')->where('id', $id)->update(['disposisi' => $csv ? implode(',', $csv) : null]);

                $bagian = [];
                if ($hasil['ditambah']) {
                    $bagian[] = 'diteruskan ke: '.implode(', ', $hasil['ditambah']);
                }
                if ($hasil['dihapus']) {
                    $bagian[] = 'dibatalkan ke: '.implode(', ', $hasil['dihapus']);
                }
                ActivityLogger::log(
                    request(), null, $user->nama, 'update',
                    "$nama memperbarui terusan rekan sebag Surat Masuk {$this->surat->nomor_surat} ({$this->surat->perihal}) -- ".implode('; ', $bagian), $id,
                );
            }
        }

        $this->isSubmittingRole[$nama] = false;
        $this->dispatch('notify', message: "Disposisi untuk $nama tersimpan.".$catatanTambahan);
    }

    /**
     * Hasil pencarian akun untuk "Tembusan Manual" (gerbang Kasubdit Surat
     * Masuk) -- cermin getOpsiUserManualProperty() milik
     * MengelolaRantaiSuratKeluar, tapi admin DAN turmin dikecualikan
     * (turmin tidak boleh mengisi disposisi sama sekali -- lihat
     * simpanDisposisi() -- jadi percuma ditawarkan sebagai tembusan).
     *
     * @return array<int, array{id:int, nama:string}>
     */
    public function getOpsiUserTembusanProperty(): array
    {
        if (trim($this->cariUserTembusan) === '') {
            return [];
        }
        $sudahDipilih = array_column($this->tembusanManualTerpilih, 'user_id');

        return User::query()
            ->whereNotIn('role', ['admin', 'turmin'])
            ->when($sudahDipilih, fn ($q) => $q->whereNotIn('id', $sudahDipilih))
            ->where('nama', 'like', '%'.$this->cariUserTembusan.'%')
            ->orderBy('nama')
            ->limit(8)
            ->get(['id', 'nama'])
            ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->nama])
            ->all();
    }

    public function pilihUserTembusan(int $userId): void
    {
        if ($userId <= 0 || collect($this->tembusanManualTerpilih)->contains('user_id', $userId)) {
            $this->cariUserTembusan = '';

            return;
        }
        $user = User::query()->whereNotIn('role', ['admin', 'turmin'])->find($userId, ['id', 'nama']);
        if (!$user) {
            return;
        }
        $this->tembusanManualTerpilih[] = ['user_id' => $user->id, 'nama' => $user->nama];
        $this->cariUserTembusan = '';
    }

    public function hapusDariTembusanManual(int $index): void
    {
        unset($this->tembusanManualTerpilih[$index]);
        $this->tembusanManualTerpilih = array_values($this->tembusanManualTerpilih);
    }

    /** Cermin BagService::semuaBagMasuk() difilter Bag milik akun Kasubdit ini, + pra-centang dari disposisi tersimpan. */
    public function muatBagDisposisi(): void
    {
        $this->sedangMuatBag = true;
        $this->errorMuatBag = null;
        try {
            $user = Auth::user();
            $semuaBag = app(BagService::class)->semuaBagMasuk();
            $bags = array_values(array_filter($semuaBag, fn ($b) => ($b['kasubdit']['id'] ?? null) === $user->id));
            usort($bags, fn ($a, $b) => strcmp($a['nama'], $b['nama']));
            $this->bags = $bags;

            $namaTerpilih = $this->disposisiNamaMentah();
            foreach ($bags as $bag) {
                // Bag BER-KABAG: meneruskan cuma bikin SATU baris disposisi
                // (milik Kabag-nya sendiri), bukan ke seluruh anggota_masuk
                // -- lihat simpan() di bawah. Jadi "sudah diteruskan" dicek
                // dari nama Kabag-nya, bukan dari seluruh anggota (yang akan
                // SELALU gagal cocok sejak fitur Kabag ada -- checkbox Bag
                // tidak akan pernah pra-tercentang lagi walau surat memang
                // sudah diteruskan ke Bag itu).
                if ($bag['kabag']) {
                    if (in_array($bag['kabag']['nama'], $namaTerpilih, true)) {
                        $this->bagTujuanTerpilih[] = $bag['id'];
                    }

                    continue;
                }

                $anggota = $bag['anggota_masuk'];
                if ($anggota && collect($anggota)->every(fn ($a) => in_array($a['nama'], $namaTerpilih, true))) {
                    $this->bagTujuanTerpilih[] = $bag['id'];
                }
            }

            // Tembusan Manual: nama disposisi yang SUDAH tersimpan tapi
            // TIDAK bisa dijelaskan oleh Kabag/anggota Bag mana pun milik
            // Kasubdit ini -- berarti sebelumnya ditambahkan lewat picker
            // manual, bukan lewat struktur Bag. Pra-isi supaya tetap
            // terlihat & tidak hilang kalau Kasubdit simpan ulang (simpan()
            // membangun ulang $disposisiList dari nol tiap kali).
            $namaTerjelaskanOlehBag = [];
            foreach ($bags as $bag) {
                if ($bag['kabag']) {
                    $namaTerjelaskanOlehBag[] = $bag['kabag']['nama'];
                } else {
                    foreach ($bag['anggota_masuk'] as $a) {
                        $namaTerjelaskanOlehBag[] = $a['nama'];
                    }
                }
            }
            $namaTembusanManual = array_values(array_diff($namaTerpilih, $namaTerjelaskanOlehBag));
            if ($namaTembusanManual) {
                $this->tembusanManualTerpilih = User::query()
                    ->whereIn('nama', $namaTembusanManual)
                    ->get(['id', 'nama'])
                    ->map(fn (User $u) => ['user_id' => $u->id, 'nama' => $u->nama])
                    ->all();
            }

            $this->sedangMuatBag = false;
        } catch (\Throwable $e) {
            $this->errorMuatBag = $e->getMessage();
            $this->sedangMuatBag = false;
        }
    }

    // =========================================================================
    // Kelola file lampiran Surat Keluar -- cermin SuratFileController.
    // =========================================================================

    public function deleteFile(int $fileId): void
    {
        $this->errorFile = null;
        $file = DB::table('surat_file')->where('id', $fileId)->first();
        if (!$file) {
            $this->errorFile = 'File tidak ditemukan';

            return;
        }
        $suratRow = DB::table('surat')->where('id', $file->surat_id)->first();
        $id = (int) $file->surat_id;
        if (!$suratRow) {
            $this->errorFile = 'Surat tidak ditemukan';

            return;
        }
        if ($suratRow->status === 'disetujui') {
            $this->errorFile = 'Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi';

            return;
        }

        $user = Auth::user();
        if (!$this->bisaKelolaFile($suratRow)) {
            $this->errorFile = $suratRow->jenis === 'keluar'
                ? 'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh menghapus filenya'
                : 'Hanya Turmin yang menginput surat ini (sebelum diputuskan Kasubdit) atau Kasubdit yang boleh menghapus lampiran surat masuk ini';

            return;
        }

        $jumlah = DB::table('surat_file')->where('surat_id', $id)->count();
        if ($jumlah <= 1) {
            $this->errorFile = 'Surat harus punya minimal satu file';

            return;
        }

        $this->sedangHapusFileId = $fileId;

        @unlink(config('suratapp.uploads_path').'/'.$file->file_name);
        $baseName = pathinfo($file->file_name, PATHINFO_FILENAME);
        @unlink(config('suratapp.preview_cache_path').'/'.$baseName.'.pdf');

        DB::table('surat_file')->where('id', $fileId)->delete();

        $jenisLabel = $suratRow->jenis === 'keluar' ? 'Surat Keluar' : 'Surat Masuk';
        ActivityLogger::log(request(), null, $user->nama, 'update', "Hapus file dari $jenisLabel ({$suratRow->perihal})", $id);

        $this->sedangHapusFileId = null;
    }

    public function startRenameFile(int $fileId, string $currentName): void
    {
        $this->editingFileId = $fileId;
        $this->editingFileName = $currentName;
        $this->errorFile = null;
    }

    public function cancelRenameFile(): void
    {
        $this->editingFileId = null;
        $this->editingFileName = '';
    }

    public function renameFile(): void
    {
        $this->errorFile = null;
        $fileId = $this->editingFileId;
        $namaBaru = trim($this->editingFileName);
        if (!$fileId) {
            return;
        }
        if ($namaBaru === '') {
            $this->errorFile = 'Nama file tidak boleh kosong';

            return;
        }

        $file = DB::table('surat_file')->where('id', $fileId)->first();
        if (!$file) {
            $this->errorFile = 'File tidak ditemukan';

            return;
        }

        $suratRow = DB::table('surat')->where('id', $file->surat_id)->first();
        $user = Auth::user();

        if (!$suratRow) {
            $this->errorFile = 'Surat tidak ditemukan';

            return;
        }
        if ($suratRow->status === 'disetujui') {
            $this->errorFile = 'Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi';

            return;
        }
        if (!$this->bisaKelolaFile($suratRow)) {
            $this->errorFile = $suratRow->jenis === 'keluar'
                ? 'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh mengganti nama filenya'
                : 'Hanya Turmin yang menginput surat ini (sebelum diputuskan Kasubdit) atau Kasubdit yang boleh mengganti nama lampiran surat masuk ini';

            return;
        }

        $extAsli = pathinfo($file->file_original_name, PATHINFO_EXTENSION);
        $extBaru = pathinfo($namaBaru, PATHINFO_EXTENSION);
        $namaFinal = strcasecmp($extAsli, $extBaru) === 0 ? $namaBaru : $namaBaru.'.'.$extAsli;

        $namaLama = $file->file_original_name;
        DB::table('surat_file')->where('id', $fileId)->update(['file_original_name' => $namaFinal]);

        ActivityLogger::log(request(), null, $user->nama, 'update', "Ganti nama file \"{$namaLama}\" menjadi \"{$namaFinal}\"", (int) $file->surat_id);

        $this->editingFileId = null;
        $this->editingFileName = '';
    }

    /** Livewire lifecycle hook -- begitu file dipilih (temp upload selesai), langsung unggah ke server (tanpa tombol konfirmasi terpisah), sama seperti _tambahFileBaru() di Flutter. */
    public function updatedNewFiles(): void
    {
        $this->uploadFiles();
    }

    public function uploadFiles(): void
    {
        $this->errorFile = null;
        if (empty($this->newFiles)) {
            return;
        }

        $user = Auth::user();
        $id = $this->surat->id;
        $suratRow = DB::table('surat')->where('id', $id)->first();
        if (!$suratRow) {
            $this->errorFile = 'Surat tidak ditemukan';

            return;
        }
        if ($suratRow->status === 'disetujui') {
            $this->errorFile = 'Dokumen sudah disetujui sepenuhnya, lampirannya tidak bisa diubah lagi';

            return;
        }
        if (!$this->bisaKelolaFile($suratRow)) {
            $this->errorFile = $suratRow->jenis === 'keluar'
                ? 'Hanya pengaju pertama (Kaur) atau pejabat pemegang giliran saat ini yang boleh menambah filenya'
                : 'Hanya Turmin yang menginput surat ini (sebelum diputuskan Kasubdit) atau Kasubdit yang boleh menambah lampiran surat masuk ini';

            return;
        }

        $suratFileService = app(SuratFileService::class);
        $this->sedangUnggahFile = true;

        $mimeWhitelist = config('suratapp.mime_keluar');
        $urutanBerikutnya = (int) (DB::table('surat_file')->where('surat_id', $id)->max('urutan') ?? -1) + 1;

        $stored = [];
        try {
            foreach ($this->newFiles as $uploaded) {
                $saved = $suratFileService->simpanFileSurat($uploaded, $mimeWhitelist);
                $stored[] = $saved;
            }
        } catch (\RuntimeException $e) {
            foreach ($stored as $s) {
                @unlink(config('suratapp.uploads_path').'/'.$s['file_name']);
            }
            $this->errorFile = $e->getMessage();
            $this->sedangUnggahFile = false;

            return;
        }

        foreach ($stored as $s) {
            SuratFile::query()->create([
                'surat_id' => $id,
                'urutan' => $urutanBerikutnya,
                'file_name' => $s['file_name'],
                'file_original_name' => $s['file_original_name'],
            ]);
            $urutanBerikutnya++;
        }

        $jenisLabel = $suratRow->jenis === 'keluar' ? 'Surat Keluar' : 'Surat Masuk';
        ActivityLogger::log(request(), null, $user->nama, 'update', 'Tambah '.count($stored)." file baru ke $jenisLabel ({$suratRow->perihal})", $id);

        $this->newFiles = [];
        $this->sedangUnggahFile = false;
    }

    // =========================================================================
    // Riwayat aktivitas -- cermin SuratController::activityLog().
    // =========================================================================

    public function toggleRiwayat(): void
    {
        $this->showRiwayat = !$this->showRiwayat;
        if ($this->showRiwayat) {
            $this->riwayat = DB::table('activity_log')->where('surat_id', $this->surat->id)
                ->orderBy('waktu')->orderBy('id')
                ->get(['waktu', 'username', 'nama', 'aksi', 'keterangan'])->all();
        }
    }

    public function render()
    {
        return view('livewire.surat-review');
    }
}
