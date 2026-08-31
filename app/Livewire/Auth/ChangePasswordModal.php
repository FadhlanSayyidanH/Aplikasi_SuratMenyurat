<?php

namespace App\Livewire\Auth;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dialog "Ganti Password" -- muncul di semua halaman lewat layouts/app
 * (event Alpine `open-change-password` dari tombol sidebar). Aturan
 * bisnisnya (paksa logout setelah ganti) meniru
 * backend/public/auth_change_password.php lama, tapi lewat session,
 * bukan token API.
 */
class ChangePasswordModal extends Component
{
    public bool $show = false;

    public string $passwordLama = '';

    public string $passwordBaru = '';

    public string $passwordBaruKonfirmasi = '';

    public ?string $errorMessage = null;

    #[On('open-change-password')]
    public function open(): void
    {
        $this->reset(['passwordLama', 'passwordBaru', 'passwordBaruKonfirmasi', 'errorMessage']);
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function submit(): void
    {
        $this->errorMessage = null;

        if ($this->passwordLama === '' || $this->passwordBaru === '') {
            $this->errorMessage = 'Password lama dan password baru wajib diisi';

            return;
        }
        if (strlen($this->passwordBaru) < 6) {
            $this->errorMessage = 'Password baru minimal 6 karakter';

            return;
        }
        if ($this->passwordBaru !== $this->passwordBaruKonfirmasi) {
            $this->errorMessage = 'Konfirmasi password baru tidak cocok';

            return;
        }

        $user = Auth::user();

        if (!Hash::check($this->passwordLama, $user->password)) {
            $this->errorMessage = 'Password lama salah';

            return;
        }
        if (Hash::check($this->passwordBaru, $user->password)) {
            $this->errorMessage = 'Password baru tidak boleh sama dengan password lama';

            return;
        }

        $user->password = Hash::make($this->passwordBaru);
        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log(request(), $user->username, $user->nama, 'update', 'Mengganti password akun sendiri');

        // Sama seperti backend lama -- ganti password memaksa logout,
        // termasuk sesi web ini sendiri.
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect(route('login'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.change-password-modal');
    }
}
