<?php

namespace App\Services;

use App\Livewire\Dashboard\DisposisiRoles;
use App\Livewire\Dashboard\MenuSurat;
use App\Models\BagMasuk;
use App\Models\PimpinanAnggota;
use App\Models\Surat;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber TUNGGAL untuk logika "menu surat" (Surat Masuk/Surat Keluar/Jajaran
 * Anggota di sidebar, lengkap dengan badge notifikasi) -- diekstrak VERBATIM
 * dari App\Livewire\Dashboard (lihat komentar identik di sana) supaya bisa
 * dipakai konsisten di SEMUA halaman lewat layouts.app, bukan cuma saat
 * komponen Dashboard aktif (sebelumnya sidebar-extra Dashboard dirender di
 * luar boundary komponen Livewire-nya sendiri lewat @section/@yield --
 * hasilnya menu ini SAMA SEKALI TIDAK MUNCUL/beda-beda di halaman lain
 * seperti Tinjau Surat). App\Livewire\Dashboard tetap memanggil method yang
 * sama persis lewat delegasi supaya tidak ada dua salinan logika yang bisa
 * berbeda hasilnya (lihat komentar delegasi di sana).
 */
class SidebarMenuService
{
    public function __construct(private readonly User $user)
    {
    }

    public function isPejabat(): bool
    {
        return $this->user->role !== 'admin';
    }

    public function isPimpinan(): bool
    {
        return $this->user->role === 'pimpinan';
    }

    public function isTurminMasuk(): bool
    {
        return $this->user->role === 'turmin';
    }

    /** Identitas pejabat SPESIFIK -- nama akun yang cocok PERSIS salah satu dari 20 label DisposisiRole tetap. */
    public function pejabatRoleLabel(): ?string
    {
        return DisposisiRoles::isValidLabel($this->user->nama) ? $this->user->nama : null;
    }

    /** Bagian/jalur milik akun pejabat 5-jalur lama (null kalau tidak punya struktur Kabag/Kasi/Kaur ATAU nama tidak cocok). */
    public function bagianSaya(): ?string
    {
        return DisposisiRoles::bagianFor($this->pejabatRoleLabel());
    }

    /**
     * Sama seperti bagianSaya(), tapi untuk akun Bag/"grup dalam grup" nama
     * bebas (lihat App\Services\BagService::bagKeluarSaya()).
     *
     * PENTING: SENGAJA TIDAK mengecualikan akun role='pimpinan' di sini
     * (dulu ada `|| $this->isPimpinan()` -- dibuang). role='pimpinan'
     * dipakai untuk DUA hal berbeda: Pimpinan sungguhan (memang harus lihat
     * seluruh jajaran) DAN akun gerbang Kasubdit Surat Masuk biasa (lihat
     * labelGerbangMasuk()) yang kebetulan juga role='pimpinan' tapi
     * keanggotaannya di rantai Surat KELUAR (bag_keluar/bag_member) TETAP
     * harus dihormati kalau ada -- kalau tidak, akun gerbang seperti itu
     * jatuh ke bagianEfektif()=null lalu (lewat filter kabag_dituju yang
     * diabaikan saat null) malah melihat progress SEMUA jalur, termasuk
     * milik jalur lain yang sama sekali tidak terkait dengannya.
     */
    public function bagianSayaDinamis(): ?string
    {
        if (!$this->isPejabat() || $this->bisaMonitorProgressLintasJalur() || $this->bagianSaya() !== null) {
            return null;
        }

        return app(BagService::class)->bagKeluarSaya($this->user->id);
    }

    /** Dipakai di SEMUA tempat yang sebelumnya cuma memakai bagianSaya() -- gabungan jalur lama + jalur baru. */
    public function bagianEfektif(): ?string
    {
        return $this->bagianSaya() ?? $this->bagianSayaDinamis();
    }

