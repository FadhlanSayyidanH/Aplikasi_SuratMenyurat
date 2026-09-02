<?php

namespace App\Livewire;

use App\Models\LaporanGangguan;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel admin untuk meninjau laporan gangguan/kendala yang dikirim user
 * lewat App\Livewire\LaporanGangguanWidget. Pola mengikuti
 * App\Livewire\PengumumanAdmin (admin-only, list + aksi sederhana).
 *
 * Beda: laporan punya `status` ('baru'/'selesai') supaya admin bisa
 * melacak mana yang sudah ditangani -- jumlah yang 'baru' juga jadi angka
 * badge di menu sidebar (lihat resources/views/layouts/app.blade.php).
 */
#[Layout('layouts.app', ['title' => 'Laporan Gangguan'])]
class LaporanGangguanAdmin extends Component
{
    /** baru | selesai | semua */
    #[Url]
    public string $filter = 'baru';

    #[Computed]
    public function daftar()
    {
        return LaporanGangguan::query()
            ->when($this->filter !== 'semua', fn ($q) => $q->where('status', $this->filter))
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function jumlah(): array
    {
        $per = LaporanGangguan::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $baru = (int) $per->get('baru', 0);
        $selesai = (int) $per->get('selesai', 0);

        return ['baru' => $baru, 'selesai' => $selesai, 'semua' => $baru + $selesai];
    }

    public function tandaiSelesai(int $id): void
    {
        $admin = Auth::user();
        $item = LaporanGangguan::query()->find($id);
        if (!$item) {
            return;
        }

        $item->update([
            'status' => 'selesai',
            'ditangani_oleh' => $admin->nama,
            'ditangani_pada' => now(),
        ]);

        ActivityLogger::log(request(), $admin->username, $admin->nama, 'update', "Tandai selesai laporan gangguan #{$id} ({$item->pelapor_nama})");

        unset($this->daftar, $this->jumlah);
    }

    public function tandaiBaru(int $id): void
    {
        $admin = Auth::user();
        $item = LaporanGangguan::query()->find($id);
        if (!$item) {
            return;
        }

        $item->update([
            'status' => 'baru',
            'ditangani_oleh' => null,
            'ditangani_pada' => null,
        ]);

        ActivityLogger::log(request(), $admin->username, $admin->nama, 'update', "Buka lagi laporan gangguan #{$id} ({$item->pelapor_nama})");

        unset($this->daftar, $this->jumlah);
    }

    public function hapus(int $id): void
    {
        $admin = Auth::user();
        $item = LaporanGangguan::query()->find($id);
        if (!$item) {
            return;
        }

        ActivityLogger::log(request(), $admin->username, $admin->nama, 'delete', "Hapus laporan gangguan #{$id} ({$item->pelapor_nama}): \"{$item->pesan}\"");
        $item->delete();

        unset($this->daftar, $this->jumlah);
    }

    public function render()
    {
        return view('livewire.laporan-gangguan-admin');
    }
}
