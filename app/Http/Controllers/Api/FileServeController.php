<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\SuratFile;
use App\Models\User;
use App\Services\FileEncryptionService;
use App\Services\TokenAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Migrasi dari backend/public/router.php (proyek PHP lama) -- dua rute
 * penyaji berkas mentah: pratinjau PDF on-the-fly (docx/pptx/xlsx via
 * LibreOffice headless) & penyajian berkas asli apa adanya. Kedua rute
 * mewajibkan `file_token` valid lewat ?token= (BUKAN token sesi login),
 * lihat TokenAuthService::requireFileToken().
 */
class FileServeController extends Controller
{
    private const CONTENT_TYPE_BY_EXTENSION = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(private TokenAuthService $auth, private FileEncryptionService $crypto) {}

    /**
     * GET /uploads/preview/{path} -- konversi on-the-fly ke PDF via
     * `soffice --headless --convert-to pdf`, di-cache di
     * config('suratapp.preview_cache_path') keyed by nama-tanpa-ekstensi,
     * kadaluarsa berdasarkan mtime sumber (berkas asli tidak disentuh).
     */
    public function preview(Request $request, string $path)
    {
        $user = $this->auth->requireFileToken($request);

        // Regex identik dengan router.php lama -- $path sudah dibatasi
        // karakternya lewat ->where() route, tapi tetap dipecah nama/ekstensi
        // di sini persis seperti versi lama (backtracking PCRE yang sama)
        // supaya nama file mengandung titik (mis. "surat.v2.docx") diproses
        // identik.
        if (!preg_match('#^([A-Za-z0-9_.\-]+)\.(docx|pptx|xlsx)$#', $path, $matches)) {
            return response('File not found', 404);
        }
        $baseName = $matches[1];
        $sourceName = $matches[1].'.'.$matches[2];
        $sourcePath = config('suratapp.uploads_path').'/'.$sourceName;

        if (!is_file($sourcePath)) {
            return response('File not found', 404);
        }

        $this->authorizeFile($sourceName, $user);

        $cacheDir = config('suratapp.preview_cache_path');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $cachedPdfPath = $cacheDir.'/'.$baseName.'.pdf';

        if (!is_file($cachedPdfPath) || filemtime($cachedPdfPath) < filemtime($sourcePath)) {
            // $sourcePath tersimpan terenkripsi -- LibreOffice butuh file
            // plaintext nyata untuk dibaca, jadi dekripsi dulu ke scratch
            // dir, konversi dari SANA, lalu selalu bersihkan temp-nya
            // (try/finally) walau soffice gagal di tengah jalan.
            $plainSourcePath = $this->crypto->decryptToTempFile($sourcePath, '.'.$matches[2]);
            try {
                // Profil user unik per konversi -- LibreOffice headless menolak
                // jalan kalau ada instance lain memakai profil yang sama secara
                // bersamaan.
                $profileDir = sys_get_temp_dir().'/soffice_profile_'.uniqid();
                $sofficeBin = is_executable('/usr/bin/soffice') ? '/usr/bin/soffice' : 'soffice';
                $cmd = escapeshellarg($sofficeBin)
                    .' '.escapeshellarg('-env:UserInstallation=file://'.$profileDir)
                    .' --headless --convert-to pdf --outdir '
                    .escapeshellarg($cacheDir).' '.escapeshellarg($plainSourcePath)
                    .' 2>&1';
                exec($cmd, $output, $exitCode);
                exec('rm -rf '.escapeshellarg($profileDir));

                // soffice menamai output dari basename SUMBER (plainSourcePath,
                // nama acak scratch) -- bukan $baseName milik sourceName asli.
                $sofficeOutputPath = $cacheDir.'/'.pathinfo($plainSourcePath, PATHINFO_FILENAME).'.pdf';
                if ($exitCode !== 0 || !is_file($sofficeOutputPath)) {
                    return response('Gagal membuat pratinjau dokumen', 500);
                }
                rename($sofficeOutputPath, $cachedPdfPath);
            } finally {
                @unlink($plainSourcePath);
            }
        }

        // PDF cache ini SENGAJA TIDAK dienkripsi -- murni derivat yang bisa
        // dibangun ulang kapan saja dari $sourcePath (dihapus/dibuat ulang
        // otomatis tiap sumber berubah atau file dihapus, lihat
        // SuratFileController::delete() & OnlyOfficeController::callback()),
        // bukan sumber kebenaran. file_get_contents() polos di sini (BUKAN
        // decryptedGet()) supaya itu terlihat jelas di titik pemanggilan.
        return $this->fileResponse(file_get_contents($cachedPdfPath), [
            'Content-Type' => 'application/pdf',
            // Cache-Control eksplisit -- lihat komentar sejenis di serve()
            // untuk alasan lengkapnya.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * GET /uploads/{filename} -- menyajikan berkas asli langsung,
     * Content-Type sesuai ekstensi, Cache-Control: no-store (berkas bisa
     * ditimpa di tempat oleh OnlyOffice). Mendukung ?download=1&name=...
     * untuk unduhan dengan nama kustom.
     */
    public function serve(Request $request, string $filename)
    {
        $user = $this->auth->requireFileToken($request);

        $filePath = config('suratapp.uploads_path').'/'.$filename;

        if (!is_file($filePath)) {
            return response('File not found', 404);
        }

        $this->authorizeFile($filename, $user);

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPE_BY_EXTENSION[$extension] ?? 'application/pdf';

        $headers = [
            'Content-Type' => $contentType,
            // Cache-Control eksplisit -- file ini BISA ditimpa isinya kapan
            // saja dengan NAMA yang SAMA persis (mis. hasil edit lewat
            // OnlyOffice, lihat OnlyOfficeController::callback()). Query
            // string `v=<mtime file>` yang ditambahkan di file_url (lihat
            // SuratFileService) SEHARUSNYA sudah cukup untuk cache-busting
            // biasa, tapi SENGAJA ditambah header eksplisit ini juga sebagai
            // lapisan kedua -- viewer PDF/gambar pihak ketiga bisa saja
            // punya lapisan cache SENDIRI yang tidak selalu memperhatikan
            // perubahan query string dengan benar.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        if ($request->has('download')) {
            $downloadName = $request->query('name') ? basename((string) $request->query('name')) : $filename;
            $downloadName = preg_replace('/[\r\n"]/', '', $downloadName);
            $headers['Content-Disposition'] = 'attachment; filename="'.$downloadName.'"; '
                ."filename*=UTF-8''".rawurlencode($downloadName);
        }

        $plaintext = $this->crypto->decryptedGet($filePath);

        return $this->fileResponse($plaintext, $headers);
    }

    /**
     * requireFileToken() di atas cuma memastikan token file-nya valid (user
     * mana pun yang sedang login) -- BELUM memastikan file yang diminta
     * memang lampiran surat yang berhak dilihat user itu. surat_file.file_name
     * yang menghubungkan nama file fisik ke surat_id-nya; kalau berkasnya
     * bukan lampiran surat mana pun (mis. cache pratinjau lama/yatim), tetap
     * ditolak -- tidak ada alasan berkas begitu bisa diminta lewat token file
     * yang sah.
     *
     * @throws ApiException 403
     */
    private function authorizeFile(string $fileName, User $user): void
    {
        $suratFile = SuratFile::query()->with('surat')->where('file_name', $fileName)->first();

        if (!$suratFile || !$suratFile->surat || !$suratFile->surat->bolehDiaksesOleh($user)) {
            throw new ApiException('Anda tidak berhak mengakses berkas ini', 403);
        }
    }

    /**
     * Menerima BYTE PLAINTEXT SIAP KIRIM (bukan path lagi) -- berkas asli
     * tersimpan terenkripsi, jadi tidak bisa lagi dikirim lewat
     * BinaryFileResponse Symfony (yang otomatis melayani HTTP Range/206
     * dengan seek langsung ke file di disk, mustahil untuk ciphertext yang
     * cuma bisa didekripsi utuh sekaligus). response() polos Laravel dipakai
     * sebagai gantinya -- tidak menyisipkan header `public` ke Cache-Control
     * (alasan BinaryFileResponse dulu dikonstruksi manual dengan
     * $public=false), jadi header `no-store, no-cache, must-revalidate,
     * max-age=0` eksplisit yang kita set tetap aman tanpa perlu argumen
     * public: false lagi.
     */
    private function fileResponse(string $plaintext, array $headers): Response
    {
        return response($plaintext, 200, $headers);
    }
}
