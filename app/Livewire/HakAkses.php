<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Panel admin untuk mengatur hak input Surat Masuk/Surat Keluar PER AKUN
 * lewat 2 switch -- migrasi dari lib/screens/hak_akses_screen.dart. Aturan
 * bisnisnya (admin dikecualikan, token/token_expires_at dikosongkan setiap
 * kali hak input berubah) SENGAJA disalin baris-per-baris dari
 * App\Http\Controllers\Api\UserController::aksesUpdate() -- BUKAN memanggil
 * controller itu via HTTP. Mengosongkan token cuma memaksa logout sesi
 * *API/Flutter* akun terkait, tidak berpengaruh ke sesi web manapun.
 */
#[Layout('layouts.app', ['title' => 'Hak Akses'])]
class HakAkses extends Component
{
    public string $search = '';

    public ?string $error = null;

    #[Computed]
    public function users()
    {
        $q = trim($this->search);

        // Admin tidak pernah relevan di panel ini -- dia tidak pernah
        // menginput surat, cuma mengelola sistem.
        return User::query()
            ->where('role', '!=', 'admin')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nama', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->orderBy('nama')
            ->get();
    }

    public function toggleMasuk(int $id, bool $value): void
    {
        $this->ubah($id, bolehInputMasuk: $value);
    }

    public function toggleKeluar(int $id, bool $value): void
    {
        $this->ubah($id, bolehInputKeluar: $value);
    }

    /** Cermin UserController::aksesUpdate(). */
    private function ubah(int $id, ?bool $bolehInputMasuk = null, ?bool $bolehInputKeluar = null): void
    {
        $this->error = null;
        $authUser = Auth::user();

        $old = User::query()->find($id);
        if (!$old) {
            $this->error = 'User tidak ditemukan';

            return;
        }
        // Akun admin tidak pernah menginput surat -- switch-nya tidak
        // relevan buat role ini, jangan sampai admin diam-diam diberi hak
        // input lewat panel ini.
        if ($old->role === 'admin') {
            $this->error = 'Akun role admin tidak bisa diberi hak input surat';

            return;
        }

        $masukBaru = $bolehInputMasuk ?? (bool) $old->boleh_input_masuk;
        $keluarBaru = $bolehInputKeluar ?? (bool) $old->boleh_input_keluar;

        $old->boleh_input_masuk = $masukBaru;
        $old->boleh_input_keluar = $keluarBaru;

        // Perubahan hak input berdampak besar ke apa yang boleh dilakukan
        // akun ini -- token dihapus sekalian, sama seperti pola saat role
        // berubah di UserController::update().
        $old->token = null;
        $old->token_expires_at = null;
        $old->save();

        ActivityLogger::log(
            request(),
            $authUser->username,
            $authUser->nama,
            'update',
            "Ubah hak input {$old->nama} ({$old->username}): "
                .'Surat Masuk '.($masukBaru ? 'diizinkan' : 'dicabut').', '
                .'Surat Keluar '.($keluarBaru ? 'diizinkan' : 'dicabut')
        );

        unset($this->users);
    }

    public function render()
    {
        return view('livewire.hak-akses');
    }
}
