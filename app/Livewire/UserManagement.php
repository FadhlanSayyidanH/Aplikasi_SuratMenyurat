<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manajemen akun user (admin-only) -- migrasi dari
 * lib/screens/user_management_screen.dart. SELURUH aturan bisnis (urutan
 * validasi, uniqueness username/nama, KAPAN token/token_expires_at
 * dikosongkan) SENGAJA disalin baris-per-baris dari
 * App\Http\Controllers\Api\UserController (create/update/delete) -- BUKAN
 * memanggil controller itu via HTTP, karena komponen ini jalan di sesi web
 * (Livewire), bukan lewat token bearer API. Mengosongkan token/token_expires_at
 * memaksa logout sesi *API/Flutter* akun terkait -- TIDAK berpengaruh ke sesi
 * *web* manapun (sesi web disimpan terpisah di server, dikunci per session
 * ID, bukan lewat kolom users.token).
 */
#[Layout('layouts.app', ['title' => 'Manajemen User'])]
class UserManagement extends Component
{
    private const ROLES = ['admin', 'user', 'pimpinan', 'turmin'];

    /** Nilai dropdown khusus "Lainnya (isi manual)" -- lihat _namaLainnyaSentinel di Flutter. */
    private const NAMA_LAINNYA = '__lainnya__';

    /**
     * 10 nama pejabat TETAP-KRITIS yang masih dirujuk exact-match di
     * tempat lain di frontend -- lihat _namaTetapValid di
     * user_management_screen.dart. Dropdown ini cuma proteksi typo (UI,
     * BUKAN validasi keras); "Lainnya (isi manual)" tetap tersedia untuk
     * nama bebas apa pun (Kaur/Kasi/posisi jalur baru).
     */
    private const NAMA_TETAP = [
        'Kabagpam Subditbinum',
        'Kabagpers Subditbinum',
        'Kabaglog Subditbinum',
        'Kabagter Subditbinum',
        'Kabagrenproggar Subditbinum',
        'Kabagtu Subditbinum',
        'Kabagurdal',
        'Kainfolahta',
        'Kasubditbinum',
        'Turmin Subditbinum',
    ];

    /** Karakter aman buat password acak -- identik dengan _randomPassword() Flutter (menghindari huruf/angka yang gampang tertukar). */
    private const RANDOM_PASSWORD_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public string $search = '';

    public ?string $error = null;

    // --- Dialog Tambah/Ubah User ---
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $formTitle = '';

    public string $namaPilihan = self::NAMA_LAINNYA;

    public string $namaLainnya = '';

    public string $username = '';

    public string $role = 'user';

    public string $password = '';

    public bool $passwordRequired = true;

    public bool $kunciRoleAdmin = false;

    public ?string $formError = null;

    // --- Dialog Kredensial (ditampilkan sekali setelah create/reset password) ---
    public bool $showCredentialsModal = false;

    public string $credTitle = '';

    public string $credNama = '';

    public string $credUsername = '';

    public string $credPassword = '';

    #[Computed]
    public function users()
    {
        $q = trim($this->search);

        return User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nama', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->orderBy('id')
            ->get();
    }

    public function getNamaTetapProperty(): array
    {
        return self::NAMA_TETAP;
    }

    public function getNamaLainnyaSentinelProperty(): string
    {
        return self::NAMA_LAINNYA;
    }

    public function getNamaLainnyaAktifProperty(): bool
    {
        return $this->namaPilihan === self::NAMA_LAINNYA;
    }

    public function getNamaFinalProperty(): string
    {
        return $this->namaLainnyaAktif ? trim($this->namaLainnya) : $this->namaPilihan;
    }

    private function randomPassword(): string
    {
        $chars = self::RANDOM_PASSWORD_CHARS;
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }

    public function openAddForm(): void
    {
        $this->reset(['namaPilihan', 'namaLainnya', 'username', 'role', 'formError']);
        $this->namaPilihan = self::NAMA_LAINNYA;
        $this->editingId = null;
        $this->formTitle = 'Tambah User';
        $this->passwordRequired = true;
        $this->kunciRoleAdmin = false;
        $this->password = $this->randomPassword();
        $this->showFormModal = true;
    }

    public function openEditForm(int $id): void
    {
        $user = User::query()->find($id);
        if (!$user) {
            $this->error = 'User tidak ditemukan';

            return;
        }

        $this->editingId = $id;
        $this->formTitle = 'Ubah User';
        $this->username = $user->username;
        if (in_array($user->nama, self::NAMA_TETAP, true)) {
            $this->namaPilihan = $user->nama;
            $this->namaLainnya = '';
        } else {
            $this->namaPilihan = self::NAMA_LAINNYA;
            $this->namaLainnya = $user->nama;
        }
        $this->role = $user->role;
        $this->password = '';
        $this->passwordRequired = false;
        $this->kunciRoleAdmin = $user->username === 'admin';
        $this->formError = null;
        $this->showFormModal = true;
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
    }

    /** Cermin UserController::create() / update(). */
    public function save(): void
    {
        $this->formError = null;

        $username = trim($this->username);
        $nama = $this->namaFinal;
        $role = $this->role;

        if ($username === '' || $nama === '') {
            $this->formError = 'Username dan nama wajib diisi';

            return;
        }
        if (!in_array($role, self::ROLES, true)) {
            $this->formError = 'Role harus "admin", "user", "pimpinan", atau "turmin"';

            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            $this->formError = 'Username hanya boleh huruf, angka, titik, dan underscore';

            return;
        }

        $authUser = Auth::user();

        if ($this->editingId === null) {
            $this->createUser($username, $nama, $role, $authUser);
        } else {
            $this->updateUser($this->editingId, $username, $nama, $role, $authUser);
        }
    }

