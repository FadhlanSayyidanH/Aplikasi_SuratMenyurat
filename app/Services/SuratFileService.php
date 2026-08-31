<?php

namespace App\Services;

use App\Models\Surat;
use App\Models\SuratFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Migrasi 1:1 dari backend/config/surat_files.php (proyek PHP lama) --
 * helper terpusat untuk mengelola file lampiran surat (tabel surat_file,
 * satu surat boleh punya banyak file).
 */
class SuratFileService
{
    public function __construct(private TokenAuthService $auth, private FileEncryptionService $crypto) {}

    /**
     * Validasi satu UploadedFile terhadap whitelist MIME/ekstensi, simpan
     * ke storage/app/uploads dengan nama server unik, dan kembalikan
     * ['file_name' => ..., 'file_original_name' => ..., 'ekstensi_upload' => ...].
     *
     * @throws \RuntimeException kalau tidak valid
     */
    public function simpanFileSurat(UploadedFile $file, array $mimeWhitelist): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Gagal mengunggah salah satu file');
        }

        $maxSize = config('suratapp.max_upload_size_bytes');
        if ($file->getSize() > $maxSize) {
            throw new \RuntimeException("Ukuran file \"{$file->getClientOriginalName()}\" maksimal 200MB");
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!array_key_exists($extension, $mimeWhitelist)) {
            throw new \RuntimeException("Format file \"{$file->getClientOriginalName()}\" tidak didukung");
        }

        $actualMime = $file->getMimeType();
        // docx/pptx/xlsx sebenarnya file zip, jadi finfo kadang cuma
        // mendeteksi application/zip -- diterima juga selama ekstensi cocok.
        if ($actualMime !== $mimeWhitelist[$extension] && $actualMime !== 'application/zip') {
            throw new \RuntimeException("Isi file \"{$file->getClientOriginalName()}\" tidak sesuai dengan ekstensinya");
        }

        $uploadsDir = config('suratapp.uploads_path');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        // Seluruh tahap konversi/kompresi di bawah beroperasi di SCRATCH DIR
        // (bukan langsung di uploads_path) -- img2pdf/Ghostscript butuh file
        // plaintext nyata untuk dibaca/ditulis lewat exec(), jadi plaintext
        // tidak boleh pernah "istirahat" di uploads_path. Enkripsi baru
        // terjadi di langkah PALING TERAKHIR, saat file sudah final, lewat
        // encryptFromPlainPath() ke uploads_path.
        $scratchDir = $this->crypto->scratchDir();

        // bin2hex(random_bytes()) (BUKAN uniqid()) -- uniqid() dibentuk dari
        // microtime + entropi rendah, jadi bisa ditebak/di-brute-force kalau
        // penyerang tahu kira-kira kapan surat diunggah. Nama file di sini
        // jadi satu-satunya "kunci" untuk membuka /uploads/{filename}, jadi
        // harus acak kriptografis.
        $storedFileName = 'surat_'.bin2hex(random_bytes(16)).'.'.$extension;
        $this->pindahkanFileUpload($file, $scratchDir.'/'.$storedFileName);

        $originalName = $file->getClientOriginalName();

        // Foto (jpg/jpeg/png) otomatis dikonversi ke PDF satu halaman.
        if (array_key_exists($extension, config('suratapp.mime_foto'))) {
            $pdfFileName = $this->konversiFotoKePdf($scratchDir.'/'.$storedFileName, $scratchDir);
            if ($pdfFileName !== null) {
                $storedFileName = $pdfFileName;
                $originalName = pathinfo($originalName, PATHINFO_FILENAME).'.pdf';
            }
        }

        $this->kompresJikaPerlu($scratchDir.'/'.$storedFileName, (int) config('suratapp.compress_target_bytes'));

        $this->crypto->encryptFromPlainPath($scratchDir.'/'.$storedFileName, $uploadsDir.'/'.$storedFileName);
        unlink($scratchDir.'/'.$storedFileName);

        return [
            'file_name' => $storedFileName,
            'file_original_name' => $originalName,
            'ekstensi_upload' => $extension,
        ];
    }

    /**
     * $file->move() bawaan Symfony/Laravel memanggil move_uploaded_file(),
     * yang CUMA berhasil untuk upload PHP asli (HTTP $_FILES) di request
     * yang sama -- gagal senyap (pesan error KOSONG) untuk
     * Livewire\Features\SupportFileUploads\TemporaryUploadedFile (dipakai
     * form upload web ini, mis. SuratReview::uploadFiles()), karena file
     * itu sudah disimpan ke disk livewire-tmp di request SEBELUMNYA (saat
     * dipilih di browser), bukan upload $_FILES di request saat ini. rename()
     * langsung ke path fisik ($file->getRealPath()) bekerja untuk KEDUA
     * kasus selama sumber & tujuan satu filesystem (selalu begitu di sini,
     * semua di bawah storage/app/).
     */
    private function pindahkanFileUpload(UploadedFile $file, string $destination): void
    {
        $source = $file->getRealPath();
        if (@rename($source, $destination)) {
            return;
        }
        if (@copy($source, $destination)) {
            @unlink($source);

            return;
        }

        throw new \RuntimeException("Gagal menyimpan file \"{$file->getClientOriginalName()}\"");
    }

    /**
     * Varian khusus Surat Masuk: kalau user mengunggah 2 FOTO (jpg/jpeg/png)
     * atau lebih sekaligus, gabungkan SEMUA foto itu jadi SATU PDF
     * multi-halaman (bukan N lampiran PDF satu-halaman terpisah seperti
     * perilaku default simpanFileSurat()) -- keluhan pengguna: hasil
     * scan/foto surat fisik berhalaman banyak jadi berantakan, harus buka
     * file satu-satu untuk baca satu surat yang sama. File non-foto
     * (docx/pptx/xlsx/pdf) dan kasus <2 foto TETAP diproses satu-per-satu
     * lewat simpanFileSurat() seperti sebelumnya -- method ini SENGAJA
     * cuma dipanggil untuk jenis='masuk' oleh App\Livewire\Surat\SuratForm
     * & App\Http\Controllers\Api\SuratController@create, TIDAK mengubah
     * apa pun untuk Surat Keluar.
     *
     * @param  UploadedFile[]  $files
     * @param  array<int, string>  $displayNames  Nama tampilan pilihan user, KEY sejajar dengan index $files.
     * @return array<int, array{file_name: string, file_original_name: string, ekstensi_upload: string}>
     *         Gabungan foto (kalau ada) SELALU di posisi pertama, diikuti file lain sesuai urutan asal.
     *
     * @throws \RuntimeException kalau ada file yang tidak valid ATAU penggabungan foto gagal total
     */
    public function simpanFileSuratMasuk(array $files, array $displayNames, array $mimeWhitelist): array
    {
        $mimeFoto = config('suratapp.mime_foto');

        $fotoEntries = [];
        $lainEntries = [];
        foreach ($files as $index => $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (array_key_exists($extension, $mimeFoto)) {
                $fotoEntries[$index] = $file;
            } else {
                $lainEntries[$index] = $file;
            }
        }

        $stored = [];
        try {
            if (count($fotoEntries) >= 2) {
                $firstIndex = array_key_first($fotoEntries);
                $saved = $this->simpanFotoGabunganSebagaiPdf(array_values($fotoEntries), $mimeWhitelist);
                $customName = trim((string) ($displayNames[$firstIndex] ?? ''));
                if ($customName !== '') {
                    $saved['file_original_name'] = $this->terapkanNamaTampilan($saved, $customName);
                }
                $stored[] = $saved;
            } else {
                // <2 foto -- gabungkan balik ke daftar "diproses biasa" di
                // bawah (termasuk konversi 1 foto -> 1 PDF seperti sebelumnya).
                foreach ($fotoEntries as $index => $file) {
                    $lainEntries[$index] = $file;
                }
            }

            ksort($lainEntries);
            foreach ($lainEntries as $index => $file) {
                $saved = $this->simpanFileSurat($file, $mimeWhitelist);
                $customName = trim((string) ($displayNames[$index] ?? ''));
                if ($customName !== '') {
                    $saved['file_original_name'] = $this->terapkanNamaTampilan($saved, $customName);
                }
                $stored[] = $saved;
            }
        } catch (\RuntimeException $e) {
            foreach ($stored as $s) {
                @unlink(config('suratapp.uploads_path').'/'.$s['file_name']);
            }
            throw $e;
        }

        return $stored;
    }

    /**
     * Validasi & simpan N file foto sebagai SATU PDF gabungan multi-halaman
     * (urutan halaman = urutan $files) -- dipakai simpanFileSuratMasuk().
     * Mirror simpanFileSurat() untuk validasi per-file (ukuran, ekstensi,
     * MIME asli) & alur scratch-dir -> kompresi -> enkripsi, tapi img2pdf
     * dipanggil SATU KALI dengan SEMUA path foto sebagai argumen (img2pdf
     * sendiri sudah mendukung banyak input -> 1 PDF multi-halaman, tidak
     * perlu tool tambahan).
     *
     * @param  UploadedFile[]  $files
     *
     * @throws \RuntimeException kalau ada foto yang tidak valid atau penggabungan gagal total
     */
    private function simpanFotoGabunganSebagaiPdf(array $files, array $mimeWhitelist): array
    {
        $maxSize = config('suratapp.max_upload_size_bytes');
        $scratchDir = $this->crypto->scratchDir();
        $scratchPaths = [];

        foreach ($files as $file) {
            if (!$file->isValid()) {
                throw new \RuntimeException('Gagal mengunggah salah satu file');
            }
            if ($file->getSize() > $maxSize) {
                throw new \RuntimeException("Ukuran file \"{$file->getClientOriginalName()}\" maksimal 200MB");
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!array_key_exists($extension, $mimeWhitelist)) {
                throw new \RuntimeException("Format file \"{$file->getClientOriginalName()}\" tidak didukung");
            }
            $actualMime = $file->getMimeType();
            if ($actualMime !== $mimeWhitelist[$extension] && $actualMime !== 'application/zip') {
                throw new \RuntimeException("Isi file \"{$file->getClientOriginalName()}\" tidak sesuai dengan ekstensinya");
            }

            $storedFileName = 'surat_'.bin2hex(random_bytes(16)).'.'.$extension;
            $path = $scratchDir.'/'.$storedFileName;
            $this->pindahkanFileUpload($file, $path);
            $scratchPaths[] = $path;
        }

        $originalName = pathinfo($files[0]->getClientOriginalName(), PATHINFO_FILENAME).'.pdf';
        // Ekstensi ASLI foto pertama (bukan 'pdf') -- dipakai terapkanNamaTampilan()
        // di caller untuk mendeteksi "customName masih pakai ekstensi upload
        // asli, ganti ke ekstensi fisik final" (sama seperti simpanFileSurat()
        // biasa, lihat komentar di terapkanNamaTampilan()).
        $ekstensiAsli = strtolower($files[0]->getClientOriginalExtension());

        $pdfFileName = $this->gabungkanFotoKePdf($scratchPaths, $scratchDir);
        if ($pdfFileName === null) {
            foreach ($scratchPaths as $p) {
                @unlink($p);
            }

            throw new \RuntimeException('Gagal menggabungkan foto menjadi satu PDF, coba lagi');
        }

        $this->kompresJikaPerlu($scratchDir.'/'.$pdfFileName, (int) config('suratapp.compress_target_bytes'));

        $uploadsDir = config('suratapp.uploads_path');
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $this->crypto->encryptFromPlainPath($scratchDir.'/'.$pdfFileName, $uploadsDir.'/'.$pdfFileName);
        unlink($scratchDir.'/'.$pdfFileName);

        return [
            'file_name' => $pdfFileName,
            'file_original_name' => $originalName,
            'ekstensi_upload' => $ekstensiAsli,
        ];
    }

    /**
     * Gabungkan N foto (path sudah di scratch dir) jadi SATU PDF
     * multi-halaman lewat img2pdf CLI (mendukung banyak input sekaligus
     * secara native) -- retry & flag --rotation=ifvalid SAMA PERSIS dengan
     * konversiFotoKePdf() di bawah (lihat komentar lengkap di sana soal
     * kenapa perlu retry & kenapa "ifvalid", bukan "auto"). Null kalau
     * gagal setelah 3x percobaan; source path TETAP ADA (caller yang
     * membersihkan) supaya jejak untuk debug tidak langsung hilang.
     *
     * @param  string[]  $sourcePaths
     */
    private function gabungkanFotoKePdf(array $sourcePaths, string $uploadsDir): ?string
    {
        $pdfFileName = 'surat_'.bin2hex(random_bytes(16)).'.pdf';
        $pdfPath = $uploadsDir.'/'.$pdfFileName;

        $img2pdfBin = is_executable('/usr/bin/img2pdf') ? '/usr/bin/img2pdf' : 'img2pdf';
        $sourceArgs = implode(' ', array_map('escapeshellarg', $sourcePaths));
        $cmd = escapeshellarg($img2pdfBin).' --rotation=ifvalid '.$sourceArgs
            .' -o '.escapeshellarg($pdfPath).' 2>&1';

        $maxAttempts = 3;
        $lastOutput = [];
        $lastExitCode = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $output = [];
            exec($cmd, $output, $exitCode);
            $lastOutput = $output;
            $lastExitCode = $exitCode;

            if ($exitCode === 0 && is_file($pdfPath)) {
                foreach ($sourcePaths as $p) {
                    unlink($p);
                }

                return $pdfFileName;
            }

            if (is_file($pdfPath)) {
                unlink($pdfPath);
            }
            if ($attempt < $maxAttempts) {
                usleep(300_000);
            }
        }

        Log::error('Gabungkan foto ke satu PDF gagal setelah '.$maxAttempts.' percobaan.', [
            'sources' => $sourcePaths,
            'exit_code' => $lastExitCode,
            'output' => implode("\n", $lastOutput),
        ]);

        return null;
    }

    /**
     * Gabungkan nama tampilan pilihan pengguna dengan hasil simpanFileSurat(),
     * supaya tetap punya ekstensi yang benar.
     */
    public function terapkanNamaTampilan(array $saved, string $customName): string
    {
        $extFisik = pathinfo($saved['file_name'], PATHINFO_EXTENSION);
        $extUpload = $saved['ekstensi_upload'];
        $extCustom = pathinfo($customName, PATHINFO_EXTENSION);

        if (strcasecmp($extCustom, $extUpload) === 0) {
            $namaTanpaEkstensi = pathinfo($customName, PATHINFO_FILENAME);

            return $namaTanpaEkstensi.'.'.$extFisik;
        }
        if (strcasecmp($extCustom, $extFisik) === 0) {
            return $customName;
        }

        return $customName.'.'.$extFisik;
    }

    /**
     * Konversi satu file foto jadi PDF satu halaman lewat img2pdf CLI.
     * Null kalau konversi gagal -- caller mempertahankan file foto aslinya.
     *
     * Coba ulang sampai 3x (jeda singkat di antaranya) sebelum benar-benar
     * menyerah -- ditemukan di produksi (Agustus 2026) img2pdf kadang gagal
     * tanpa sebab yang bisa dilacak (CPU/RAM/disk/swap semuanya normal
     * waktu itu, dan file yang sama selalu berhasil dikonversi ulang begitu
     * dicoba lagi beberapa saat kemudian) -- polanya bersifat SESAAT, bukan
     * masalah pada file atau environment yang menetap. Kegagalan yang tetap
     * terjadi setelah 3x percobaan dicatat ke log (dulu senyap total,
     * sehingga kasus di atas nyaris mustahil didiagnosis dari log saja).
     */
    public function konversiFotoKePdf(string $sourcePath, string $uploadsDir): ?string
    {
        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
        $pdfFileName = $baseName.'.pdf';
        $pdfPath = $uploadsDir.'/'.$pdfFileName;

        // --rotation=ifvalid -- ditemukan di produksi (Agustus 2026) img2pdf
        // MENOLAK konversi total (bukan cuma abai rotasi) untuk foto yang
        // tag EXIF Orientation-nya "0" (bukan kode EXIF yang valid, 1-8,
        // tapi tetap ditulis apa adanya oleh sebagian aplikasi kamera HP) --
        // perilaku default img2pdf ("auto") mewajibkan tag itu valid.
        // "ifvalid" menerapkan rotasi HANYA kalau tag-nya valid, dan diam-
        // diam mengabaikannya (bukan gagal) kalau tidak -- persis solusi
        // yang disarankan img2pdf sendiri di pesan errornya.
        $img2pdfBin = is_executable('/usr/bin/img2pdf') ? '/usr/bin/img2pdf' : 'img2pdf';
        $cmd = escapeshellarg($img2pdfBin).' --rotation=ifvalid '.escapeshellarg($sourcePath)
            .' -o '.escapeshellarg($pdfPath).' 2>&1';

        $maxAttempts = 3;
        $lastOutput = [];
        $lastExitCode = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $output = [];
            exec($cmd, $output, $exitCode);
            $lastOutput = $output;
            $lastExitCode = $exitCode;

            if ($exitCode === 0 && is_file($pdfPath)) {
                unlink($sourcePath);

                return $pdfFileName;
            }

            if (is_file($pdfPath)) {
                unlink($pdfPath);
            }

            if ($attempt < $maxAttempts) {
                usleep(300_000);
            }
        }

        // Log::error() (bukan warning()) -- LOG_LEVEL di .env production diset "error",
        // jadi level di bawah itu senyap total dan tidak pernah tercatat.
        Log::error('Konversi foto ke PDF gagal setelah '.$maxAttempts.' percobaan, file dipertahankan sebagai foto.', [
            'source' => $sourcePath,
            'exit_code' => $lastExitCode,
            'output' => implode("\n", $lastOutput),
        ]);

        return null;
    }

    /**
     * Kompresi otomatis SATU file lampiran (bukan gabungan seluruh lampiran
     * satu surat) supaya ukurannya di bawah $targetBytes. Dijamin tercapai
     * untuk foto & PDF, usaha terbaik saja untuk docx/pptx/xlsx (lihat
     * kompresOffice()). Dipanggil di akhir simpanFileSurat() -- $path sudah
     * menunjuk ke nama file FINAL (setelah foto dikonversi ke PDF kalau
     * berlaku), gagal diam-diam (biarkan file asli tidak terkompresi)
     * kalau tool CLI-nya tidak tersedia, supaya upload tidak sampai gagal
     * total hanya gara-gara kompresi tidak berhasil.
     */
    private function kompresJikaPerlu(string $path, int $targetBytes): void
    {
        if (!is_file($path) || filesize($path) <= $targetBytes) {
            return;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        match ($extension) {
            'pdf' => $this->kompresPdf($path, $targetBytes),
            'jpg', 'jpeg', 'png' => $this->kompresGambar($path, $extension, $targetBytes),
            'docx', 'pptx', 'xlsx' => $this->kompresOffice($path, $targetBytes),
            default => null,
        };
    }

    /**
     * Kompres ulang PDF lewat Ghostscript, coba beberapa tingkat resolusi/
     * kualitas gambar dari paling ringan turun sampai tembus target (atau
     * tingkat habis) -- selalu pakai hasil TERKECIL yang didapat, tidak
     * pernah membuat file lebih besar dari sebelumnya.
     *
     * SENGAJA tidak pakai preset bawaan (-dPDFSETTINGS=/screen dst) --
     * preset itu punya "ColorImageDownsampleThreshold" default 1.5x, jadi
     * gambar yang resolusinya cuma SEDIKIT di atas target sama sekali tidak
     * ikut dikompres (diteruskan mentah apa adanya). Ini bukan kasus
     * langka: foto hasil konversi img2pdf (lihat konversiFotoKePdf() di
     * atas) sering berakhir di kisaran ~72-96 DPI "nominal" APAPUN jumlah
     * megapixel aslinya (kamera HP umumnya menulis metadata 72/96 DPI di
     * EXIF, tidak berhubungan dengan resolusi sensor) -- persis di zona
     * mati preset bawaan. -dColorImageDownsampleThreshold=1.0 di bawah
     * menghilangkan zona mati itu: downsample terjadi tiap kali ada
     * kesempatan, betapa pun kecilnya.
     */
    private function kompresPdf(string $path, int $targetBytes): void
    {
        $gsBin = is_executable('/usr/bin/gs') ? '/usr/bin/gs' : 'gs';

        $terkecilPath = null;
        $terkecilSize = filesize($path);

        // [resolusi target (DPI), kualitas JPEG] -- turun bertahap.
        $tingkatan = [[150, 60], [100, 45], [72, 35]];

        foreach ($tingkatan as [$resolusi, $kualitas]) {
            $tmpPath = $path.'.compress-'.bin2hex(random_bytes(4)).'.pdf';
            $cmd = escapeshellarg($gsBin)
                .' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4'
                .' -dDownsampleColorImages=true -dColorImageResolution='.$resolusi.' -dColorImageDownsampleThreshold=1.0'
                .' -dDownsampleGrayImages=true -dGrayImageResolution='.$resolusi.' -dGrayImageDownsampleThreshold=1.0'
                .' -dAutoFilterColorImages=false -dColorImageFilter=/DCTEncode'
                .' -dAutoFilterGrayImages=false -dGrayImageFilter=/DCTEncode'
                .' -dJPEGQ='.$kualitas
                .' -dNOPAUSE -dQUIET -dBATCH -sOutputFile='.escapeshellarg($tmpPath)
                .' '.escapeshellarg($path).' 2>&1';
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && is_file($tmpPath) && filesize($tmpPath) > 0 && filesize($tmpPath) < $terkecilSize) {
                if ($terkecilPath !== null) {
                    @unlink($terkecilPath);
                }
                $terkecilPath = $tmpPath;
                $terkecilSize = filesize($tmpPath);
            } else {
                @unlink($tmpPath);
            }

            if ($terkecilSize <= $targetBytes) {
                break;
            }
        }

        if ($terkecilPath !== null) {
            rename($terkecilPath, $path);
        }
    }

    /**
     * Kompres foto (fallback -- hanya kepakai kalau konversiFotoKePdf()
     * gagal, mis. img2pdf tidak terpasang, sehingga file tetap berupa
     * jpg/png). JPEG diturunkan kualitasnya bertahap; PNG (lossless, tidak
     * ada "kualitas") langsung diperkecil dimensinya. Kalau kualitas
     * serendah mungkin masih di atas target, dimensi diperkecil bertahap
     * sebagai langkah terakhir.
     */
    private function kompresGambar(string $path, string $extension, int $targetBytes): void
    {
        $image = $extension === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if ($image === false) {
            return;
        }

        if ($extension === 'png') {
            imagepng($image, $path, 9);
        } else {
            $quality = 85;
            do {
                imagejpeg($image, $path, $quality);
                clearstatcache(true, $path);
                $quality -= 15;
            } while (filesize($path) > $targetBytes && $quality > 10);
        }

        clearstatcache(true, $path);
        while (filesize($path) > $targetBytes && imagesx($image) > 400) {
            $width = (int) (imagesx($image) * 0.8);
            $height = (int) (imagesy($image) * 0.8);
            $resized = imagescale($image, $width, $height);
            imagedestroy($image);
            $image = $resized;

            if ($extension === 'png') {
                imagepng($image, $path, 9);
            } else {
                imagejpeg($image, $path, 60);
            }
            clearstatcache(true, $path);
        }

        imagedestroy($image);
    }

    /**
     * Usaha terbaik SAJA (tidak dijamin tembus target) -- docx/pptx/xlsx
     * adalah arsip zip, cuma gambar JPEG tertanam di dalamnya (word/media,
     * ppt/media, xl/media) yang dikompres ulang di tempat. PNG tertanam
     * SENGAJA dilewati (bukan diganti byte JPEG) supaya ekstensi entri zip
     * -- dipakai Office untuk tahu format gambarnya -- tetap cocok dengan
     * isinya. Dokumen tanpa gambar besar (teks/formatting saja) nyaris
     * tidak akan mengecil sama sekali, itu wajar.
     */
    private function kompresOffice(string $path, int $targetBytes): void
    {
        if (!class_exists(\ZipArchive::class)) {
            return;
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }

        // Kumpulkan dulu daftar nama entri SEBELUM memodifikasi apa pun --
        // ZipArchive::addFromString() menambah entri baru (bukan menimpa di
        // tempat, entri lama ditandai hapus lewat deleteName() terpisah),
        // jadi numFiles ikut bertambah selama proses. Iterasi langsung
        // lewat index 0..numFiles sambil memodifikasi akan balik memproses
        // ulang entri yang baru saja ditambahkan sendiri.
        $namaGambar = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^(word|ppt|xl)/media/.+\.jpe?g$#i', $name)) {
                $namaGambar[] = $name;
            }
        }

        foreach ($namaGambar as $name) {
            $data = $zip->getFromName($name);
            if ($data === false) {
                continue;
            }

            $image = @imagecreatefromstring($data);
            if ($image === false) {
                continue;
            }

            ob_start();
            imagejpeg($image, null, 70);
            $compressed = ob_get_clean();
            imagedestroy($image);

            if ($compressed !== false && strlen($compressed) > 0 && strlen($compressed) < strlen($data)) {
                $zip->deleteName($name);
                $zip->addFromString($name, $compressed);
            }
        }

        $zip->close();
    }

    /** Ambil seluruh file milik satu surat, urut, bentuk siap-JSON. */
    public function getSuratFiles(int $suratId, string $baseUrl, User $authUser): array
    {
        $fileToken = $this->auth->getOrCreateFileToken($authUser);

        return SuratFile::query()
            ->where('surat_id', $suratId)
            ->orderBy('urutan')->orderBy('id')
            ->get()
            ->map(fn (SuratFile $f) => $this->suratFileToJson($f, $baseUrl, $fileToken))
            ->all();
    }

    /** Varian batch untuk daftar banyak surat sekaligus -- map surat_id => [file, ...]. */
    public function getSuratFilesBatch(array $suratIds, string $baseUrl, User $authUser): array
    {
        if (!$suratIds) {
            return [];
        }

        $fileToken = $this->auth->getOrCreateFileToken($authUser);
        $bySurat = [];
        $files = SuratFile::query()
            ->whereIn('surat_id', $suratIds)
            ->orderBy('surat_id')->orderBy('urutan')->orderBy('id')
            ->get();
        foreach ($files as $f) {
            $bySurat[$f->surat_id][] = $this->suratFileToJson($f, $baseUrl, $fileToken);
        }

        return $bySurat;
    }

    /**
     * true kalau $authUser boleh menambah/menghapus/mengganti-nama file
     * lampiran Surat Keluar $suratId ini, tergantung $suratStatus. Caller
     * tetap wajib cek sendiri $suratStatus !== 'disetujui' SEBELUM memanggil
     * ini.
     */
    public function bolehKelolaFileSuratKeluar(int $suratId, string $suratStatus, User $authUser): bool
    {
        $rows = \App\Models\SuratApproval::query()
            ->where('surat_id', $suratId)
            ->orderBy('urutan')
            ->get(['urutan', 'role', 'status']);

        if ($suratStatus === 'ditolak') {
            return $rows->contains(fn ($row) => $row->role === $authUser->nama);
        }

        if ($suratStatus === 'menunggu') {
            $pertamaMenunggu = $rows->firstWhere('status', 'menunggu');

            return $pertamaMenunggu !== null && $pertamaMenunggu->role === $authUser->nama;
        }

        return false;
    }

    /** Ambil satu row surat lengkap dengan files & approval_detail, bentuk siap-JSON. */
    public function suratDetailDenganFiles(int $suratId, User $authUser): array
    {
        $surat = Surat::query()->findOrFail($suratId);
        $baseUrl = BaseUrlResolver::resolve(request());

        $row = $surat->toArray();
        $row['files'] = $this->getSuratFiles($suratId, $baseUrl, $authUser);
        $row['approval_detail'] = $surat->approval()->get([
            'urutan', 'role', 'status', 'instruksi', 'catatan', 'diproses_oleh', 'diproses_at',
        ])->toArray();

        return $row;
    }

    private function suratFileToJson(SuratFile $file, string $baseUrl, string $token): array
    {
        // Parameter `v` (mtime file di disk) supaya URL berubah setiap kali
        // isi file ditimpa (mis. lewat OnlyOffice) -- mencegah viewer
        // menampilkan versi cache lama walau nama file sama persis.
        $filePath = config('suratapp.uploads_path').'/'.$file->file_name;
        $mtime = is_file($filePath) ? filemtime($filePath) : 0;

        return [
            'id' => $file->id,
            'file_name' => $file->file_name,
            'file_original_name' => $file->file_original_name,
            'file_url' => $baseUrl.'/uploads/'.$file->file_name.'?token='.urlencode($token).'&v='.$mtime,
        ];
    }
}
