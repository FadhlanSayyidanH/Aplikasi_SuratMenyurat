<?php

namespace App\Livewire;

use App\Models\LaporanGangguan;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tombol mengambang "Laporkan Gangguan / Kendala" -- pengganti tombol
 * WhatsApp "Kontak Admin" lama (resources/views/components/kontak-admin.blade.php,
 * dihapus). Beda utama: laporan masuk ke DB (tabel laporan_gangguan) dan
 * ditinjau admin lewat App\Livewire\LaporanGangguanAdmin, bukan chat keluar
 * aplikasi ke nomor pribadi.
 *
 * Dirender di layouts.app SAJA (halaman ber-login) -- halaman login/guest
 * tidak lagi punya kanal lapor, disengaja (butuh identitas pelapor).
 */
class LaporanGangguanWidget extends Component
{
    public string $kategori = 'kendala';

    public string $pesan = '';

    public ?string $error = null;

    public bool $terkirim = false;

    public function kirim(): void
    {
        $this->error = null;

        $user = Auth::user();
        if (!$user) {
            // Defensif -- widget cuma dirender untuk user ber-login.
            return;
        }

        $pesan = trim($this->pesan);
        if ($pesan === '') {
            $this->error = 'Pesan tidak boleh kosong';

            return;
        }
        if (mb_strlen($pesan) > 1000) {
            $this->error = 'Pesan maksimal 1000 karakter';

            return;
        }

        // Kategori TIDAK dipercaya dari klien -- fallback ke 'kendala' kalau
        // bukan salah satu kode yang dikenali.
        $kategori = array_key_exists($this->kategori, LaporanGangguan::KATEGORI)
            ? $this->kategori
            : 'kendala';

        // Identitas pelapor SELALU di-derive dari akun (server-side), bukan
        // dari properti yang bisa diubah klien -- sejalan dengan invarian
        // di docs/features/surat-masuk.md.
        LaporanGangguan::query()->create([
            'pelapor_username' => $user->username,
            'pelapor_nama' => $user->nama,
            'kategori' => $kategori,
            'pesan' => $pesan,
            'halaman' => mb_substr((string) request()->headers->get('referer'), 0, 255) ?: null,
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
            'status' => 'baru',
        ]);

        ActivityLogger::log(
            request(),
            $user->username,
            $user->nama,
            'create',
            "Laporan gangguan [{$kategori}]: \"{$pesan}\"",
        );

        $this->reset(['pesan', 'error']);
        $this->kategori = 'kendala';
        $this->terkirim = true;
    }

    /** Kembali ke form kosong untuk mengirim laporan lain. */
    public function laporLagi(): void
    {
        $this->reset(['pesan', 'error', 'terkirim']);
        $this->kategori = 'kendala';
    }

    public function render()
    {
        return view('livewire.laporan-gangguan-widget');
    }
}
