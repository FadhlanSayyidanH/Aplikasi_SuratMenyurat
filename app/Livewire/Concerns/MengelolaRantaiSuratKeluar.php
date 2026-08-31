<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\BagService;

/**
 * Logika pemilihan rantai approval Surat Keluar (jalur Bag/Kaur otomatis
 * ATAU rantai manual bebas pilih orang) -- dipakai BERSAMA oleh
 * App\Livewire\Surat\SuratForm (buat surat baru, seluruh rantai) dan
 * App\Livewire\SuratReview (ubah rantai surat yang sudah berjalan, cuma
 * bagian yang belum diproses) supaya kedua tempat itu SELALU konsisten --
 * satu logika, bukan dua salinan yang bisa diam-diam berbeda.
 */
trait MengelolaRantaiSuratKeluar
{
    public array $bagsKeluar = [];

    public ?int $bagTerpilihId = null;

    public ?int $kaurMemberId = null;

    /** true kalau jalur berhasil dipastikan tunggal otomatis (lihat BagService::deteksiJalurKeluarAkun()). */
    public bool $jalurOtomatis = false;

    /** Bag/Kaur hasil deteksi otomatis (disimpan terpisah supaya bisa dikembalikan kalau user sempat pindah ke mode manual lalu berubah pikiran). */
    public ?int $bagTerpilihIdOtomatis = null;

    public ?int $kaurMemberIdOtomatis = null;

    public bool $jalurError = false;

    public bool $kaurError = false;

    /**
     * Opsi tambahan (di luar jalur Bag/Kaur baku): pengaju/pemilik tahap
     * bebas memilih sendiri siapa saja & urutannya untuk rantai approval
     * Surat Keluar -- tidak terikat struktur Bag sama sekali. Kalau aktif,
     * kabag_dituju dibiarkan null (surat manual tidak "dimiliki" Bag mana
     * pun untuk keperluan dashboard per-Bag -- lihat
     * SidebarMenuService::bagianEfektif()).
     */
    public bool $modeManual = false;

    /** Rantai hasil pilihan manual, urut sesuai dipilih -- tiap elemen ['user_id' => int, 'nama' => string]. */
    public array $rantaiManual = [];

    /** Terikat wire:model ke dropdown "tambah orang" mode manual -- dikosongkan lagi setelah ditambahkan. */
    public string $cariUserManual = '';

    protected function loadBagsKeluar(): void
    {
        $this->bagsKeluar = app(BagService::class)->semuaBagKeluar();

        // Deteksi otomatis (lihat BagService::deteksiJalurKeluarAkun()) -- kegagalan
        // SENGAJA tidak dianggap error (biarkan checklist/dropdown manual jadi cadangan).
        $deteksi = app(BagService::class)->deteksiJalurKeluarAkun((int) auth()->id());
        if ($deteksi) {
            $this->jalurOtomatis = true;
            $this->bagTerpilihId = $deteksi['bag_id'];
            $this->kaurMemberId = $deteksi['kaur_member_id'];
            $this->bagTerpilihIdOtomatis = $deteksi['bag_id'];
            $this->kaurMemberIdOtomatis = $deteksi['kaur_member_id'];
        }
    }

    public function getBagTerpilihProperty(): ?array
    {
        if (!$this->bagTerpilihId) {
            return null;
        }
        foreach ($this->bagsKeluar as $bag) {
            if ($bag['id'] === $this->bagTerpilihId) {
                return $bag;
            }
        }

        return null;
    }

    /** true kalau Bag terpilih punya grup Kasi ("grup dalam grup") -- dropdown Kaur WAJIB ditampilkan & diisi. */
    public function getPerluPilihKaurProperty(): bool
    {
        return !empty($this->bagTerpilih['kasi_grup'] ?? []);
    }

    /** Nama Bag hasil deteksi otomatis -- dipakai label kartu "Terdeteksi otomatis". */
    public function getBagOtomatisNamaProperty(): ?string
    {
        if (!$this->bagTerpilihIdOtomatis) {
            return null;
        }
        foreach ($this->bagsKeluar as $bag) {
            if ($bag['id'] === $this->bagTerpilihIdOtomatis) {
                return $bag['nama'];
            }
        }

        return null;
    }

