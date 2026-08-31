<?php

namespace App\Livewire;

use App\Livewire\Dashboard\DisposisiRoles;
use App\Livewire\Dashboard\MenuSurat;
use App\Models\Pengumuman;
use App\Models\Surat;
use App\Models\SuratFile;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shell utama setelah login -- migrasi dari lib/screens/dashboard_screen.dart
 * (sidebar menu + antrian/arsip surat + notifikasi) DIGABUNG dengan
 * lib/screens/surat_kategori_browser.dart (folder Bulan -> Klasifikasi ->
 * daftar surat, dirender langsung di sini alih-alih rute terpisah, sesuai
 * arsitektur satu-halaman ala Flutter aslinya -- lihat komentar di
 * routes/web_dashboard.php).
 *
 * Beda arsitektur PENTING dari Flutter: di sana banyak state ber-status
 * "null = belum selesai dimuat" karena SEMUA panggilan data lewat HTTP API
 * async (mis. _adaBagKasubditSaya, _bagianSayaDinamis). Di Laravel, query
 * Eloquent/DB dilakukan LANGSUNG & SINKRON di proses request yang sama --
 * tidak ada jeda "sedang dimuat" sama sekali, jadi seluruh state itu di
 * sini collapse jadi getter/Computed property biasa yang query on-demand.
 */
