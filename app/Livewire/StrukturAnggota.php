<?php

namespace App\Livewire;

use App\Models\PimpinanAnggota;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Manajemen "Struktur Anggota" -- jajaran (bawahan) tiap akun role='pimpinan'
 * -- migrasi dari lib/screens/struktur_anggota_screen.dart. PURELY untuk
 * menyaring tampilan menu sidebar "Surat Masuk Jajaran" seorang Pimpinan,
 * BUKAN batas otorisasi (lihat komentar App\Http\Controllers\Api\PimpinanController).
 * Aturan bisnis add()/remove() SENGAJA disalin baris-per-baris dari
 * controller itu -- BUKAN memanggil controller via HTTP.
 */
#[Layout('layouts.app', ['title' => 'Struktur Anggota'])]
class StrukturAnggota extends Component
{
    public ?string $error = null;

    // --- Dialog "Tambah Anggota" ---
    public bool $showPickerModal = false;

    public ?int $pickerPimpinanId = null;

    public string $pickerPimpinanNama = '';

    public string $pickerSearch = '';

    #[Computed]
    public function pimpinanList()
    {
        $pimpinanUsers = User::query()
            ->where('role', 'pimpinan')
            ->orderBy('nama')
            ->get(['id', 'username', 'nama']);

        if ($pimpinanUsers->isEmpty()) {
            return collect();
        }

        $anggotaRows = PimpinanAnggota::query()
            ->join('users', 'users.id', '=', 'pimpinan_anggota.anggota_id')
            ->orderBy('users.nama')
            ->get([
                'pimpinan_anggota.id as id',
                'pimpinan_anggota.pimpinan_id as pimpinan_id',
                'users.id as user_id',
                'users.username as username',
                'users.nama as nama',
            ]);

        return $pimpinanUsers->map(fn (User $p) => (object) [
            'id' => $p->id,
            'username' => $p->username,
            'nama' => $p->nama,
            'anggota' => $anggotaRows->where('pimpinan_id', $p->id)->values(),
        ]);
    }

    /**
     * User yang bisa ditambahkan ke jajaran Pimpinan yang sedang dipilih --
     * akun role admin selalu dikecualikan, begitu juga akun Pimpinan itu
     * sendiri. Satu user BOLEH jadi anggota jajaran lebih dari satu
     * Pimpinan sekaligus -- yang dikecualikan cuma yang SUDAH jadi anggota
     * jajaran Pimpinan INI SENDIRI.
     */
    #[Computed]
    public function kandidat()
    {
        if (!$this->pickerPimpinanId) {
            return collect();
        }

        $sudahTerdaftar = PimpinanAnggota::query()
            ->where('pimpinan_id', $this->pickerPimpinanId)
            ->pluck('anggota_id')
            ->all();

        $q = trim($this->pickerSearch);

        return User::query()
            ->where('role', '!=', 'admin')
            ->where('id', '!=', $this->pickerPimpinanId)
            ->whereNotIn('id', $sudahTerdaftar)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nama', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->get();
    }

    public function openPicker(int $pimpinanId, string $pimpinanNama): void
    {
        $this->pickerPimpinanId = $pimpinanId;
        $this->pickerPimpinanNama = $pimpinanNama;
        $this->pickerSearch = '';
        $this->error = null;
        $this->showPickerModal = true;
    }

    public function closePicker(): void
    {
        $this->showPickerModal = false;
        $this->pickerPimpinanId = null;
        $this->pickerSearch = '';
    }

    /** Cermin PimpinanController::add(). */
    public function tambahAnggota(int $anggotaId): void
    {
        $this->error = null;
        $authUser = Auth::user();
        $pimpinanId = $this->pickerPimpinanId;

        if (!$pimpinanId || !$anggotaId) {
            $this->error = 'Akun Pimpinan dan anggota wajib dipilih';

            return;
        }

        $pimpinan = User::query()->find($pimpinanId, ['username', 'nama', 'role']);
        if (!$pimpinan) {
            $this->error = 'Akun Pimpinan tidak ditemukan';

            return;
        }
        if ($pimpinan->role !== 'pimpinan') {
            $this->error = 'Akun yang dipilih bukan akun role Pimpinan';

            return;
        }

        if ($anggotaId === $pimpinanId) {
            $this->error = 'Akun Pimpinan tidak bisa jadi anggota jajarannya sendiri';

            return;
        }

        $user = User::query()->find($anggotaId, ['username', 'nama', 'role']);
        if (!$user) {
            $this->error = 'User tidak ditemukan';

            return;
        }
        if ($user->role === 'admin') {
            $this->error = 'Akun role admin tidak bisa jadi anggota jajaran';

            return;
        }

        $existing = PimpinanAnggota::query()
            ->where('pimpinan_id', $pimpinanId)
            ->where('anggota_id', $anggotaId)
            ->exists();
        if ($existing) {
            $this->error = "{$user->nama} sudah jadi anggota jajaran {$pimpinan->nama}";

            return;
        }

        PimpinanAnggota::query()->create([
            'pimpinan_id' => $pimpinanId,
            'anggota_id' => $anggotaId,
        ]);

        ActivityLogger::log(
            request(),
            $authUser->username,
            $authUser->nama,
            'update',
            "Tambah {$user->nama} ({$user->username}) ke jajaran {$pimpinan->nama}",
        );

        unset($this->pimpinanList);
        $this->closePicker();
    }

    /** Cermin PimpinanController::remove(). */
    public function hapusAnggota(int $id): void
    {
        $this->error = null;
        $authUser = Auth::user();

        $old = PimpinanAnggota::query()
            ->join('users as u', 'u.id', '=', 'pimpinan_anggota.anggota_id')
            ->join('users as p', 'p.id', '=', 'pimpinan_anggota.pimpinan_id')
            ->where('pimpinan_anggota.id', $id)
            ->first(['u.username as username', 'u.nama as nama', 'p.nama as nama_pimpinan']);

        if (!$old) {
            $this->error = 'Anggota tidak ditemukan';

            return;
        }

        PimpinanAnggota::query()->where('id', $id)->delete();

        ActivityLogger::log(
            request(),
            $authUser->username,
            $authUser->nama,
            'update',
            "Keluarkan {$old->nama} ({$old->username}) dari jajaran {$old->nama_pimpinan}",
        );

        unset($this->pimpinanList);
    }

    public function render()
    {
        return view('livewire.struktur-anggota');
    }
}
