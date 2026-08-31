<?php

namespace App\Console\Commands;

use App\Models\SuratFile;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Cek status enkripsi-at-rest lampiran surat -- lihat komentar
 * FileEncryptionService::decryptedGet() untuk alasan kenapa file lama &
 * baru bisa bercampur (fitur enkripsi cuma berlaku untuk file BARU, tidak
 * ada migrasi file lama). Command ini MURNI baca (tidak mengubah apa pun).
 *
 * Tanpa argumen: ringkasan jumlah file terenkripsi vs lama (plaintext).
 * Dengan --surat=<id>: rincian per file untuk SATU surat tertentu.
 */
class CekStatusEnkripsiFile extends Command
{
    protected $signature = 'file:cek-enkripsi {--surat= : ID surat tertentu, kosongkan untuk ringkasan semua file}';

    protected $description = 'Cek apakah lampiran surat sudah terenkripsi at-rest atau masih file lama (plaintext)';

    public function handle(): int
    {
        $suratId = $this->option('surat');

        $query = SuratFile::query()->orderBy('surat_id')->orderBy('urutan');
        if ($suratId) {
            $query->where('surat_id', (int) $suratId);
        }

        $files = $query->with('surat:id,nomor_surat,perihal')->get();

        if ($files->isEmpty()) {
            $this->warn($suratId ? "Tidak ada lampiran untuk surat #$suratId." : 'Tidak ada lampiran surat sama sekali.');

            return self::SUCCESS;
        }

        $uploadsDir = config('suratapp.uploads_path');
        $terenkripsi = 0;
        $plaintext = 0;
        $hilang = 0;

        foreach ($files as $file) {
            $path = $uploadsDir.'/'.$file->file_name;

            if (!is_file($path)) {
                $hilang++;
                if ($suratId) {
                    $this->line("  [{$file->id}] {$file->file_name} -- <fg=red>FILE TIDAK DITEMUKAN DI DISK</>");
                }

                continue;
            }

            $status = $this->cekSatuFile($path);
            $status === 'terenkripsi' ? $terenkripsi++ : $plaintext++;

            if ($suratId) {
                $label = $status === 'terenkripsi' ? '<fg=green>terenkripsi</>' : '<fg=yellow>lama (plaintext)</>';
                $this->line("  [{$file->id}] {$file->file_name} ({$file->file_original_name}) -- $label");
            }
        }

        if ($suratId) {
            $surat = $files->first()->surat;
            $this->info('Surat: '.($surat->nomor_surat ?? '(surat keluar)').' -- '.($surat->perihal ?? ''));
        }

        $this->newLine();
        $this->info("Terenkripsi : $terenkripsi");
        $this->info("Lama (plaintext) : $plaintext");
        if ($hilang > 0) {
            $this->error("File tidak ditemukan di disk : $hilang");
        }

        return self::SUCCESS;
    }

    /** 'terenkripsi' kalau Crypt::decrypt() berhasil (format Crypt::encrypt() valid), 'plaintext' kalau gagal (byte file asli mentah). */
    private function cekSatuFile(string $path): string
    {
        $raw = file_get_contents($path);

        try {
            Crypt::decrypt($raw, false);

            return 'terenkripsi';
        } catch (DecryptException) {
            return 'plaintext';
        }
    }
}
