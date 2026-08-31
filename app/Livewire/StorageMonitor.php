<?php

namespace App\Livewire;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Panel admin pemantauan kapasitas disk VPS -- total/terpakai/tersisa pada
 * partisi tempat aplikasi berjalan (disk_total_space()/disk_free_space(),
 * murah & selalu real-time), plus breakdown ukuran lampiran surat
 * (config('suratapp.uploads_path'), dihitung rekursif -- bisa lumayan
 * kalau lampirannya sudah banyak) dan ukuran database (metadata
 * information_schema, murah). Ukuran folder lampiran di-cache 5 menit
 * supaya panel ini tidak menghitung ulang seluruh folder setiap kali
 * halaman dibuka -- tombol "Segarkan" memaksa hitung ulang.
 */
#[Layout('layouts.app', ['title' => 'Storage Server'])]
class StorageMonitor extends Component
{
    private const CACHE_KEY = 'storage_monitor.uploads_snapshot';

    public int $diskTotalBytes = 0;

    public int $diskFreeBytes = 0;

    public int $uploadsSizeBytes = 0;

    public int $databaseSizeBytes = 0;

    public ?string $uploadsSizeUpdatedAt = null;

    public function mount(): void
    {
        $this->loadDiskStats();
        $this->loadUploadsSnapshot();
        $this->loadDatabaseSize();
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loadDiskStats();
        $this->loadUploadsSnapshot();
        $this->loadDatabaseSize();
    }

    private function loadDiskStats(): void
    {
        $path = storage_path();
        $this->diskTotalBytes = (int) disk_total_space($path);
        $this->diskFreeBytes = (int) disk_free_space($path);
    }

    private function loadUploadsSnapshot(): void
    {
        $snapshot = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            return [
                'bytes' => $this->folderSizeBytes(config('suratapp.uploads_path')),
                'at' => now()->toDateTimeString(),
            ];
        });

        $this->uploadsSizeBytes = $snapshot['bytes'];
        $this->uploadsSizeUpdatedAt = $snapshot['at'];
    }

    private function loadDatabaseSize(): void
    {
        $row = DB::selectOne(
            'SELECT SUM(data_length + index_length) AS bytes FROM information_schema.tables WHERE table_schema = ?',
            [DB::getDatabaseName()]
        );

        $this->databaseSizeBytes = (int) ($row->bytes ?? 0);
    }

    private function folderSizeBytes(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    public function diskUsedBytes(): int
    {
        return max(0, $this->diskTotalBytes - $this->diskFreeBytes);
    }

    public function diskUsedPercent(): float
    {
        return $this->diskTotalBytes > 0
            ? round(($this->diskUsedBytes() / $this->diskTotalBytes) * 100, 1)
            : 0.0;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : 2).' '.$units[$power];
    }

    public function render()
    {
        return view('livewire.storage-monitor');
    }
}