    /** Kabagtu & Turmin (SEMUA akun turmin, bukan cuma "Turmin Subditbinum") perlu memantau Progress Surat Keluar lintas jalur. */
    public function bisaMonitorProgressLintasJalur(): bool
    {
        $role = $this->pejabatRoleLabel();

        return $role === DisposisiRoles::KABAGTU_SUBDITBINUM
            || $role === DisposisiRoles::TURMIN_SUBDITBINUM
            || $this->user->role === 'turmin';
    }

    private ?bool $adaBagKasubditSayaCache = null;

    /**
     * true kalau akun Pimpinan ini terdaftar sebagai Kasubdit di Bag Surat
     * Masuk manapun. Dipanggil berkali-kali per request lewat
     * labelGerbangMasuk() dkk -- di-cache manual supaya tidak query ulang
     * tiap panggilan (lihat komentar cache serupa di
     * App\Livewire\Dashboard::sidebarService()).
     */
    public function adaBagKasubditSaya(): bool
    {
        if (!$this->isPimpinan()) {
            return false;
        }

        return $this->adaBagKasubditSayaCache ??= BagMasuk::query()->where('kasubdit_user_id', $this->user->id)->exists();
    }

    /** Label generik tahap gerbang Surat Masuk ('Turmin'/'Kasubdit') milik akun ini, null kalau bukan gerbang. */
    public function labelGerbangMasuk(): ?string
    {
        if ($this->isTurminMasuk()) {
            return 'Turmin';
        }
        if ($this->isPimpinan() && $this->adaBagKasubditSaya()) {
            return 'Kasubdit';
        }

        return null;
    }

    /** Nama pejabat (urut abjad) yang jadi anggota jajaran akun Pimpinan ini -- lihat panel "Struktur Anggota". */
    public function namaJajaranSaya(): array
    {
        if (!$this->isPimpinan()) {
            return [];
        }

        $anggotaIds = PimpinanAnggota::query()
            ->where('pimpinan_id', $this->user->id)
            ->pluck('anggota_id');

        if ($anggotaIds->isEmpty()) {
            return [];
        }

        $nama = User::query()->whereIn('id', $anggotaIds)->pluck('nama')->all();
        sort($nama);

        return $nama;
    }

    public function menuMasukList(): array
    {
        return $this->isPejabat()
            ? [MenuSurat::DashboardMasuk, MenuSurat::SuratTerespon]
            : [MenuSurat::DashboardMasuk, MenuSurat::ArsipMasuk];
    }

    /**
     * Arsip & Progres Surat Keluar SELALU muncul (dulu "Progress" baru
     * muncul kalau bagianEfektif()/bisaMonitorProgressLintasJalur()) --
     * sekarang kedua menu itu sudah digabung jadi satu tab, jadi
     * pembatasan itu pindah ke ISI datanya (lihat
     * App\Livewire\Dashboard::daftarProgressKeluar()), bukan lagi ke
     * tampil/tidaknya tab ini.
     */
    public function menuKeluarList(): array
    {
        return [MenuSurat::DashboardKeluar, MenuSurat::ArsipProgresKeluar];
    }

    public function badgeUntukMenu(MenuSurat $menu): ?int
    {
        return match ($menu) {
            MenuSurat::DashboardMasuk => $this->notifikasiMasuk()->count(),
            MenuSurat::DashboardKeluar => $this->notifikasiKeluar()->count(),
            default => null,
        };
    }

    public function notifikasiKeluar(): Collection
    {
        // Admin tidak punya "giliran" sendiri (bukan salah satu tahap
        // approval bernama) -- lihat seluruh Surat Keluar yang lagi
        // menunggu diproses SIAPA PUN, sama seperti separuh kanan
        // MenuSurat::Dashboard gabungan yang tadinya cuma ada di
        // App\Livewire\Dashboard::daftar().
        if (!$this->isPejabat()) {
            return $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu']);
        }

        return $this->fetchSurat(['jenis' => 'keluar', 'status' => 'menunggu', 'tahap' => $this->user->nama]);
    }

