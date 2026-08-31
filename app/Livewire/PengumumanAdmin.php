<?php

namespace App\Livewire;

use App\Models\Pengumuman;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Panel admin untuk kirim pengumuman ke SELURUH akun -- beda dari
 * notifikasi surat masuk/keluar (App\Livewire\Dashboard::deteksiNotifikasiBaru()
 * & App\Console\Commands\KirimWebPushNotifikasi), yang otomatis & per-surat.
 * Pengumuman di sini murni teks bebas dari admin, tampil sebagai banner di
 * dashboard SEMUA user (lihat App\Livewire\Dashboard::pengumumanAktif() &
 * resources/views/livewire/dashboard.blade.php) sampai admin sendiri yang
 * menghapusnya -- tidak ada auto-expire, tidak ada dismiss per-user.
 */
#[Layout('layouts.app', ['title' => 'Pengumuman'])]
class PengumumanAdmin extends Component
{
    public string $pesan = '';

    public ?string $error = null;

    #[Computed]
    public function daftar()
    {
        return Pengumuman::query()->orderByDesc('created_at')->get();
    }

    public function kirim(): void
    {
        $this->error = null;
        $pesan = trim($this->pesan);

        if ($pesan === '') {
            $this->error = 'Pesan tidak boleh kosong';

            return;
        }
        if (mb_strlen($pesan) > 1000) {
            $this->error = 'Pesan maksimal 1000 karakter';

            return;
        }

        $admin = Auth::user();

        Pengumuman::query()->create([
            'pesan' => $pesan,
            'dibuat_oleh' => $admin->nama,
        ]);

        ActivityLogger::log(request(), $admin->username, $admin->nama, 'create', "Kirim pengumuman ke seluruh akun: \"{$pesan}\"");

        $this->pesan = '';
        unset($this->daftar);
    }

    public function cabut(int $id): void
    {
        $admin = Auth::user();
        $item = Pengumuman::query()->find($id);
        if (!$item) {
            return;
        }

        ActivityLogger::log(request(), $admin->username, $admin->nama, 'delete', "Cabut pengumuman: \"{$item->pesan}\"");
        $item->delete();

        unset($this->daftar);
    }

    public function render()
    {
        return view('livewire.pengumuman-admin');
    }
}
