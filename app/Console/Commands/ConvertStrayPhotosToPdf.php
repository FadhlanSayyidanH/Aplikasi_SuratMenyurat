<?php

namespace App\Console\Commands;

use App\Models\SuratFile;
use App\Services\SuratFileService;
use Illuminate\Console\Command;

/**
 * Backfill satu kali untuk lampiran foto (jpg/jpeg/png) yang lolos konversi
 * otomatis ke PDF (lihat App\Services\SuratFileService::konversiFotoKePdf())
 * -- ditemukan Agustus 2026 ada file yang tersimpan sebagai foto mentah
 * karena img2pdf sempat gagal sesaat di produksi tanpa sebab yang bisa
 * dilacak (lihat catatan panjang di konversiFotoKePdf()). Method itu sendiri
 * sekarang sudah retry otomatis supaya kasus baru jarang lolos, tapi
 * lampiran yang SUDAH kadung tersimpan sebagai foto sebelum perbaikan itu
 * perlu dirapikan manual lewat command ini.
 *
 * Aman dijalankan berulang -- hanya memproses baris yang file_name-nya
 * masih berekstensi foto.
 */
class ConvertStrayPhotosToPdf extends Command
{
    protected $signature = 'surat:convert-stray-photos {--dry-run : Cuma tampilkan daftar, jangan ubah apa pun}';

    protected $description = 'Konversi ulang lampiran foto yang lolos dari konversi otomatis ke PDF';

    public function handle(SuratFileService $suratFiles): int
    {
        $uploadsDir = config('suratapp.uploads_path');
        $dryRun = (bool) $this->option('dry-run');

        $strays = SuratFile::query()
            ->where(function ($q) {
                $q->where('file_name', 'like', '%.jpg')
                    ->orWhere('file_name', 'like', '%.jpeg')
                    ->orWhere('file_name', 'like', '%.png');
            })
            ->orderBy('id')
            ->get();

        if ($strays->isEmpty()) {
            $this->info('Tidak ada lampiran foto yang perlu dikonversi.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$strays->count()} lampiran foto.".($dryRun ? ' (dry-run, tidak ada perubahan)' : ''));

        $converted = 0;
        $failed = 0;

        foreach ($strays as $file) {
            $sourcePath = $uploadsDir.'/'.$file->file_name;

            if (!is_file($sourcePath)) {
                $this->warn("  [{$file->id}] {$file->file_name} -- file fisik tidak ditemukan, dilewati.");
                $failed++;

                continue;
            }

            if ($dryRun) {
                $this->line("  [{$file->id}] {$file->file_name} -- akan dikonversi.");

                continue;
            }

            $pdfFileName = $suratFiles->konversiFotoKePdf($sourcePath, $uploadsDir);

            if ($pdfFileName === null) {
                $this->error("  [{$file->id}] {$file->file_name} -- gagal dikonversi (lihat storage/logs/laravel.log).");
                $failed++;

                continue;
            }

            $newOriginalName = pathinfo($file->file_original_name, PATHINFO_FILENAME).'.pdf';
            $file->update([
                'file_name' => $pdfFileName,
                'file_original_name' => $newOriginalName,
            ]);

            $this->info("  [{$file->id}] {$file->file_name} -> {$pdfFileName} OK");
            $converted++;
        }

        if (!$dryRun) {
            $this->newLine();
            $this->info("Selesai: {$converted} berhasil dikonversi, {$failed} gagal.");
        }

        return self::SUCCESS;
    }
}