#[Layout('layouts.app', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    private const NAMA_BULAN = [
        '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /**
     * Urutan "baku" label klasifikasi -- persis urutan DEKLARASI enum
     * KlasifikasiSurat di lib/models/surat.dart (dipakai kategori_browser
     * lewat _bandingkanUrutanKlasifikasi). CATATAN: enum itu juga punya
     * getter `prioritas` dengan urutan BEDA (telegram=0, perintah=1, dst),
     * tapi getter itu TIDAK DIPAKAI di mana pun pada UI (dicek lewat grep
     * di seluruh lib/) -- kode mati, jadi SENGAJA tidak direplikasi di sini
     * supaya perilaku folder klasifikasi persis sama seperti yang benar-benar
     * dirender Flutter, bukan field yang tidak pernah dipakai.
     */
    private const KLASIFIKASI_URUTAN_BAKU = [
        'Surat Keputusan', 'Surat Telegram', 'Surat Edaran', 'Surat Perintah', 'Surat Biasa',
        'Surat Nota Dinas', 'Surat Pengantar', 'Surat Undangan', 'Surat Rahasia',
    ];

    /** Slug menu aktif (lihat App\Livewire\Dashboard\MenuSurat) -- disinkronkan ke query string ?menu=. */
    #[Url]
    public string $menu = '';

    /** Nama anggota jajaran yang folder "Surat Masuk Jajaran"-nya sedang dibuka -- hanya relevan saat $menu = jajaran-anggota. */
    #[Url]
    public ?string $anggota = null;

    public string $search = '';

    // State navigasi internal folder Bulan -> Klasifikasi (lihat
    // SuratKategoriBrowser Flutter) -- SENGAJA tidak disinkronkan ke URL,
    // sama seperti aslinya (cuma state widget biasa, di-reset tiap ganti menu).
    public ?string $bulanKey = null;

    public ?string $bulanLabel = null;

    public ?string $klasifikasi = null;

    /** Seleksi checkbox untuk hapus massal (admin saja, lihat kategori_bar.dart & surat_kategori_browser.dart). */
    public array $selectedIds = [];

    /** 'awal' (urutan alami) atau 'belum_direspon' (belum direspon di atas) -- lihat _UrutanKategori Flutter. */
    public string $urutanFolder = 'awal';

    // Snapshot ID poll sebelumnya, dipakai deteksi notifikasi BARU (bukan
    // yang sudah ada dari poll sebelumnya) -- lihat
    // _deteksiDanBeriTahuNotifikasiBaru di dashboard_screen.dart. Null =
    // baseline poll pertama belum terekam.
    public ?array $idNotifikasiKeluarDiketahui = null;

    public ?array $idArsipKeluarDiketahui = null;

    public ?array $idNotifikasiMasukDiketahui = null;

    public bool $isDeleting = false;

    public function mount(): void
    {
        $allowed = $this->allowedMenuValues();

        if ($this->menu === '' || !in_array($this->menu, $allowed, true)) {
            $this->menu = MenuSurat::Dashboard->value;
        }

        if ($this->menu !== MenuSurat::JajaranAnggota->value) {
            $this->anggota = null;
        } elseif ($this->anggota !== null && !in_array($this->anggota, $this->namaJajaranSaya(), true)) {
            $this->anggota = null;
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }

    // ------------------------------------------------------------------
    // Identitas & kapabilitas akun (migrasi dari getter2 _DashboardScreenState)
    // ------------------------------------------------------------------

    #[Computed]
    public function currentUser(): User
    {
        return auth()->user();
    }

    private ?\App\Services\SidebarMenuService $sidebarServiceCache = null;

    /**
     * Sumber tunggal logika menu/badge sidebar (lihat App\Services\SidebarMenuService)
     * -- method2 di bawah ini SENGAJA cuma delegasi tipis ke sana (bukan
     * duplikat) supaya sidebar (layouts.app, tampil di SEMUA halaman) &
     * konten utama Dashboard ini selalu menghasilkan menu/badge yang PERSIS
     * sama, tidak mungkin berbeda-beda.
     *
     * Di-cache manual lewat properti (BUKAN cuma andalkan #[Computed]) --
     * seluruh pemanggil di file ini memanggilnya sebagai method
     * `$this->sidebarService()` (dengan kurung), bukan diakses sebagai
     * properti `$this->sidebarService`. #[Computed] Livewire cuma
     * meng-cache lewat magic __get() PROPERTI (tanpa kurung) -- dipanggil
     * sebagai method biasa (`()`) SELALU membuat instance
     * SidebarMenuService BARU tiap kali, jadi cache di dalamnya
     * (SidebarMenuService::$fetchSuratCache) tidak pernah kepakai lintas
     * pemanggilan, dan query surat+disposisi+approval yang sama jalan
     * berkali-kali per request (lihat daftar()/notifikasiKeluar()/
     * notifikasiMasuk() di bawah, semuanya lewat sini). Properti manual di
     * sini menjamin SATU instance dipakai bersama sepanjang request,
     * apa pun cara pemanggilannya.
     */
    public function sidebarService(): \App\Services\SidebarMenuService
    {
        return $this->sidebarServiceCache ??= new \App\Services\SidebarMenuService($this->currentUser());
    }

    #[Computed]
    public function isPejabat(): bool
    {
        return $this->sidebarService()->isPejabat();
    }

    #[Computed]
    public function isPimpinan(): bool
    {
        return $this->sidebarService()->isPimpinan();
    }

    /** Identitas pejabat SPESIFIK -- nama akun yang cocok PERSIS salah satu dari 20 label DisposisiRole tetap. */
    #[Computed]
    public function pejabatRoleLabel(): ?string
    {
        return $this->sidebarService()->pejabatRoleLabel();
    }

    /** Bagian/jalur milik akun pejabat 5-jalur lama (null kalau tidak punya struktur Kabag/Kasi/Kaur ATAU nama tidak cocok). */
    #[Computed]
    public function bagianSaya(): ?string
    {
        return $this->sidebarService()->bagianSaya();
    }

    /** Sama seperti bagianSaya(), tapi untuk akun Bag/"grup dalam grup" nama bebas (lihat App\Services\BagService::bagKeluarSaya()). */
    #[Computed]
    public function bagianSayaDinamis(): ?string
    {
        return $this->sidebarService()->bagianSayaDinamis();
    }

    /** Dipakai di SEMUA tempat yang sebelumnya cuma memakai bagianSaya() -- gabungan jalur lama + jalur baru. */
    #[Computed]
    public function bagianEfektif(): ?string
    {
        return $this->sidebarService()->bagianEfektif();
    }

    /** Kabagtu & Turmin (SEMUA akun turmin, bukan cuma "Turmin Subditbinum") perlu memantau Progress Surat Keluar lintas jalur. */
    #[Computed]
    public function bisaMonitorProgressLintasJalur(): bool
    {
        return $this->sidebarService()->bisaMonitorProgressLintasJalur();
    }

    /** Akun gerbang Surat Masuk generik (role='turmin') -- beda dari pejabatRoleLabel() == 'Turmin Subditbinum'. */
    #[Computed]
    public function isTurminMasuk(): bool
    {
        return $this->sidebarService()->isTurminMasuk();
    }

    /** true kalau akun Pimpinan ini terdaftar sebagai Kasubdit di Bag Surat Masuk manapun. */
    #[Computed]
    public function adaBagKasubditSaya(): bool
    {
        return $this->sidebarService()->adaBagKasubditSaya();
    }

    /** Label generik tahap gerbang Surat Masuk ('Turmin'/'Kasubdit') milik akun ini, null kalau bukan gerbang. */
    #[Computed]
    public function labelGerbangMasuk(): ?string
    {
        return $this->sidebarService()->labelGerbangMasuk();
    }

    /** Nama pejabat (urut abjad) yang jadi anggota jajaran akun Pimpinan ini -- lihat panel "Struktur Anggota". */
    #[Computed]
    public function namaJajaranSaya(): array
    {
        return $this->sidebarService()->namaJajaranSaya();
    }

    public function bisaInputMasuk(): bool
    {
        return (bool) $this->currentUser()->boleh_input_masuk;
    }

    public function bisaInputKeluar(): bool
    {
        return (bool) $this->currentUser()->boleh_input_keluar;
    }

    public function bisaInputSurat(): bool
    {
        return $this->bisaInputMasuk() || $this->bisaInputKeluar();
    }

    // ------------------------------------------------------------------
    // Menu sidebar & navigasi
    // ------------------------------------------------------------------

    #[Computed]
    public function menuMasukList(): array
    {
        return $this->sidebarService()->menuMasukList();
    }

    #[Computed]
    public function menuKeluarList(): array
    {
        return $this->sidebarService()->menuKeluarList();
    }

    private function allowedMenuValues(): array
    {
        // MenuSurat::Dashboard (ringkasan gabungan) SELALU diizinkan -- baik
        // admin maupun pejabat, karena daftarDashboardKeluar()/daftarDashboardMasuk()
        // (dipanggil lewat kasus Dashboard di daftar()) sudah diskop per
        // akun masing-masing, bukan lintas-siapa-pun.
        $values = array_map(fn (MenuSurat $m) => $m->value, [
            MenuSurat::Dashboard,
            ...$this->menuMasukList(),
            ...$this->menuKeluarList(),
        ]);

        if ($this->isPimpinan()) {
            $values[] = MenuSurat::JajaranAnggota->value;
        }

        return $values;
    }

    #[Computed]
    public function activeMenu(): MenuSurat
    {
        return MenuSurat::tryFrom($this->menu) ?? MenuSurat::Dashboard;
    }

    public function badgeUntukMenu(MenuSurat $menu): ?int
    {
        return $this->sidebarService()->badgeUntukMenu($menu);
    }

    public function judulMenuAktif(): string
    {
        if ($this->activeMenu() === MenuSurat::JajaranAnggota) {
            return $this->anggota ?? '';
        }

        return $this->activeMenu()->judul();
    }

    public function isMenuDashboard(): bool
    {
        return in_array($this->activeMenu(), [MenuSurat::Dashboard, MenuSurat::DashboardMasuk, MenuSurat::DashboardKeluar], true);
    }

    public function pilihMenu(string $menuValue): void
    {
        if (!in_array($menuValue, $this->allowedMenuValues(), true)) {
            return;
        }
        if ($this->menu === $menuValue) {
            return;
        }
        $this->menu = $menuValue;
        $this->anggota = null;
        $this->resetBrowserState();
    }

    public function pilihJajaranAnggota(string $nama): void
    {
        if (!in_array($nama, $this->namaJajaranSaya(), true)) {
            return;
        }
        if ($this->menu === MenuSurat::JajaranAnggota->value && $this->anggota === $nama) {
            return;
        }
        $this->menu = MenuSurat::JajaranAnggota->value;
        $this->anggota = $nama;
        $this->resetBrowserState();
    }

    private function resetBrowserState(): void
    {
        $this->bulanKey = null;
        $this->bulanLabel = null;
        $this->klasifikasi = null;
        $this->selectedIds = [];
        $this->urutanFolder = 'awal';
    }

    // ------------------------------------------------------------------
    // Query surat -- migrasi dari SuratApiService.fetchSurat() (Flutter) +
    // filter-filter di App\Http\Controllers\Api\SuratController::list(),
    // dijalankan langsung lewat Eloquent (bukan panggil API sendiri).
    // ------------------------------------------------------------------

    /**
     * @param  array{status?:?string,jenis?:?string,disposisi?:?string,tahap?:?string,peran?:?string,diproses_oleh?:?string,kabag_dituju?:?string,ascending?:bool}  $opts
     */
    private function fetchSurat(array $opts): Collection
    {
        return $this->sidebarService()->fetchSurat($opts);
    }

    /** Daftar surat untuk menu yang sedang aktif -- migrasi dari _loadDaftar(). */
    #[Computed]
    public function daftar(): Collection
    {
        $nama = $this->currentUser()->nama;

        $hasil = match ($this->activeMenu()) {
            // Dashboard gabungan -- antrian Surat Keluar yang perlu diproses
            // + Surat Masuk yang belum direspon, DUA-DUANYA sudah diskop per
            // akun (lihat daftarDashboardKeluar()/daftarDashboardMasuk() --
            // sama persis dengan menu terpisah "Dashboard Surat Masuk"/"Dashboard
            // Surat Keluar" di bawah, cuma digabung jadi satu daftar).
            MenuSurat::Dashboard => $this->daftarDashboardKeluar($nama)->concat($this->daftarDashboardMasuk($nama)),

            MenuSurat::DashboardKeluar => $this->daftarDashboardKeluar($nama),

            MenuSurat::DashboardMasuk => $this->daftarDashboardMasuk($nama),

            MenuSurat::SuratTerespon => $this->labelGerbangMasuk() !== null
                ? $this->fetchSurat(['jenis' => 'masuk', 'diproses_oleh' => $nama, 'ascending' => true])
                : $this->fetchSurat(['jenis' => 'masuk', 'disposisi' => $nama, 'ascending' => true])
                    ->filter(fn ($s) => $this->sudahDireponOleh($s, $nama)),

            // Gabungan bekas dua menu terpisah "Arsip Surat Keluar" (sudah
            // selesai -- disetujui/ditolak) & "Progress Surat Keluar" (masih
            // berjalan) -- lihat daftarArsipKeluar()/daftarProgressKeluar().
            MenuSurat::ArsipProgresKeluar => $this->daftarProgressKeluar($nama)->concat($this->daftarArsipKeluar($nama))->unique('id'),

            MenuSurat::JajaranAnggota => $this->anggota !== null
                ? $this->fetchSurat(['jenis' => 'masuk', 'disposisi' => $this->anggota, 'ascending' => true])
                : collect(),

            // arsipMasuk: gabungan seluruh surat masuk apa pun status/tujuannya.
            default => $this->fetchSurat([
                'status' => $this->activeMenu()->status(), 'jenis' => $this->activeMenu()->jenisFilter(), 'ascending' => true,
            ]),
        };

        return $hasil->values();
    }

    /** Surat Keluar yang sudah SELESAI (disetujui/ditolak) -- separuh "Arsip" dari menu ArsipProgresKeluar. */
    private function daftarArsipKeluar(string $nama): Collection
    {
        return $this->isPejabat()
            ? $this->fetchSurat(['jenis' => 'keluar', 'peran' => $nama, 'ascending' => true])
                ->filter(fn ($s) => $s->approval->where('role', $nama)->contains(fn ($a) => $a->status !== 'menunggu'))
            : $this->fetchSurat(['jenis' => 'keluar', 'ascending' => true])
                ->filter(fn ($s) => in_array($s->status, ['disetujui', 'ditolak'], true));
    }

    /**
     * Surat Keluar yang MASIH BERJALAN (status 'menunggu') -- separuh
     * "Progress" dari menu ArsipProgresKeluar. Admin & akun yang berhak
     * memantau lintas jalur (Kabagtu/Turmin Subditbinum, role='turmin')
     * lihat SEMUA jalur tanpa filter -- ini SUDAH mencakup surat rantai
     * manual (kabag_dituju null) karena tidak ada filter kabag_dituju sama
     * sekali di kasus ini. Akun dengan bagian nyata (bagianEfektif(),
     * termasuk akun gerbang Kasubdit yang kebetulan juga role='pimpinan'
     * tapi anggota SATU bag_keluar tertentu) dibatasi ke bagiannya sendiri,
     * DITAMBAH surat rantai manual mana pun yang benar-benar melibatkan
     * mereka (lihat daftarProgressSuratKeluarManual()) -- surat manual
     * TIDAK terikat Bag (kabag_dituju null) jadi tidak pernah ikut kefilter
     * kabag_dituju di atas, harus digabung terpisah. Akun lain (tidak match
     * ketiganya) hanya lihat surat rantai manual yang melibatkan mereka --
     * SENGAJA bukan fallback "tanpa filter" lagi (dulu begitu, celahnya:
     * akun gerbang Kasubdit uji coba tanpa bagian jelas bisa melihat
     * progress jalur lain yang sama sekali tidak terkait).
     */
    private function daftarProgressKeluar(string $nama): Collection
    {
        return match (true) {
            !$this->isPejabat(), $this->bisaMonitorProgressLintasJalur() => $this->fetchSurat([
                'jenis' => 'keluar', 'status' => 'menunggu', 'ascending' => true,
            ]),
            $this->bagianEfektif() !== null => $this->fetchSurat([
                'jenis' => 'keluar', 'status' => 'menunggu', 'kabag_dituju' => $this->bagianEfektif(), 'ascending' => true,
            ])->concat($this->daftarProgressSuratKeluarManual($nama))->unique('id'),
            default => $this->daftarProgressSuratKeluarManual($nama),
        };
    }

    /** Akun pejabat: surat keluar yang gilirannya SEDANG ada di tangan mereka. Akun admin: SEMUA surat keluar yang menunggu diproses siapa pun. */
    /**
     * Surat Keluar rantai manual (kabag_dituju null, lihat SuratForm::submit())
     * yang MELIBATKAN akun ini di rantai approval-nya -- dipakai
     * daftarProgressKeluar() supaya surat manual tetap terpantau walau
     * tidak terikat Bag mana pun untuk difilter lewat kabag_dituju.
     */
    private function daftarProgressSuratKeluarManual(string $nama): Collection
    {
        return $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu', 'peran' => $nama, 'ascending' => true])
            ->filter(fn ($s) => $s->kabag_dituju === null);
    }

    private function daftarDashboardKeluar(string $nama): Collection
    {
        return $this->isPejabat()
            ? $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu', 'tahap' => $nama, 'ascending' => true])
            : $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu', 'ascending' => true]);
    }

    /** Akun pejabat: surat masuk yang butuh respon MEREKA. Akun admin: SEMUA surat masuk yang belum direspon siapa pun. */
    private function daftarDashboardMasuk(string $nama): Collection
    {
        return $this->isPejabat()
            ? ($this->labelGerbangMasuk() !== null
                ? ($this->isTurminMasuk()
                    ? collect()
                    : $this->fetchSurat(['jenis' => 'masuk', 'status' => 'menunggu', 'peran' => $this->labelGerbangMasuk(), 'ascending' => true]))
                : $this->fetchSurat(['jenis' => 'masuk', 'disposisi' => $nama, 'status' => 'disetujui', 'ascending' => true])
                    ->reject(fn ($s) => $this->sudahDireponOleh($s, $nama)))
            : $this->fetchSurat(['jenis' => 'masuk', 'ascending' => true])
                ->filter(fn ($s) => $this->belumDirespon($s));
    }

    /** Diurutkan tanggal paling awal masuk dulu (FIFO) -- dipakai SEMUA tampilan menu surat. */
    #[Computed]
    public function daftarTerurut(): Collection
    {
        return $this->daftar()->sort(fn ($a, $b) => $this->tanggalUrut($a) <=> $this->tanggalUrut($b))->values();
    }

    public function sedangMencari(): bool
    {
        return trim($this->search) !== '';
    }

    #[Computed]
    public function daftarTerfilter(): Collection
    {
        return $this->sedangMencari() ? collect() : $this->daftarTerurut();
    }

    // ------------------------------------------------------------------
    // Pencarian -- migrasi dari _muatSemuaSuratAkun/_hasilPencarian.
    // ------------------------------------------------------------------

    #[Computed]
    public function semuaSuratAkun(): Collection
    {
        $user = $this->currentUser();

        if (!$this->isPejabat() || $this->isPimpinan()) {
            return $this->fetchSurat(['jenis' => 'masuk'])->concat($this->fetchSurat(['jenis' => 'keluar']));
        }

        $labelGerbang = $this->labelGerbangMasuk();
        if ($labelGerbang !== null) {
            return $this->fetchSurat(['jenis' => 'masuk', 'diproses_oleh' => $user->nama]);
        }

        $pejabatRole = $this->pejabatRoleLabel();
        $isGateRole = $pejabatRole !== null && DisposisiRoles::isGateRoleMasuk($pejabatRole);
        $masuk = $isGateRole
            ? $this->fetchSurat(['jenis' => 'masuk', 'peran' => $pejabatRole])
            : $this->fetchSurat(['jenis' => 'masuk', 'disposisi' => $user->nama]);
        $keluar = $this->fetchSurat(['jenis' => 'keluar', 'peran' => $user->nama]);

        return $masuk->concat($keluar);
    }

    #[Computed]
    public function hasilPencarian(): Collection
    {
        $query = mb_strtolower(trim($this->search));
        if ($query === '') {
            return collect();
        }

        return $this->semuaSuratAkun()
            ->filter(fn ($s) => $this->cocokPencarian($s, $query))
            ->sort(fn ($a, $b) => $this->tanggalUrut($b) <=> $this->tanggalUrut($a))
            ->values();
    }

    private function cocokPencarian(Surat $s, string $query): bool
    {
        if (str_contains(mb_strtolower($s->perihal ?? ''), $query)) {
            return true;
        }
        if ($s->nomor_surat && str_contains(mb_strtolower($s->nomor_surat), $query)) {
            return true;
        }
        if ($s->nama_pengaju && str_contains(mb_strtolower($s->nama_pengaju), $query)) {
            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Notifikasi (badge sidebar + dropdown lonceng) -- migrasi dari
    // _refreshBadgeDashboard/_refreshNotifikasiAdmin/_jumlahNotifikasi.
    // ------------------------------------------------------------------

    /**
     * Pengumuman admin ke SELURUH akun -- beda dari notifikasi surat
     * masuk/keluar di bawah (yang otomatis per-surat & khusus akun
     * terkait). Ini teks bebas dari admin, sama untuk semua role, tampil
     * sampai admin sendiri hapus lewat App\Livewire\PengumumanAdmin. Ikut
     * ter-refresh otomatis lewat wire:poll.8s yang sudah ada di
     * livewire.dashboard, jadi begitu admin cabut, hilang dari layar user
     * lain dalam <=8 detik tanpa perlu reload manual.
     */
    #[Computed]
    public function pengumumanAktif(): Collection
    {
        return Pengumuman::query()->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function notifikasiKeluar(): Collection
    {
        return $this->sidebarService()->notifikasiKeluar();
    }

    #[Computed]
    public function notifikasiMasuk(): Collection
    {
        return $this->sidebarService()->notifikasiMasuk();
    }

    #[Computed]
    public function notifikasiArsipKeluar(): Collection
    {
        return $this->sidebarService()->notifikasiArsipKeluar();
    }

    #[Computed]
    public function notifikasiAdmin(): Collection
    {
        if ($this->isPejabat()) {
            return collect();
        }

        $keluar = $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu']);
        $masuk = $this->fetchSurat(['jenis' => 'masuk'])->filter(fn ($s) => $this->belumDirespon($s));

        return $keluar->concat($masuk)->sort(fn ($a, $b) => $this->tanggalUrut($b) <=> $this->tanggalUrut($a))->values();
    }

    #[Computed]
    public function jumlahNotifikasi(): int
    {
        if (!$this->isPejabat()) {
            return $this->notifikasiAdmin()->count();
        }

        return $this->notifikasiMasuk()->count() + $this->notifikasiKeluar()->count() + $this->notifikasiArsipKeluar()->count();
    }

    /** Dipanggil tombol "Konfirmasi"/"Abaikan" pada notifikasi Surat Keluar yang sudah disetujui/ditolak. */
    public function konfirmasiSuratDiterima(int $suratId): void
    {
        $user = $this->currentUser();
        $surat = Surat::query()->find($suratId);
        if (!$surat || $surat->jenis !== 'keluar' || !in_array($surat->status, ['disetujui', 'ditolak'], true)) {
            return;
        }

        $stage1 = DB::table('surat_approval')->where('surat_id', $suratId)->where('urutan', 1)->first();
        if (!$stage1 || $stage1->role !== $user->nama) {
            return;
        }

        $surat->konfirmasi_kaur_at = now();
        $surat->save();

        $keterangan = $surat->status === 'ditolak'
            ? "Abaikan notifikasi Surat Keluar yang ditolak ({$surat->perihal}), tidak akan ditindaklanjuti"
            : "Konfirmasi penerimaan Surat Keluar yang sudah disetujui ({$surat->perihal})";
        ActivityLogger::log(request(), $user->username, $user->nama, 'update', $keterangan, $surat->id);
    }

    // ------------------------------------------------------------------
    // Live polling (8 detik, sama seperti Timer.periodic Flutter) + deteksi
    // notifikasi baru untuk pop-up.
    // ------------------------------------------------------------------

    public function pollLive(): void
    {
        $this->deteksiNotifikasiBaru();
    }

    private function deteksiNotifikasiBaru(): void
    {
        // Dulu SENGAJA dihentikan total untuk admin (notifikasiKeluar()/
        // notifikasiMasuk() dulu memang cuma diisi untuk pejabat) -- sekarang
        // dua-duanya sudah diskop juga untuk admin (lihat
        // App\Services\SidebarMenuService), jadi pop-up "Surat Keluar Perlu
        // Ditindak"/"Surat Masuk Diterima" ikut jalan untuk admin.
        // notifikasiArsipKeluar() TETAP kosong untuk admin secara alami
        // (konsep "saya Kaur yang input pertama" memang tidak berlaku untuk
        // akun admin generik) -- tidak perlu guard terpisah.
        $perluDitindak = $this->notifikasiKeluar();
        $arsipKeluar = $this->notifikasiArsipKeluar();
        $notifMasuk = $this->notifikasiMasuk();

        $idPerlu = $perluDitindak->pluck('id')->filter()->values()->all();
        $idArsip = $arsipKeluar->pluck('id')->filter()->values()->all();
        $idMasuk = $notifMasuk->pluck('id')->filter()->values()->all();

        $prevPerlu = $this->idNotifikasiKeluarDiketahui;
        $prevArsip = $this->idArsipKeluarDiketahui;
        $prevMasuk = $this->idNotifikasiMasukDiketahui;

        $this->idNotifikasiKeluarDiketahui = $idPerlu;
        $this->idArsipKeluarDiketahui = $idArsip;
        $this->idNotifikasiMasukDiketahui = $idMasuk;

        if ($prevPerlu === null || $prevArsip === null || $prevMasuk === null) {
            // Baseline poll pertama -- jangan tampilkan apa pun dulu.
            return;
        }

        $events = [];
        foreach ($perluDitindak as $s) {
            if ($s->id && !in_array($s->id, $prevPerlu, true)) {
                $events[] = ['judul' => 'Surat Keluar Perlu Ditindak', 'perihal' => $s->perihal, 'id' => $s->id, 'warna' => 'gold'];
            }
        }
        foreach ($arsipKeluar as $s) {
            if ($s->id && !in_array($s->id, $prevArsip, true)) {
                $events[] = [
                    'judul' => $s->status === 'disetujui' ? 'Surat Keluar Disetujui' : 'Surat Keluar Ditolak',
                    'perihal' => $s->perihal, 'id' => $s->id,
                    'warna' => $s->status === 'disetujui' ? 'green' : 'red',
                ];
            }
        }
        foreach ($notifMasuk as $s) {
            if ($s->id && !in_array($s->id, $prevMasuk, true)) {
                $events[] = ['judul' => 'Surat Masuk Diterima', 'perihal' => $s->perihal, 'id' => $s->id, 'warna' => 'green'];
            }
        }

        if ($events) {
            $this->dispatch('notifikasi-baru', events: $events);
        }
    }

    // ------------------------------------------------------------------
    // Status respons/approval per surat -- migrasi dari getter2 di
    // lib/models/surat.dart (Surat.adaDisposisiBelumDirespon,
    // disposisiUntukNama, approvalUntuk).
    // ------------------------------------------------------------------

    private function tanggalUrut(Surat $s): \Illuminate\Support\Carbon
    {
        return $s->tanggalUrut();
    }

    /** Wrapper publik dari tanggalUrut() untuk dipakai langsung dari Blade (resources/views/livewire/dashboard.blade.php). */
    public function tanggalUrutUntuk(Surat $s): \Illuminate\Support\Carbon
    {
        return $this->tanggalUrut($s);
    }

    /**
     * `surat.disposisi` adalah NAMA KOLOM (teks comma-separated) SEKALIGUS
     * nama relasi Eloquent (Surat::disposisi(), hasMany SuratDisposisi) --
     * getAttribute() bawaan Eloquent SELALU memprioritaskan kolom asli di
     * atas relasi bernama sama, jadi `$s->disposisi` TIDAK PERNAH memberi
     * koleksi relasi walau sudah di-eager-load lewat with('disposisi') di
     * fetchSurat(). Helper ini mengambil relasi yang benar lewat
     * getRelation() -- SEMUA Surat yang lewat sini datang dari fetchSurat()
     * yang selalu eager-load 'disposisi', jadi aman dipakai langsung.
     */
    private function disposisiRelasi(Surat $s): Collection
    {
        return $s->relationLoaded('disposisi') ? $s->getRelation('disposisi') : $s->disposisi()->get();
    }

    private function sudahDireponOleh(Surat $s, string $nama): bool
    {
        $d = $this->disposisiRelasi($s)->firstWhere('role', $nama);

        return $d !== null && $d->catatan !== null && trim($d->catatan) !== '';
    }

    /** Surat.adaDisposisiBelumDirespon -- true kalau ADA pejabat tujuan mana pun yang belum merespon. */
    private function belumDirespon(Surat $s): bool
    {
        if ($s->jenis !== 'masuk') {
            return false;
        }
        foreach ($this->disposisiRelasi($s) as $d) {
            if ($d->catatan === null || trim($d->catatan) === '') {
                return true;
            }
        }

        return false;
    }

    private function approvalUntuk(Surat $s, string $roleLabel): mixed
    {
        return $s->approval->firstWhere('role', $roleLabel);
    }

    /**
     * Status Sudah/Belum Direspon satu surat -- migrasi dari
     * _BadgeStatusDisposisi._sudahDirespon (surat_bar_list.dart) DAN
     * _SuratKategoriBrowserState._belumDirespon (kategori browser, negasinya).
     * CATATAN: viewingRole (DisposisiRole TERTYPE) dari Flutter SENGAJA
     * tidak direplikasi jadi parameter terpisah -- di SELURUH pemakaian
     * SuratKategoriBrowser dari dashboard_screen.dart, parameter itu TIDAK
     * PERNAH diisi (selalu null), dan untuk SuratBarList label DisposisiRole
     * itu SELALU persis sama dengan nama akun (label enum = nama), jadi
     * matching nama mentah ($viewingRoleLabel) di bawah ini identik
     * perilakunya dengan disposisiUntuk(DisposisiRole) versi Dart.
     */
    private function suratSudahDirespon(Surat $s, ?string $viewingRoleLabel, ?string $viewingRoleNama, bool $selaluSudahDirespon): bool
    {
        if ($selaluSudahDirespon) {
            return true;
        }
        if ($viewingRoleNama !== null) {
            return $this->sudahDireponOleh($s, $viewingRoleNama);
        }
        if ($viewingRoleLabel !== null && DisposisiRoles::isGateRoleMasuk($viewingRoleLabel)) {
            $a = $this->approvalUntuk($s, $viewingRoleLabel);

            return $a !== null && $a->status !== 'menunggu';
        }
        if ($viewingRoleLabel !== null) {
            return $this->sudahDireponOleh($s, $viewingRoleLabel);
        }
        if ($this->disposisiRelasi($s)->isEmpty()) {
            return false;
        }

        return !$this->belumDirespon($s);
    }

    /** viewingRole untuk tampilan flat (SuratBarList) menu Dashboard gabungan/Dashboard Surat Masuk. */
    private function viewingRoleLabelUntukFlatList(): ?string
    {
        return in_array($this->activeMenu(), [MenuSurat::Dashboard, MenuSurat::DashboardMasuk], true)
            ? $this->pejabatRoleLabel()
            : null;
    }

    /** Status badge satu baris surat di daftar flat (dashboard/pencarian/notifikasi) -- null kalau surat keluar. */
    public function statusDisposisiUntukBar(Surat $s, bool $modePencarian = false): ?bool
    {
        if ($s->jenis !== 'masuk') {
            return null;
        }
        $roleLabel = $modePencarian ? $this->pejabatRoleLabel() : $this->viewingRoleLabelUntukFlatList();

        return $this->suratSudahDirespon($s, $roleLabel, null, false);
    }

    // ------------------------------------------------------------------
    // Folder browser (Bulan -> Klasifikasi -> daftar) -- migrasi dari
    // SuratKategoriBrowser (surat_kategori_browser.dart).
    // ------------------------------------------------------------------

    public function kelompokkanPerBulan(): bool
    {
        return true;
    }

    #[Computed]
    public function isMasukList(): bool
    {
        return $this->daftarTerurut()->contains(fn ($s) => $s->jenis === 'masuk');
    }

    private function viewingRoleNamaUntukBrowser(): ?string
    {
        return $this->activeMenu() === MenuSurat::JajaranAnggota ? $this->anggota : null;
    }

    private function selaluSudahDireponUntukBrowser(): bool
    {
        return $this->activeMenu() === MenuSurat::SuratTerespon;
    }

    private function belumDireponUntukBrowser(Surat $s): bool
    {
        if ($s->jenis !== 'masuk') {
            return false;
        }

        return !$this->suratSudahDirespon($s, null, $this->viewingRoleNamaUntukBrowser(), $this->selaluSudahDireponUntukBrowser());
    }

    /** Status Sudah/Belum Direspon dipakai Blade untuk badge per baris di dalam folder browser. */
    public function statusDisposisiUntukBrowser(Surat $s): ?bool
    {
        if ($s->jenis !== 'masuk') {
            return null;
        }

        return $this->suratSudahDirespon($s, null, $this->viewingRoleNamaUntukBrowser(), $this->selaluSudahDireponUntukBrowser());
    }

    private function bulanKeyDari(Surat $s): string
    {
        return $this->tanggalUrut($s)->format('Y-m');
    }

    private function bandingkanKlasifikasi(string $a, string $b): int
    {
        $ia = array_search($a, self::KLASIFIKASI_URUTAN_BAKU, true);
        $ib = array_search($b, self::KLASIFIKASI_URUTAN_BAKU, true);
        if ($ia !== false && $ib !== false) {
            return $ia <=> $ib;
        }
        if ($ia !== false) {
            return -1;
        }
        if ($ib !== false) {
            return 1;
        }

        return strcmp($a, $b);
    }

    /** Sumber data untuk level klasifikasi & leaf. */
    private function daftarBulanTerpilih(): Collection
    {
        $daftar = $this->daftarTerurut();
        if (!$this->kelompokkanPerBulan()) {
            return $daftar;
        }
        if ($this->bulanKey === null) {
            return collect();
        }

        return $daftar->filter(fn ($s) => $this->bulanKeyDari($s) === $this->bulanKey)->values();
    }

    #[Computed]
    public function bulanGroups(): array
    {
        $daftar = $this->daftarTerurut();
        $byMonth = [];
        foreach ($daftar as $s) {
            $byMonth[$this->bulanKeyDari($s)][] = $s;
        }

        $keys = array_keys($byMonth);
        sort($keys);

        if ($this->urutanFolder === 'belum_direspon') {
            usort($keys, function ($a, $b) use ($byMonth) {
                $aAda = collect($byMonth[$a])->contains(fn ($s) => $this->belumDireponUntukBrowser($s));
                $bAda = collect($byMonth[$b])->contains(fn ($s) => $this->belumDireponUntukBrowser($s));
                if ($aAda !== $bAda) {
                    return $aAda ? -1 : 1;
                }

                return $a <=> $b;
            });
        }

        $groups = [];
        foreach ($keys as $key) {
            [$year, $month] = explode('-', $key);
            $groups[] = [
                'key' => $key,
                'label' => self::NAMA_BULAN[(int) $month].':'.$year,
                'jumlah' => count($byMonth[$key]),
                'ids' => collect($byMonth[$key])->pluck('id')->filter()->values()->all(),
                'ada_belum_direspon' => collect($byMonth[$key])->contains(fn ($s) => $this->belumDireponUntukBrowser($s)),
            ];
        }

        return $groups;
    }

    #[Computed]
    public function klasifikasiGroups(): array
    {
        $daftar = $this->daftarBulanTerpilih();
        $byKlas = [];
        foreach ($daftar as $s) {
            if ($s->klasifikasi === null) {
                continue;
            }
            $byKlas[$s->klasifikasi][] = $s;
        }

        $keys = array_keys($byKlas);
        usort($keys, fn ($a, $b) => $this->bandingkanKlasifikasi($a, $b));

        if ($this->urutanFolder === 'belum_direspon') {
            usort($keys, function ($a, $b) use ($byKlas) {
                $aAda = collect($byKlas[$a])->contains(fn ($s) => $this->belumDireponUntukBrowser($s));
                $bAda = collect($byKlas[$b])->contains(fn ($s) => $this->belumDireponUntukBrowser($s));
                if ($aAda !== $bAda) {
                    return $aAda ? -1 : 1;
                }

                return $this->bandingkanKlasifikasi($a, $b);
            });
        }

        $groups = [];
        foreach ($keys as $k) {
            $groups[] = [
                'label' => $k,
                'jumlah' => count($byKlas[$k]),
                'ids' => collect($byKlas[$k])->pluck('id')->filter()->values()->all(),
                'ada_belum_direspon' => collect($byKlas[$k])->contains(fn ($s) => $this->belumDireponUntukBrowser($s)),
            ];
        }

        return $groups;
    }

    #[Computed]
    public function leafSurat(): Collection
    {
        $daftar = $this->daftarBulanTerpilih()->filter(fn ($s) => $s->klasifikasi === $this->klasifikasi)->values();

        if ($this->urutanFolder === 'belum_direspon') {
            return $daftar->sort(function ($a, $b) {
                $aAda = $this->belumDireponUntukBrowser($a);
                $bAda = $this->belumDireponUntukBrowser($b);
                if ($aAda !== $bAda) {
                    return $aAda ? -1 : 1;
                }

                return $this->tanggalUrut($a) <=> $this->tanggalUrut($b);
            })->values();
        }

        return $daftar;
    }

    public function bukaBulan(string $key, string $label): void
    {
        $this->bulanKey = $key;
        $this->bulanLabel = $label;
    }

    public function kembaliBulan(): void
    {
        $this->bulanKey = null;
        $this->bulanLabel = null;
    }

    public function bukaKlasifikasi(string $klasifikasi): void
    {
        $this->klasifikasi = $klasifikasi;
    }

    public function kembaliKlasifikasi(): void
    {
        $this->klasifikasi = null;
    }

    /** Livewire lifecycle hook -- dipanggil otomatis saat $urutanFolder diubah lewat wire:model.live (dropdown "Urutkan"). */
    public function updatedUrutanFolder(string $value): void
    {
        if (!in_array($value, ['awal', 'belum_direspon'], true)) {
            $this->urutanFolder = 'awal';
        }
    }

    // ------------------------------------------------------------------
    // Seleksi & hapus massal (admin saja) -- migrasi dari
    // _SuratKategoriBrowserState (_toggleSuratSelection, _toggleGroupSelection,
    // _hapusTerpilih).
    // ------------------------------------------------------------------

    public function canDelete(): bool
    {
        return !$this->isPejabat();
    }

    public function toggleSelectSurat(int $id): void
    {
        if (!$this->canDelete()) {
            return;
        }
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function toggleSelectGroup(array $ids): void
    {
        if (!$this->canDelete()) {
            return;
        }
        $allSelected = count($ids) > 0 && count(array_diff($ids, $this->selectedIds)) === 0;
        if ($allSelected) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $ids));
        } else {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $ids)));
        }
    }

    public function statusSeleksiGroup(array $ids): ?bool
    {
        if (empty($ids)) {
            return false;
        }
        $selectedCount = count(array_intersect($ids, $this->selectedIds));
        if ($selectedCount === 0) {
            return false;
        }
        if ($selectedCount === count($ids)) {
            return true;
        }

        return null;
    }

    public function hapusTerpilih(): void
    {
        $user = $this->currentUser();
        if ($user->role !== 'admin' || empty($this->selectedIds)) {
            return;
        }

        $this->isDeleting = true;

        $ids = $this->selectedIds;
        $suratRows = Surat::query()->whereIn('id', $ids)->get(['id', 'nomor_surat']);
        $deletedIds = $suratRows->pluck('id')->all();
        $nomorList = $suratRows->map(fn ($r) => $r->nomor_surat ?? "surat #{$r->id}")->all();
        $fileNames = $deletedIds ? SuratFile::query()->whereIn('surat_id', $deletedIds)->pluck('file_name')->all() : [];

        $deletedCount = Surat::query()->whereIn('id', $ids)->delete();

        ActivityLogger::log(request(), $user->username, $user->nama, 'delete', "Menghapus $deletedCount surat: ".implode(', ', $nomorList));

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

        $this->selectedIds = [];
        $this->bulanKey = null;
        $this->bulanLabel = null;
        $this->klasifikasi = null;
        $this->isDeleting = false;

        $this->dispatch('notify', message: "$deletedCount surat berhasil dihapus.");
    }
}
