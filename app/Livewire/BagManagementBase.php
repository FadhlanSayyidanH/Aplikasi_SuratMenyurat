<?php

namespace App\Livewire;

use App\Models\BagDisposisiAnggota;
use App\Models\BagKasiGrup;
use App\Models\BagKeluar;
use App\Models\BagMasuk;
use App\Models\BagMember;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BagService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Manajemen Bag (Surat Masuk & Surat Keluar) -- migrasi dari
 * lib/screens/bag_management_screen.dart (proyek Flutter lama). Di sana ini
 * SATU screen dengan parameter `BagMode` (suratMasuk/suratKeluar) yang
 * menentukan SELURUH state (endpoint yang dipanggil, field yang
 * ditampilkan/diedit) -- lihat dokumentasi [BagMode] di file itu. Di sini
 * dipecah jadi kelas dasar abstrak (logika + view sepenuhnya sama) dengan
 * dua subclass tipis (BagManagementMasuk/BagManagementKeluar) yang cuma
 * menentukan `$mode` lewat modeValue() -- supaya masing-masing bisa
 * dipasang sebagai komponen Livewire full-page TERPISAH di
 * routes/web_bag.php (rute 'bag.masuk'/'bag.keluar', masing-masing perlu
 * class Livewire sendiri untuk didaftarkan sebagai route action), sambil
 * tetap 100% satu sumber logika -- persis semangat satu screen Flutter
 * dengan parameter mode, bukan dua implementasi independen yang bisa
 * divergen.
 *
 * SELURUH operasi database di sini SENGAJA disalin baris-per-baris dari
 * App\Http\Controllers\Api\BagController / BagMemberController /
 * BagMasukController (dan App\Services\BagService untuk sisi baca) --
 * BUKAN memanggil controller itu / endpoint API lewat HTTP, karena
 * komponen ini jalan di sesi web (Livewire, cookie session), bukan lewat
 * token bearer API seperti client Flutter.
 *
 * Invalidasi token (paksa re-login sesi Flutter/API akun yang
 * terpengaruh) HANYA dilakukan pada mutasi yang di proyek API juga
 * melakukannya (lihat komentar per-method di bawah) -- bag_member_update
 * (reorder) dan seluruh bag_disposisi_anggota_* SENGAJA TIDAK, sesuai
 * temuan agent yang membangun sisi API (session web Laravel admin yang
 * sedang mengedit TIDAK pernah ikut ter-invalidate oleh ini, karena token
 * kolom itu murni punya jalur API/Flutter -- terpisah dari sesi web).
 */
abstract class BagManagementBase extends Component
{
    public string $mode;

    /** @var array<int, array> hasil BagService::semuaBagKeluar()/semuaBagMasuk(), terurut nama. */
    public array $bags = [];

    /** @var array<int, array{id:int, username:string, nama:string, role:string}> seluruh user. */
    public array $users = [];

    public ?string $error = null;

    // --- Modal: Tambah/Ubah Bag ---
    public bool $showBagForm = false;

    public ?int $editingBagId = null;

    public string $bagFormNama = '';

    public ?string $bagFormError = null;

    // --- Modal: Pilih User (Tambah Anggota / Tambah Penerima Disposisi / Tambah Grup Kasi / Tambah Kaur) ---
    public bool $showPilihUser = false;

    /** anggota|anggota_masuk|kasi_grup|kaur */
    public string $pilihUserAction = '';

    public ?int $pilihUserBagId = null;

    /** hanya diisi untuk aksi 'kaur' -- id BagKasiGrup tujuan. */
    public ?int $pilihUserGrupId = null;

    public string $pilihUserTitle = '';

    public string $pilihUserQuery = '';

    public ?string $pilihUserError = null;

    // --- Modal: Pilih Akun Posisi (Turmin/Kasubdit, khusus bag_masuk) ---
    public bool $showPilihPosisi = false;

    public ?int $posisiBagId = null;

    /** turmin_user_id|kasubdit_user_id */
    public string $posisiKolom = '';

    /** turmin|pimpinan */
    public string $posisiRole = '';

    /** Turmin|Kasubdit */
    public string $posisiLabel = '';

    public string $posisiQuery = '';

    public ?string $posisiError = null;

    abstract protected function modeValue(): string;

    public function mount(): void
    {
        $this->mode = $this->modeValue();
        $this->loadData();
    }

    protected function khususKeluar(): bool
    {
        return $this->mode === 'keluar';
    }