    public function notifikasiMasuk(): Collection
    {
        // Sama seperti notifikasiKeluar() -- admin lihat SEMUA Surat Masuk
        // yang belum direspon pejabat tujuan mana pun.
        if (!$this->isPejabat()) {
            return $this->fetchSurat(['jenis' => 'masuk'])
                ->filter(fn ($s) => $this->belumDirespon($s))
                ->values();
        }

        $labelGerbang = $this->labelGerbangMasuk();
        $pejabatRole = $this->pejabatRoleLabel();
        $isGateRoleLama = $pejabatRole !== null && DisposisiRoles::isGateRoleMasuk($pejabatRole);

        if ($labelGerbang !== null) {
            return $this->isTurminMasuk()
                ? collect()
                : $this->fetchSurat(['jenis' => 'masuk', 'status' => 'menunggu', 'peran' => $labelGerbang]);
        }
        if ($isGateRoleLama) {
            return $this->fetchSurat(['jenis' => 'masuk', 'status' => 'menunggu', 'tahap' => $pejabatRole]);
        }

        return $this->fetchSurat(['jenis' => 'masuk', 'disposisi' => $this->user->nama, 'status' => 'disetujui'])
            ->reject(fn ($s) => $this->sudahDireponOleh($s, $this->user->nama))
            ->values();
    }

    /**
     * Surat Keluar yang sudah disetujui/ditolak dan menunggu konfirmasi Kaur
     * (pemohon awal, urutan=1) -- dipindah VERBATIM dari
     * App\Livewire\Dashboard::notifikasiArsipKeluar() supaya bisa dipakai
     * bersama oleh Dashboard (badge lonceng) & App\Console\Commands\KirimWebPush
     * (push notification), sama seperti notifikasiKeluar()/notifikasiMasuk() di atas.
     */
    public function notifikasiArsipKeluar(): Collection
    {
        if (!$this->isPejabat()) {
            return collect();
        }

        return $this->fetchSurat(['jenis' => 'keluar', 'peran' => $this->user->nama])
            ->filter(function ($s) {
                $first = $s->approval->first();

                return $first
                    && (int) $first->urutan === 1
                    && $first->role === $this->user->nama
                    && in_array($s->status, ['disetujui', 'ditolak'], true)
                    && $s->konfirmasi_kaur_at === null;
            })->values();
    }

    /** Ringkasan lengkap siap-render untuk sidebar (layouts.app) & App\Livewire\Dashboard. */
    public function sidebarData(): array
    {
        $menuMasuk = $this->menuMasukList();
        $menuKeluar = $this->menuKeluarList();
        $badges = [];
        foreach ([...$menuMasuk, ...$menuKeluar] as $item) {
            $badge = $this->badgeUntukMenu($item);
            if ($badge !== null) {
                $badges[$item->value] = $badge;
            }
        }

        return [
            'menuMasuk' => $menuMasuk,
            'menuKeluar' => $menuKeluar,
            'badges' => $badges,
            'isPimpinan' => $this->isPimpinan(),
            'jajaranAnggota' => $this->namaJajaranSaya(),
        ];
    }

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

    /**
     * @var array<string, Collection> Cache in-memory per instance, keyed
     * dari opts TANPA 'ascending' (lihat komentar di fetchSurat()).
     */
    private array $fetchSuratCache = [];

