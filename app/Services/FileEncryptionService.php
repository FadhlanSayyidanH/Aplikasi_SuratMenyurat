<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Enkripsi-at-rest lampiran surat di storage/app/uploads (AES-256-CBC via
 * APP_KEY, Crypt::encrypt/decrypt dengan $serialize=false supaya byte biner
 * file tidak diproses sebagai PHP-serialized value). Dipakai raw
 * file_get_contents/file_put_contents (BUKAN Storage:: facade) supaya
 * konsisten dengan pola yang sudah ada di SuratFileService/FileServeController
 * -- uploads_path bukan disk terdaftar di config/filesystems.php, semua kode
 * di app ini sudah memakai path mentah (is_file(), filemtime(), dst).
 *
 * img2pdf/Ghostscript/LibreOffice (dipanggil lewat exec()) TIDAK bisa
 * memproses ciphertext -- decryptToTempFile()/encryptFromPlainPath() ADA
 * khusus untuk jembatan itu: dekripsi ke file plaintext sementara sebelum
 * exec(), enkripsi lagi hasilnya sesudah exec() selesai.
 */
class FileEncryptionService
{
    /** Direktori scratch untuk file plaintext sementara -- SATU filesystem dengan uploads_path supaya rename() antar keduanya tetap cepat/atomik. */
    public function scratchDir(): string
    {
        $dir = storage_path('app/tmp');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** Enkripsi $plaintext, tulis ke $path. */
    public function encryptedPut(string $path, string $plaintext): void
    {
        file_put_contents($path, Crypt::encrypt($plaintext, false));
    }

    /**
     * Baca isi $path, kembalikan plaintext-nya. Fitur enkripsi ini SENGAJA
     * cuma berlaku untuk file BARU (lihat komentar di kelas ini) -- file
     * lama yang sudah ada sebelum fitur ini aktif TETAP plaintext apa
     * adanya di disk, selamanya (tidak ada migrasi). Karena satu direktori
     * uploads_path berisi CAMPURAN keduanya, di sini dicoba dekripsi dulu;
     * kalau gagal (DecryptException -- "payload is invalid", persis yang
     * terjadi untuk byte PDF/DOCX/JPG mentah yang bukan hasil Crypt::encrypt()),
     * anggap saja $path itu file lama, kembalikan isinya apa adanya.
     */
    public function decryptedGet(string $path): string
    {
        $raw = file_get_contents($path);

        try {
            return Crypt::decrypt($raw, false);
        } catch (DecryptException) {
            return $raw;
        }
    }

    /**
     * Dekripsi $storedPath, tulis plaintext-nya ke file baru di scratch dir,
     * kembalikan path-nya. Caller WAJIB unlink() sendiri setelah selesai
     * (idealnya lewat try/finally, supaya tetap terhapus walau exec() gagal).
     */
    public function decryptToTempFile(string $storedPath, string $suffix = ''): string
    {
        $tempPath = $this->scratchDir().'/'.bin2hex(random_bytes(16)).$suffix;
        file_put_contents($tempPath, $this->decryptedGet($storedPath));

        return $tempPath;
    }

    /** Baca plaintext dari $plainPath, enkripsi, tulis ke $destPath. TIDAK menghapus $plainPath. */
    public function encryptFromPlainPath(string $plainPath, string $destPath): void
    {
        $this->encryptedPut($destPath, file_get_contents($plainPath));
    }
}