    // =========================================================================
    // Muat data -- cermin _load() Flutter (bag list + user list sekaligus).
    // =========================================================================

    public function loadData(): void
    {
        $this->error = null;
        try {
            $bagService = app(BagService::class);
            $this->bags = $this->khususKeluar() ? $bagService->semuaBagKeluar() : $bagService->semuaBagMasuk();
            $this->users = User::query()->orderBy('nama')
                ->get(['id', 'username', 'nama', 'role'])
                ->map(fn (User $u) => ['id' => $u->id, 'username' => $u->username, 'nama' => $u->nama, 'role' => $u->role])
                ->all();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reload(): void
    {
        $this->loadData();
    }

    private function findBag(int $id): ?array
    {
        foreach ($this->bags as $b) {
            if ($b['id'] === $id) {
                return $b;
            }
        }

        return null;
    }

    // =========================================================================
    // Helper kandidat user -- cermin _userIdSudahDiBagIni/_userBelumBerbag/
    // _userBelumDisposisi Flutter.
    // =========================================================================

    /** SELURUH user id yang sudah terpakai di $bag -- anggota datar, Kasi di grup manapun, MAUPUN Kaur di dalam grup manapun. */
    private function userIdsSudahDiBagIni(array $bag): array
    {
        $ids = [];
        foreach ($bag['anggota'] ?? [] as $a) {
            $ids[] = $a['user_id'];
        }
        foreach ($bag['kasi_grup'] ?? [] as $g) {
            $ids[] = $g['kasi']['id'];
            foreach ($g['kaur'] as $k) {
                $ids[] = $k['user_id'];
            }
        }

        return $ids;
    }

    /** User yang bisa ditambahkan sebagai anggota datar/Kasi/Kaur di $bagId (rantai approval Surat Keluar). */
    private function userBelumBerbag(int $bagId): array
    {
        $bag = $this->findBag($bagId);
        $sudah = $bag ? $this->userIdsSudahDiBagIni($bag) : [];

        return array_values(array_filter($this->users, fn ($u) => $u['role'] !== 'admin' && !in_array($u['id'], $sudah, true)));
    }

    /** User yang bisa ditambahkan sebagai penerima disposisi Surat Masuk $bagId -- SEPENUHNYA independen dari userBelumBerbag(). */
    private function userBelumDisposisi(int $bagId): array
    {
        $bag = $this->findBag($bagId);
        $sudah = $bag ? array_map(fn ($a) => $a['user_id'], $bag['anggota_masuk'] ?? []) : [];

        return array_values(array_filter($this->users, fn ($u) => $u['role'] !== 'admin' && !in_array($u['id'], $sudah, true)));
    }

    /** Kandidat yang sedang ditampilkan di modal Pilih User, terfilter query pencarian nama/username. */
    public function pilihUserKandidat(): array
    {
        if (!$this->pilihUserBagId) {
            return [];
        }
        $kandidat = match ($this->pilihUserAction) {
            'anggota', 'kasi_grup', 'kaur' => $this->userBelumBerbag($this->pilihUserBagId),
            'anggota_masuk' => $this->userBelumDisposisi($this->pilihUserBagId),
            default => [],
        };

        return $this->filterKandidat($kandidat, $this->pilihUserQuery);
    }

    /** Kandidat modal Pilih Akun Posisi (Turmin/Kasubdit) -- dibatasi role, terfilter query pencarian. */
    public function posisiKandidat(): array
    {
        $kandidat = array_values(array_filter($this->users, fn ($u) => $u['role'] === $this->posisiRole));

        return $this->filterKandidat($kandidat, $this->posisiQuery);
    }

    private function filterKandidat(array $kandidat, string $query): array
    {
        $q = trim(mb_strtolower($query));
        if ($q === '') {
            return $kandidat;
        }

        return array_values(array_filter(
            $kandidat,
            fn ($u) => str_contains(mb_strtolower($u['nama']), $q) || str_contains(mb_strtolower($u['username']), $q)
        ));
    }

    // =========================================================================
    // CRUD Bag -- cermin App\Http\Controllers\Api\BagController.
    // =========================================================================

    public function openTambahBag(): void
    {
        $this->showBagForm = true;
        $this->editingBagId = null;
        $this->bagFormNama = '';
        $this->bagFormError = null;
    }

    public function openEditBag(int $bagId): void
    {
        $bag = $this->findBag($bagId);
        if (!$bag) {
            return;
        }
        $this->showBagForm = true;
        $this->editingBagId = $bagId;
        $this->bagFormNama = $bag['nama'];
        $this->bagFormError = null;
    }

    public function closeBagForm(): void
    {
        $this->showBagForm = false;
    }

    /** Cermin BagController::create()/update() -- tabel target (bag_keluar/bag_masuk) ditentukan $this->mode, tidak bisa diubah lewat form ini. */
    public function saveBag(): void
    {
        $this->bagFormError = null;
        $authUser = Auth::user();
        $nama = trim($this->bagFormNama);
        if ($nama === '') {
            $this->bagFormError = 'Nama Bag wajib diisi';

            return;
        }

        $model = $this->khususKeluar() ? BagKeluar::class : BagMasuk::class;

        if ($this->editingBagId === null) {
            if ($model::query()->where('nama', $nama)->exists()) {
                $this->bagFormError = 'Sudah ada Bag dengan nama tersebut di konteks ini';

                return;
            }

            $model::query()->create(['nama' => $nama]);

            ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'create', "Tambah Bag \"$nama\"");
        } else {
            $id = $this->editingBagId;
            $bag = $model::query()->find($id);
            if (!$bag) {
                $this->bagFormError = 'Bag tidak ditemukan';

                return;
            }
            if ($model::query()->where('nama', $nama)->where('id', '!=', $id)->exists()) {
                $this->bagFormError = 'Sudah ada Bag dengan nama tersebut di konteks ini';

                return;
            }

            $namaLama = $bag->nama;
            $bag->nama = $nama;
            $bag->save();

            ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Ubah Bag \"$namaLama\" menjadi \"$nama\"");
        }

        $this->showBagForm = false;
        $this->loadData();
    }

