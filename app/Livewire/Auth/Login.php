<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman login web (session Laravel biasa) -- migrasi dari
 * lib/screens/login_screen.dart. Aturan bisnisnya (brute-force lockout,
 * dsb) meniru backend/public/auth_login.php lama persis, tapi lewat
 * Auth::attempt() session, BUKAN token bearer (itu jalur khusus app
 * Flutter/API, lihat App\Http\Controllers\Api\AuthController).
 */
#[Layout('layouts.guest')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $rememberMe = false;

    public ?string $errorMessage = null;

    /**
     * Tangkap pesan dari App\Http\Middleware\EnsureSingleWebSession (kalau
     * kick-nya ketahuan lewat navigasi halaman penuh, redirect HTML biasa
     * -- BUKAN lewat wire:poll di latar belakang, itu jalur terpisah lewat
     * sessionStorage, lihat resources/views/livewire/auth/login.blade.php).
     */
    public function mount(): void
    {
        $this->errorMessage = session('status');
    }

    public function login(): void
    {
        $this->errorMessage = null;
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi',
            'password.required' => 'Password wajib diisi',
        ]);

        $maxAttempts = config('suratapp.login_max_attempts');
        $lockoutMinutes = config('suratapp.login_lockout_minutes');

        $user = User::query()->where('username', $this->username)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $this->errorMessage = 'Terlalu banyak percobaan gagal. Coba lagi setelah '
                .$user->locked_until->format('H:i');

            return;
        }

        if (!$user || !Hash::check($this->password, $user->password)) {
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->failed_login_attempts = $attempts;
                if ($attempts >= $maxAttempts) {
                    $user->locked_until = now()->addMinutes($lockoutMinutes);
                }
                $user->save();
            }
            $this->errorMessage = 'Username atau password salah';

            return;
        }

        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        Auth::login($user, $this->rememberMe);
        request()->session()->regenerate();

        // Satu akun cuma boleh punya SATU sesi web aktif -- menyimpan ID
        // sesi BARU di sini otomatis membuat sesi LAMA (kalau ada, mis.
        // browser/perangkat lain yang login pakai akun yang sama) langsung
        // ditolak di request berikutnya lewat
        // App\Http\Middleware\EnsureSingleWebSession (persis seperti kolom
        // `token` API yang juga cuma menyimpan satu nilai).
        $user->web_session_id = request()->session()->getId();
        $user->save();

        ActivityLogger::log(request(), $user->username, $user->nama, 'login');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