    public function fetchSurat(array $opts): Collection
    {
        $ascending = $opts['ascending'] ?? false;

        // Dashboard (daftar() gabungan) & notifikasi/badge sidebar (lihat
        // notifikasiKeluar()/notifikasiMasuk() di bawah) SERING memanggil
        // fetchSurat() dengan opts PERSIS SAMA (cuma beda 'ascending') dalam
        // satu request yang sama -- tanpa cache ini, query filter+eager-load
        // yang sama (bisa melibatkan banyak baris surat_disposisi/
        // surat_approval) jalan berkali-kali sia-sia. $ascending SENGAJA
        // dikeluarkan dari cache key -- baris yang dikembalikan SAMA PERSIS
        // terlepas arah urut, jadi query-nya cukup dijalankan SEKALI (selalu
        // descending) lalu dibalik di memori kalau caller minta ascending
        // (membalik urutan total 2-kolom (created_at,id) DESC menghasilkan
        // urutan (created_at,id) ASC yang identik dengan query ulang).
        $cacheKey = json_encode([
            $opts['status'] ?? null, $opts['jenis'] ?? null, $opts['disposisi'] ?? null,
            $opts['tahap'] ?? null, $opts['peran'] ?? null, $opts['diproses_oleh'] ?? null,
            $opts['kabag_dituju'] ?? null,
        ]);

        if (!array_key_exists($cacheKey, $this->fetchSuratCache)) {
            $this->fetchSuratCache[$cacheKey] = $this->fetchSuratUncached($opts);
        }

        $hasil = $this->fetchSuratCache[$cacheKey];

        return $ascending ? $hasil->reverse()->values() : $hasil;
    }

    private function fetchSuratUncached(array $opts): Collection
    {
        $status = $opts['status'] ?? null;
        $jenis = $opts['jenis'] ?? null;
        $disposisi = $opts['disposisi'] ?? null;
        $tahap = $opts['tahap'] ?? null;
        $peran = $opts['peran'] ?? null;
        $diprosesOleh = $opts['diproses_oleh'] ?? null;
        $kabagDituju = $opts['kabag_dituju'] ?? null;

        $user = $this->user;

        $query = Surat::query()->with(['disposisi', 'approval']);

        if (in_array($status, ['menunggu', 'disetujui', 'ditolak'], true)) {
            $query->where('status', $status);
        }
        if (in_array($jenis, ['masuk', 'keluar'], true)) {
            $query->where('jenis', $jenis);
        }
        if ($kabagDituju) {
            $query->where('kabag_dituju', $kabagDituju);
        }
        if ($disposisi) {
            $query->whereHas('disposisi', fn ($q) => $q->where('role', $disposisi));
        }
        if ($tahap) {
            // Tahap approval AKTIF (urutan menunggu paling kecil) milik $tahap --
            // sama persis dengan filter `tahap` di SuratController::list().
            $query->whereExists(function ($q) use ($tahap) {
                $q->select(DB::raw(1))->from('surat_approval as sa')
                    ->whereColumn('sa.surat_id', 'surat.id')
                    ->where('sa.role', $tahap)->where('sa.status', 'menunggu')
                    ->whereRaw('sa.urutan = (SELECT MIN(sa2.urutan) FROM surat_approval sa2 WHERE sa2.surat_id = surat.id AND sa2.status = \'menunggu\')');
            });
        }
        if ($peran === 'Kasubdit') {
            $userId = $user->id;
            $query->whereHas('approval', fn ($q) => $q->where('role', 'Kasubdit'))
                ->where(function ($q) use ($userId) {
                    $q->whereNull('kasubdit_gerbang_user_id')->orWhere('kasubdit_gerbang_user_id', $userId);
                });
        } elseif ($peran) {
            $query->whereHas('approval', fn ($q) => $q->where('role', $peran));
        }
        if ($diprosesOleh) {
            $query->whereHas('approval', fn ($q) => $q->where('diproses_oleh', $diprosesOleh));
        }

        // Batas keamanan wajib -- sama persis SuratController::list(): akun
        // bukan admin/pimpinan hanya boleh melihat surat yang benar-benar
        // melibatkan mereka.
        if (!in_array($user->role, ['admin', 'pimpinan'], true)) {
            $nama = $user->nama;
            $query->where(function ($q) use ($nama) {
                $q->whereHas('disposisi', fn ($sub) => $sub->where('role', $nama))
                    ->orWhereHas('approval', fn ($sub) => $sub->where('role', $nama))
                    ->orWhereHas('approval', fn ($sub) => $sub->where('diproses_oleh', $nama));
            });
        }

        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();
    }
}