    /** Cermin BagController::delete() -- bag_member/bag_kasi_grup (keluar) atau bag_disposisi_anggota (masuk) ikut terhapus lewat FK cascade. */
    public function deleteBag(int $bagId): void
    {
        $this->error = null;
        $authUser = Auth::user();
        $model = $this->khususKeluar() ? BagKeluar::class : BagMasuk::class;
        $bag = $model::query()->find($bagId);
        if (!$bag) {
            $this->error = 'Bag tidak ditemukan';

            return;
        }

        $nama = $bag->nama;
        $bag->delete();

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'delete', "Hapus Bag \"$nama\"");

        $this->loadData();
    }

    // =========================================================================
    // Turmin/Kasubdit slot (bag_masuk) -- cermin BagMasukController::setGateSlot()
    // (dipakai setTurmin() & setKasubdit() di sana).
    // =========================================================================

    public function openPosisi(int $bagId, string $which): void
    {
        $bag = $this->findBag($bagId);
        if (!$bag) {
            return;
        }
        $this->showPilihPosisi = true;
        $this->posisiBagId = $bagId;
        $this->posisiQuery = '';
        $this->posisiError = null;
        if ($which === 'turmin') {
            $this->posisiKolom = 'turmin_user_id';
            $this->posisiRole = 'turmin';
            $this->posisiLabel = 'Turmin';
        } elseif ($which === 'kabag') {
            $this->posisiKolom = 'kabag_user_id';
            $this->posisiRole = 'user';
            $this->posisiLabel = 'Kabag';
        } else {
            $this->posisiKolom = 'kasubdit_user_id';
            $this->posisiRole = 'pimpinan';
            $this->posisiLabel = 'Kasubdit';
        }
    }

    public function closePosisi(): void
    {
        $this->showPilihPosisi = false;
    }

    /** $userId null = "Kosongkan" slot ini. Ganti slot gerbang berdampak ke hak akses -- paksa re-login akun LAMA & BARU. */
    public function pilihPosisi(?int $userId): void
    {
        $this->posisiError = null;
        $authUser = Auth::user();
        $bagId = $this->posisiBagId;
        $column = $this->posisiKolom;
        $requiredRole = $this->posisiRole;
        $label = $this->posisiLabel;

        if (!$bagId) {
            return;
        }

        $bag = BagMasuk::query()->find($bagId);
        if (!$bag) {
            $this->posisiError = 'Bag tidak ditemukan';

            return;
        }

        $user = null;
        if ($userId) {
            $user = User::query()->find($userId);
            if (!$user) {
                $this->posisiError = 'User tidak ditemukan';

                return;
            }
            if ($user->role !== $requiredRole) {
                $this->posisiError = "Akun $label harus akun dengan role \"$requiredRole\"";

                return;
            }
        }

        $oldUserId = $bag->{$column};
        $oldUsername = $oldUserId ? User::query()->find($oldUserId)?->username : null;

        $bag->{$column} = $userId;
        $bag->save();

        if ($oldUsername !== null) {
            User::query()->where('username', $oldUsername)->update(['token' => null, 'token_expires_at' => null]);
        }
        if ($user !== null) {
            User::query()->where('username', $user->username)->update(['token' => null, 'token_expires_at' => null]);
        }

        $keterangan = $user !== null
            ? "Atur Akun $label Bag \"{$bag->nama}\" jadi {$user->nama} ({$user->username})"
            : "Kosongkan Akun $label Bag \"{$bag->nama}\"";
        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', $keterangan);

        $this->showPilihPosisi = false;
        $this->loadData();
    }

    // =========================================================================
    // Modal Pilih User -- dipakai bareng Tambah Anggota / Tambah Penerima
    // Disposisi / Tambah Grup Kasi / Tambah Kaur, cermin _PilihUserDialog
    // Flutter (satu dialog generik, aksi ditentukan pemanggilnya).
    // =========================================================================

    public function openPilihUser(string $action, int $bagId, ?int $grupId = null): void
    {
        $this->showPilihUser = true;
        $this->pilihUserAction = $action;
        $this->pilihUserBagId = $bagId;
        $this->pilihUserGrupId = $grupId;
        $this->pilihUserQuery = '';
        $this->pilihUserError = null;
        $this->pilihUserTitle = match ($action) {
            'anggota' => 'Tambah Anggota',
            'anggota_masuk' => 'Tambah Penerima Disposisi',
            'kasi_grup' => 'Tambah Grup Kasi',
            'kaur' => 'Tambah Kaur',
            default => 'Tambah',
        };

        if (empty($this->pilihUserKandidat())) {
            $this->showPilihUser = false;
            $this->error = match ($action) {
                'kasi_grup' => 'Tidak ada user yang bisa jadi Kasi di Bag ini.',
                'kaur' => 'Tidak ada user yang bisa ditambahkan ke grup ini.',
                default => 'Tidak ada user yang bisa ditambahkan ke Bag ini.',
            };
        }
    }

    public function closePilihUser(): void
    {
        $this->showPilihUser = false;
    }

    public function pilihUser(int $userId): void
    {
        match ($this->pilihUserAction) {
            'anggota' => $this->addAnggota($this->pilihUserBagId, $userId, null),
            'kaur' => $this->addAnggota($this->pilihUserBagId, $userId, $this->pilihUserGrupId),
            'anggota_masuk' => $this->addAnggotaMasuk($this->pilihUserBagId, $userId),
            'kasi_grup' => $this->addKasiGrup($this->pilihUserBagId, $userId),
            default => null,
        };
    }

    // =========================================================================
    // Anggota bag_member (datar & Kaur bersarang) -- cermin BagMemberController.
    // =========================================================================

    /** Cermin BagMemberController::add() -- $kasiGrupId null = anggota datar, diisi = Kaur di dalam grup itu. */
    private function addAnggota(?int $bagId, int $userId, ?int $kasiGrupId): void
    {
        $this->pilihUserError = null;
        $authUser = Auth::user();
        if (!$bagId) {
            return;
        }

        $bag = BagKeluar::query()->find($bagId);
        if (!$bag) {
            $this->pilihUserError = 'Bag tidak ditemukan';

            return;
        }
        if ($kasiGrupId !== null) {
            $grup = BagKasiGrup::query()->find($kasiGrupId);
            if (!$grup || (int) $grup->bag_id !== $bagId) {
                $this->pilihUserError = 'Grup Kasi tidak valid untuk Bag ini';

                return;
            }
        }
        $user = User::query()->find($userId);
        if (!$user) {
            $this->pilihUserError = 'User tidak ditemukan';

            return;
        }
        if ($user->role === 'admin') {
            $this->pilihUserError = 'Akun role admin tidak bisa jadi anggota Bag';

            return;
        }
        if (BagMember::query()->where('bag_id', $bagId)->where('user_id', $userId)->exists()) {
            $this->pilihUserError = "{$user->nama} sudah jadi anggota Bag \"{$bag->nama}\"";

            return;
        }

        $urutan = $kasiGrupId !== null
            ? (int) (BagMember::query()->where('kasi_grup_id', $kasiGrupId)->max('urutan')) + 1
            : (int) (BagMember::query()->where('bag_id', $bagId)->whereNull('kasi_grup_id')->max('urutan')) + 1;

        BagMember::query()->create([
            'bag_id' => $bagId,
            'user_id' => $userId,
            'urutan' => $urutan,
            'kasi_grup_id' => $kasiGrupId,
        ]);

        // Paksa re-login supaya sesi yang sedang aktif langsung mendapat hak akses terbaru.
        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Tambah {$user->nama} ({$user->username}) ke Bag \"{$bag->nama}\"");

        $this->showPilihUser = false;
        $this->loadData();
    }

    /** Cermin BagMemberController::remove() -- baris yang sama dipakai anggota datar MAUPUN Kaur di dalam grup, tombol "Keluarkan" generik untuk keduanya. */
    public function hapusAnggota(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();
        $old = BagMember::query()->with(['user:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            $this->error = 'Anggota tidak ditemukan';

            return;
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

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Keluarkan {$namaUser} ({$usernameUser}) dari Bag \"{$namaBag}\"");

        $this->loadData();
    }

    /**
     * Drag-and-drop anggota datar ke posisi baru -- cermin _geser() Flutter:
     * SELALU menomori ulang SELURUH anggota datar Bag ini jadi 1..N sesuai
     * urutan hasil drag (bukan cuma tukar 2 nilai), $ids adalah id
     * bag_member terurut SETELAH drag (dibaca dari DOM oleh Alpine, lihat
     * resources/views/livewire/bag-management.blade.php). Cermin
     * BagMemberController::update() -- reorder murni, SENGAJA TIDAK
     * invalidasi token (tidak mengubah hak akses).
     */
    public function reorderAnggota(int $bagId, array $ids): void
    {
        $authUser = Auth::user();
        foreach ($ids as $i => $rawId) {
            $id = (int) $rawId;
            $urutanBaru = $i + 1;
            $old = BagMember::query()->where('bag_id', $bagId)->whereNull('kasi_grup_id')
                ->with(['user:id,nama', 'bag:id,nama'])->find($id);
            if (!$old || !$old->user || !$old->bag) {
                continue;
            }
            if ((int) $old->urutan === $urutanBaru) {
                continue;
            }

            $namaBag = $old->bag->nama;
            $namaUser = $old->user->nama;
            $old->urutan = $urutanBaru;
            $old->save();

            ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Ubah urutan {$namaUser} di Bag \"{$namaBag}\" jadi $urutanBaru");
        }
        $this->loadData();
    }

    /** Cermin BagMemberController::kasiGrupCreate(). */
    private function addKasiGrup(?int $bagId, int $kasiUserId): void
    {
        $this->pilihUserError = null;
        $authUser = Auth::user();
        if (!$bagId) {
            return;
        }

        $bag = BagKeluar::query()->find($bagId);
        if (!$bag) {
            $this->pilihUserError = 'Bag tidak ditemukan';

            return;
        }
        $user = User::query()->find($kasiUserId);
        if (!$user) {
            $this->pilihUserError = 'Akun tidak ditemukan';

            return;
        }
        if ($user->role === 'admin') {
            $this->pilihUserError = 'Akun role admin tidak bisa jadi Kasi';

            return;
        }
        if (BagKasiGrup::query()->where('bag_id', $bagId)->where('kasi_user_id', $kasiUserId)->exists()) {
            $this->pilihUserError = "{$user->nama} sudah jadi Kasi di Bag \"{$bag->nama}\"";

            return;
        }

        $urutan = (int) (BagKasiGrup::query()->where('bag_id', $bagId)->max('urutan')) + 1;

        BagKasiGrup::query()->create(['bag_id' => $bagId, 'kasi_user_id' => $kasiUserId, 'urutan' => $urutan]);

        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Tambah grup Kasi {$user->nama} ({$user->username}) ke Bag \"{$bag->nama}\"");

        $this->showPilihUser = false;
        $this->loadData();
    }

    /** Cermin BagMemberController::kasiGrupDelete() -- SELURUH Kaur di bawahnya ikut terhapus lewat FK cascade. */
    public function hapusKasiGrup(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();
        $old = BagKasiGrup::query()->with(['kasi:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->kasi || !$old->bag) {
            $this->error = 'Grup tidak ditemukan';

            return;
        }

        $kasiUserId = $old->kasi_user_id;
        $kasiUsername = $old->kasi->username;
        $kasiNama = $old->kasi->nama;
        $namaBag = $old->bag->nama;

        $kaurUserIds = BagMember::query()->where('kasi_grup_id', $id)->pluck('user_id')->all();

        $old->delete();

        $idsToInvalidate = [...$kaurUserIds, $kasiUserId];
        User::query()->whereIn('id', $idsToInvalidate)->update(['token' => null, 'token_expires_at' => null]);

        ActivityLogger::log(
            $this->request(), $authUser->username, $authUser->nama, 'update',
            'Hapus grup Kasi '.$kasiNama." ({$kasiUsername}) beserta ".count($kaurUserIds)." Kaur dari Bag \"{$namaBag}\""
        );

        $this->loadData();
    }

    // =========================================================================
    // Penerima disposisi bag_disposisi_anggota (bag_masuk) -- cermin
    // BagMasukController (disposisiAnggotaAdd/Remove/Update), SEPENUHNYA
    // LEPAS dari bag_member/bag_kasi_grup di atas.
    // =========================================================================

    /** Cermin BagMasukController::disposisiAnggotaAdd() -- SENGAJA TIDAK invalidasi token (bukan gerbang akses seperti turmin/kasubdit). */
    private function addAnggotaMasuk(?int $bagId, int $userId): void
    {
        $this->pilihUserError = null;
        $authUser = Auth::user();
        if (!$bagId) {
            return;
        }

        $bag = BagMasuk::query()->find($bagId);
        if (!$bag) {
            $this->pilihUserError = 'Bag tidak ditemukan';

            return;
        }
        $user = User::query()->find($userId);
        if (!$user) {
            $this->pilihUserError = 'User tidak ditemukan';

            return;
        }
        if ($user->role === 'admin') {
            $this->pilihUserError = 'Akun role admin tidak bisa jadi penerima disposisi';

            return;
        }
        if (BagDisposisiAnggota::query()->where('bag_id', $bagId)->where('user_id', $userId)->exists()) {
            $this->pilihUserError = "{$user->nama} sudah jadi penerima disposisi Bag \"{$bag->nama}\"";

            return;
        }

        $urutan = (int) (BagDisposisiAnggota::query()->where('bag_id', $bagId)->max('urutan')) + 1;

        BagDisposisiAnggota::query()->create(['bag_id' => $bagId, 'user_id' => $userId, 'urutan' => $urutan]);

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Tambah {$user->nama} ({$user->username}) ke penerima disposisi Bag \"{$bag->nama}\"");

        $this->showPilihUser = false;
        $this->loadData();
    }

    /** Cermin BagMasukController::disposisiAnggotaRemove() -- tidak menyentuh bag_member/bag_kasi_grup Bag yang sama. */
    public function hapusAnggotaMasuk(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();
        $old = BagDisposisiAnggota::query()->with(['user:id,username,nama', 'bag:id,nama'])->find($id);
        if (!$old || !$old->user || !$old->bag) {
            $this->error = 'Anggota tidak ditemukan';

            return;
        }

        $namaUser = $old->user->nama;
        $usernameUser = $old->user->username;
        $namaBag = $old->bag->nama;

        $old->delete();

        ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Keluarkan {$namaUser} ({$usernameUser}) dari penerima disposisi Bag \"{$namaBag}\"");

        $this->loadData();
    }

    /** Sama seperti reorderAnggota(), tapi untuk daftar penerima disposisi independen. Cermin BagMasukController::disposisiAnggotaUpdate() -- reorder murni, TIDAK invalidasi token. */
    public function reorderAnggotaMasuk(int $bagId, array $ids): void
    {
        $authUser = Auth::user();
        foreach ($ids as $i => $rawId) {
            $id = (int) $rawId;
            $urutanBaru = $i + 1;
            $old = BagDisposisiAnggota::query()->where('bag_id', $bagId)
                ->with(['user:id,nama', 'bag:id,nama'])->find($id);
            if (!$old || !$old->user || !$old->bag) {
                continue;
            }
            if ((int) $old->urutan === $urutanBaru) {
                continue;
            }

            $namaBag = $old->bag->nama;
            $namaUser = $old->user->nama;
            $old->urutan = $urutanBaru;
            $old->save();

            ActivityLogger::log($this->request(), $authUser->username, $authUser->nama, 'update', "Ubah urutan {$namaUser} di penerima disposisi Bag \"{$namaBag}\" jadi $urutanBaru");
        }
        $this->loadData();
    }

    private function request(): \Illuminate\Http\Request
    {
        return request();
    }

    public function render()
    {
        return view('livewire.bag-management');
    }
}