    private function createUser(string $username, string $nama, string $role, User $authUser): void
    {
        if (strlen($this->password) < 10) {
            $this->formError = 'Password minimal 10 karakter';

            return;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->formError = 'Username sudah dipakai';

            return;
        }

        if ($role !== 'admin') {
            $clash = User::query()->where('nama', $nama)->where('role', '!=', 'admin')->exists();
            if ($clash) {
                $this->formError = "Sudah ada akun pejabat lain dengan nama \"$nama\"";

                return;
            }
        }

        $user = new User();
        $user->username = $username;
        $user->password = Hash::make($this->password);
        $user->nama = $nama;
        $user->role = $role;
        $user->save();

        ActivityLogger::log(request(), $authUser->username, $authUser->nama, 'create', "Tambah akun user $nama ($username), role $role");

        unset($this->users);
        $this->showFormModal = false;
        $this->openCredentials('User Ditambahkan', $nama, $username, $this->password);
    }

    private function updateUser(int $id, string $username, string $nama, string $role, User $authUser): void
    {
        if ($this->password !== '' && strlen($this->password) < 10) {
            $this->formError = 'Password baru minimal 10 karakter';

            return;
        }

        $old = User::query()->find($id);
        if (!$old) {
            $this->formError = 'User tidak ditemukan';

            return;
        }
        if ($old->username === 'admin' && $role !== 'admin') {
            $this->formError = 'Role akun Administrator tidak bisa diturunkan';

            return;
        }

        $usernameClash = User::query()->where('username', $username)->where('id', '!=', $id)->exists();
        if ($usernameClash) {
            $this->formError = 'Username sudah dipakai';

            return;
        }

        if ($role !== 'admin') {
            $namaClash = User::query()
                ->where('nama', $nama)
                ->where('role', '!=', 'admin')
                ->where('id', '!=', $id)
                ->exists();
            if ($namaClash) {
                $this->formError = "Sudah ada akun pejabat lain dengan nama \"$nama\"";

                return;
            }
        }

        $oldUsername = $old->username;
        $oldNama = $old->nama;
        $oldRole = $old->role;

        $old->username = $username;
        $old->nama = $nama;

        // Ganti password ATAU role (kalau berubah) memaksa logout sesi
        // API/Flutter lama, jadi token dihapus sekalian -- SENGAJA hanya dua
        // kondisi ini (bukan termasuk perubahan username saja), persis
        // UserController::update().
        if ($this->password !== '' || $role !== $oldRole) {
            $old->role = $role;
            if ($this->password !== '') {
                $old->password = Hash::make($this->password);
            }
            $old->token = null;
            $old->token_expires_at = null;
        }

        $old->save();

        $keterangan = "Ubah akun user {$oldNama} ({$oldUsername})";
        if ($username !== $oldUsername || $nama !== $oldNama) {
            $keterangan .= " menjadi $nama ($username)";
        }
        if ($role !== $oldRole) {
            $keterangan .= ", role diubah dari {$oldRole} menjadi $role";
        }
        if ($this->password !== '') {
            $keterangan .= ', password direset';
        }

        ActivityLogger::log(request(), $authUser->username, $authUser->nama, 'update', $keterangan);

        unset($this->users);
        $this->showFormModal = false;
        if ($this->password !== '') {
            $this->openCredentials('Password Direset', $nama, $username, $this->password);
        } else {
            $this->dispatch('notify', message: 'Data user berhasil diperbarui.');
        }
    }

    /** Tombol "Reset Pass." di daftar -- cermin alur _resetPassword() Flutter (password acak baru, lewat jalur update() yang sama). */
    public function resetPassword(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();

        $old = User::query()->find($id);
        if (!$old) {
            $this->error = 'User tidak ditemukan';

            return;
        }

        $newPassword = $this->randomPassword();
        $oldUsername = $old->username;
        $oldNama = $old->nama;

        $old->password = Hash::make($newPassword);
        $old->token = null;
        $old->token_expires_at = null;
        $old->save();

        ActivityLogger::log(request(), $authUser->username, $authUser->nama, 'update', "Ubah akun user {$oldNama} ({$oldUsername}), password direset");

        unset($this->users);
        $this->openCredentials('Password Direset', $oldNama, $oldUsername, $newPassword);
    }

    /** Cermin UserController::delete(). */
    public function deleteUser(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();

        $target = User::query()->find($id);
        if (!$target) {
            $this->error = 'User tidak ditemukan';

            return;
        }
        if ($target->username === 'admin') {
            $this->error = 'Akun Administrator tidak bisa dihapus';

            return;
        }
        if ((int) $id === (int) $authUser->id) {
            $this->error = 'Tidak bisa menghapus akun sendiri';

            return;
        }

        $targetUsername = $target->username;
        $targetNama = $target->nama;

        $target->delete();

        ActivityLogger::log(request(), $authUser->username, $authUser->nama, 'delete', "Hapus akun user {$targetNama} ({$targetUsername})");

        unset($this->users);
        $this->dispatch('notify', message: "Akun \"$targetNama\" berhasil dihapus.");
    }

    private function openCredentials(string $title, string $nama, string $username, string $password): void
    {
        $this->credTitle = $title;
        $this->credNama = $nama;
        $this->credUsername = $username;
        $this->credPassword = $password;
        $this->showCredentialsModal = true;
    }

    public function closeCredentials(): void
    {
        $this->showCredentialsModal = false;
        $this->credPassword = '';
    }

    public function render()
    {
        return view('livewire.user-management');
    }
}