    /** Nama Kaur hasil deteksi otomatis (kalau Bag-nya punya grup Kasi) -- dipakai kartu "Terdeteksi otomatis" di kartu info. */
    public function getKaurOtomatisNamaProperty(): ?string
    {
        if (!$this->bagTerpilihIdOtomatis || !$this->kaurMemberIdOtomatis) {
            return null;
        }
        foreach ($this->bagsKeluar as $bag) {
            if ($bag['id'] !== $this->bagTerpilihIdOtomatis) {
                continue;
            }
            foreach ($bag['kasi_grup'] as $grup) {
                foreach ($grup['kaur'] as $kaur) {
                    if ($kaur['id'] === $this->kaurMemberIdOtomatis) {
                        return $kaur['nama'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Daftar DATAR titik mulai yang boleh dipilih user yang SEDANG LOGIN --
     * DIBATASI sesuai keterlibatannya di tiap Bag (bukan lagi seluruh Bag/
     * Kaur di sistem): kalau dia sendiri seorang Kaur, cuma dirinya sendiri;
     * kalau dia seorang Kasi, semua Kaur di bawah grupnya; selain itu
     * (anggota flat Bag mis. Kabag/Kabagtu/Turmin), semua Kaur dalam 1 Bag
     * yang sama. Satu opsi per Kaur untuk Bag yang punya grup Kasi, atau
     * satu opsi tunggal (anggota pertama/urutan=1) untuk Bag yang jalurnya
     * tunggal tanpa grup Kasi. Menggantikan pemilihan dua tahap (checklist
     * Bag lalu dropdown Kaur terpisah) dengan SATU dropdown "Kaur Penyiap
     * Surat" yang langsung menentukan Bag + Kaur sekaligus -- lihat
     * pilihGabungan().
     */
    public function getOpsiKaurPenyiapProperty(): array
    {
        $userId = (int) auth()->id();
        $out = [];

        foreach ($this->bagsKeluar as $bag) {
            // Keterlibatan user di Bag ini: Kaur spesifik (nested di 1 grup Kasi),
            // Kasi (membawahi 1 grup), atau anggota flat (Kabag/Kabagtu/Turmin/dst).
            $kaurSayaId = null;
            $kasiSayaGrupId = null;
            $sayaAnggotaFlat = collect($bag['anggota'])->contains('user_id', $userId);
            foreach ($bag['kasi_grup'] as $grup) {
                if ($grup['kasi']['id'] === $userId) {
                    $kasiSayaGrupId = $grup['id'];
                }
                $kaurDiGrupIni = collect($grup['kaur'])->firstWhere('user_id', $userId);
                if ($kaurDiGrupIni) {
                    $kaurSayaId = $kaurDiGrupIni['id'];
                }
            }

            // Tidak terlibat sama sekali di Bag ini -- jangan tampilkan opsi apa pun dari sana.
            if ($kaurSayaId === null && $kasiSayaGrupId === null && !$sayaAnggotaFlat) {
                continue;
            }

            if (empty($bag['kasi_grup'])) {
                // Bag tanpa grup Kasi (jalur tunggal) -- tidak ada yang perlu dibatasi lagi, cuma 1 opsi.
                if ($bag['anggota']) {
                    $out[] = [
                        'bag_id' => $bag['id'],
                        'bag_nama' => $bag['nama'],
                        'kaur_id' => null,
                        'label' => $bag['anggota'][0]['nama'],
                    ];
                }

                continue;
            }

            foreach ($bag['kasi_grup'] as $grup) {
                // Tampilkan grup Kasi ini kalau: saya sendiri Kaur di sini, saya Kasi-nya,
                // atau saya anggota flat Bag ini (Kabag dkk boleh lihat semua grup di Bag-nya).
                $grupRelevan = $sayaAnggotaFlat || $kasiSayaGrupId === $grup['id']
                    || collect($grup['kaur'])->contains('id', $kaurSayaId);
                if (!$grupRelevan) {
                    continue;
                }

                foreach ($grup['kaur'] as $kaur) {
                    // Kalau saya sendiri seorang Kaur spesifik (bukan Kasi/anggota flat),
                    // cuma tampilkan diri sendiri -- bukan rekan Kaur lain di grup yang sama.
                    if ($kaurSayaId !== null && $kasiSayaGrupId === null && !$sayaAnggotaFlat && $kaur['id'] !== $kaurSayaId) {
                        continue;
                    }
                    $out[] = [
                        'bag_id' => $bag['id'],
                        'bag_nama' => $bag['nama'],
                        'kaur_id' => $kaur['id'],
                        'label' => $kaur['nama'].' -- Kasi '.$grup['kasi']['nama'],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Pilih Bag + Kaur sekaligus dari dropdown "Kaur Penyiap Surat" --
     * $value berformat "{bagId}|{kaurId}" (kaurId kosong kalau Bag itu
     * tidak punya grup Kasi). Dipanggil lewat wire:change (bukan
     * wire:model) karena satu dropdown ini mewakili DUA properti
     * ($bagTerpilihId & $kaurMemberId) sekaligus.
     */
    public function pilihGabungan(string $value): void
    {
        if ($value === '') {
            $this->bagTerpilihId = null;
            $this->kaurMemberId = null;
        } else {
            [$bagId, $kaurId] = array_pad(explode('|', $value, 2), 2, '');
            $this->bagTerpilihId = $bagId !== '' ? (int) $bagId : null;
            $this->kaurMemberId = $kaurId !== '' ? (int) $kaurId : null;
        }
        $this->jalurError = false;
        $this->kaurError = false;
    }

    /**
     * Urutan LENGKAP rantai approval Surat Keluar untuk Bag/Kaur yang
     * sedang terpilih (Kaur -> Kasi -> sisa anggota Bag berurutan) --
     * dipakai untuk pratinjau "Alur Rute" di Langkah 1 & Resume, memakai
     * logika yang sama persis dengan yang benar-benar dipakai submit()
     * (BagService::rantaiKeluarUntukBag()).
     */
    protected function hitungAlurRuteKeluar(): array
    {
        if ($this->modeManual) {
            return array_column($this->rantaiManual, 'nama');
        }

        if (!$this->bagTerpilih) {
            return [];
        }
        if ($this->perluPilihKaur && !$this->kaurMemberId) {
            return [];
        }

        try {
            return app(BagService::class)->rantaiKeluarUntukBag($this->bagTerpilih['nama'], $this->kaurMemberId);
        } catch (\RuntimeException) {
            return [];
        }
    }

    /**
     * true kalau akun yang sedang login sendiri persis orang urutan pertama
     * di rantai default (jalurOtomatis) -- dalam kondisi ini dropdown "Kaur
     * Penyiap Surat" tidak berguna (tidak ada apa pun yang perlu dipilih,
     * karena Bag & Kaur-nya sudah pasti dirinya sendiri), jadi disembunyikan.
     */
    public function getPenyiapSudahPastiProperty(): bool
    {
        if (!$this->jalurOtomatis || $this->modeManual) {
            return false;
        }

        return ($this->hitungAlurRuteKeluar()[0] ?? null) === auth()->user()->nama;
    }

    /**
     * Hasil pencarian nama untuk search bar "tambah orang" mode manual --
     * admin tidak boleh masuk rantai proses surat, dan yang SUDAH ADA di
     * rantai (lihat $rantaiManual) tidak ditampilkan lagi -- kalau orang itu
     * baru saja dikeluarkan dari rantai (hapusDariRantaiManual()), dia bebas
     * dicari & ditambahkan lagi, di posisi manapun; TIDAK ada nama yang
     * dikunci selamanya -- lihat App\Livewire\SuratReview::simpanEditRantai()
     * untuk bagaimana keputusan lama (disetujui/ditolak) tetap dipertahankan
     * SELAMA posisinya di rantai baru persis sama dengan rantai lama.
     * Dibatasi 8 hasil supaya ringkas.
     */
    public function getOpsiUserManualProperty(): array
    {
        if (trim($this->cariUserManual) === '') {
            return [];
        }

        // array_filter buang entri null -- App\Livewire\SuratReview::mulaiEditRantai()
        // pra-isi rantai manual dari histori surat, dan resolusi nama->user_id
        // bisa gagal (mis. nama pejabatnya berubah); satu null tunggal di
        // whereNotIn bikin SQL "NOT IN (..., NULL)" jadi UNKNOWN buat SEMUA baris
        // (hasil pencarian jadi kosong sama sekali), jadi WAJIB dibuang dulu.
        $sudahDipilih = array_filter(array_column($this->rantaiManual, 'user_id'));

        return User::query()
            ->where('role', '!=', 'admin')
            ->when($sudahDipilih, fn ($q) => $q->whereNotIn('id', $sudahDipilih))
            ->where('nama', 'like', '%'.$this->cariUserManual.'%')
            ->orderBy('nama')
            ->limit(8)
            ->get(['id', 'nama'])
            ->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->nama])
            ->all();
    }

    /** Ganti antara jalur Bag/Kaur baku dan rantai manual bebas -- rantaiManual TIDAK direset supaya bisa bolak-balik tanpa kehilangan pilihan. */
    public function toggleModeManual(): void
    {
        $this->modeManual = !$this->modeManual;
        $this->jalurError = false;
        $this->kaurError = false;
    }

    /** Tambahkan user hasil klik dari search bar ke ujung rantai manual -- admin ditolak (lihat getOpsiUserManualProperty()). */
    public function pilihUserManual(int $userId): void
    {
        if ($userId <= 0 || collect($this->rantaiManual)->contains('user_id', $userId)) {
            $this->cariUserManual = '';

            return;
        }

        $user = User::query()->where('role', '!=', 'admin')->find($userId, ['id', 'nama']);
        if (!$user) {
            return;
        }

        $this->rantaiManual[] = ['user_id' => $user->id, 'nama' => $user->nama];
        $this->cariUserManual = '';
        $this->jalurError = false;
    }

    /** Keluarkan satu orang dari rantai manual berdasarkan posisinya. */
    public function hapusDariRantaiManual(int $index): void
    {
        unset($this->rantaiManual[$index]);
        $this->rantaiManual = array_values($this->rantaiManual);
    }

    /** Geser satu orang naik ($arah=-1) atau turun ($arah=1) dalam urutan rantai manual. */
    public function pindahRantaiManual(int $index, int $arah): void
    {
        $target = $index + $arah;
        if ($target < 0 || $target >= count($this->rantaiManual)) {
            return;
        }
        [$this->rantaiManual[$index], $this->rantaiManual[$target]] = [$this->rantaiManual[$target], $this->rantaiManual[$index]];
    }
}
