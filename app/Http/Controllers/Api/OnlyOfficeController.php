<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\SuratApproval;
use App\Models\SuratFile;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FileEncryptionService;
use App\Services\OnlyOfficeJwtService;
use App\Services\TokenAuthService;
use Illuminate\Http\Request;

/**
 * Migrasi dari backend/public/surat_edit_docx.php, surat_edit_pdf.php &
 * onlyoffice_callback.php (proyek PHP lama) -- integrasi editor OnlyOffice
 * untuk lampiran surat (docx/pptx/xlsx untuk Surat Keluar, pdf untuk kedua
 * jenis surat).
 */
class OnlyOfficeController extends Controller
{
    /** documentType OnlyOffice per ekstensi -- dipakai dua kali: validasi & config. */
    private const DOCUMENT_TYPE = [
        'docx' => 'word',
        'pptx' => 'slide',
        'xlsx' => 'cell',
    ];

    public function __construct(
        private TokenAuthService $auth,
        private OnlyOfficeJwtService $jwt,
        private FileEncryptionService $crypto,
    ) {}

    /**
     * GET /surat_edit_docx.php?file_id=... -- config editor untuk SATU file
     * .docx/.pptx/.xlsx lampiran Surat Keluar. Migrasi dari surat_edit_docx.php.
     */
    public function editDocx(Request $request)
    {
        $authUser = $request->user();

        $fileId = $this->intParam($request, 'file_id');

        $file = SuratFile::query()->with('surat')->find($fileId);
        if (!$file || !$file->surat) {
            throw new ApiException('File tidak ditemukan', 404);
        }
        $surat = $file->surat;

        $ekstensi = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));

        // Catatan: tidak ada pengecualian blanket untuk role='turmin' di sini
        // -- endpoint ini cuma pernah dipakai untuk Surat Keluar, dan pada
        // Surat Keluar "Turmin Subditbinum" adalah salah satu tahap approval
        // manual biasa yang boleh mengedit dokumen kalau sedang gilirannya --
        // itu sudah dicek lewat $bolehEdit di bawah, sama seperti pejabat
        // tahap lain.
        if ($surat->jenis !== 'keluar' || !array_key_exists($ekstensi, self::DOCUMENT_TYPE)) {
            throw new ApiException('File ini bukan dokumen DOCX/PPTX/XLSX yang bisa diedit', 400);
        }

        // Dokumen yang sudah disetujui SEPENUHNYA final -- tidak boleh
        // diedit lagi lewat OnlyOffice oleh siapa pun. Ditolak (perlu
        // revisi) TETAP boleh diedit -- itu alur normal sebelum "Ajukan
        // Ulang".
        if ($surat->status === 'disetujui') {
            throw new ApiException('Dokumen sudah disetujui sepenuhnya, tidak bisa diedit lagi', 403);
        }

        $approvalRows = SuratApproval::query()
            ->where('surat_id', $surat->id)
            ->orderBy('urutan')
            ->get(['urutan', 'role', 'status']);

        if (!$this->bolehEditTahap($approvalRows, $authUser->nama)) {
            throw new ApiException('Anda tidak berhak mengedit dokumen ini saat ini', 403);
        }

        $filePath = config('suratapp.uploads_path').'/'.$file->file_name;
        if (!is_file($filePath)) {
            throw new ApiException('File dokumen tidak ditemukan di server', 404);
        }

        $fileToken = $this->auth->getOrCreateFileToken($authUser);

        $documentKey = 'file'.$file->id.'_'.filemtime($filePath);
        $documentUrl = $this->fileUrl($file->file_name, $fileToken);
        $callbackUrl = $this->callbackUrl($file->id, $fileToken);

        $config = [
            'document' => [
                'fileType' => $ekstensi,
                'key' => $documentKey,
                'title' => $file->file_original_name ?: $file->file_name,
                'url' => $documentUrl,
                'permissions' => [
                    'edit' => true,
                    'download' => true,
                    'print' => true,
                ],
            ],
            'documentType' => self::DOCUMENT_TYPE[$ekstensi],
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'id',
                'user' => [
                    'id' => (string) $authUser->id,
                    'name' => $authUser->nama,
                ],
            ],
        ];

        // CATATAN: sengaja TIDAK memaksa config['type'] = 'mobile' di sini --
        // lihat komentar panjang di surat_edit_docx.php lama: UI mobile
        // Community Edition cuma bisa MELIHAT, bukan mengedit.
        $config['token'] = $this->jwt->encode($config);

        return response()->json([
            'config' => $config,
            'perihal' => $surat->perihal,
        ]);
    }

    /**
     * GET /surat_edit_pdf.php?file_id=... -- config editor/viewer untuk SATU
     * file PDF (Surat Masuk maupun Surat Keluar). Migrasi dari
     * surat_edit_pdf.php.
     */
    public function editPdf(Request $request)
    {
        $authUser = $request->user();

        $fileId = $this->intParam($request, 'file_id');

        $file = SuratFile::query()->with('surat')->find($fileId);
        if (!$file || !$file->surat) {
            throw new ApiException('File tidak ditemukan', 404);
        }
        $surat = $file->surat;

        $ekstensi = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));
        if ($ekstensi !== 'pdf') {
            throw new ApiException('File ini bukan dokumen PDF', 400);
        }

        // Batas akses DASAR (bisa LIHAT sama sekali atau tidak) -- SAMA
        // untuk Surat Masuk maupun Keluar: admin/pimpinan bebas, selain itu
        // harus benar-benar TERLIBAT (tujuan disposisi ATAU salah satu tahap
        // approval). Gagal di sini = 403 TOTAL (beda dari $bolehEdit di
        // bawah, yang gagalnya tetap 200 tapi read-only).
        if (!$surat->bolehDiaksesOleh($authUser)) {
            throw new ApiException('Anda tidak berhak melihat dokumen ini', 403);
        }

        // $bolehEdit -- SENGAJA tidak jadi alasan 403 -- "Pratinjau" & "Coret
        // / Tandai" adalah SATU aksi yang sama (buka OnlyOffice). Kalau
        // bukan giliran pejabat ini / dokumen sudah final, dokumen TETAP
        // dibuka (bisa dilihat), cuma pena/highlight/bentuk-nya dinonaktifkan
        // (permissions.edit di bawah) -- bukan gagal total.
        $bolehEdit = true;
        if ($surat->jenis === 'keluar') {
            if ($surat->status === 'disetujui') {
                $bolehEdit = false;
            } else {
                $approvalRows = SuratApproval::query()
                    ->where('surat_id', $surat->id)
                    ->orderBy('urutan')
                    ->get(['urutan', 'role', 'status']);
                $bolehEdit = $this->bolehEditTahap($approvalRows, $authUser->nama);
            }
        }
        // Surat Masuk: tidak ada giliran approval yang menggilir siapa boleh
        // mengedit kapan -- $bolehEdit tetap true selama lolos batas akses
        // dasar di atas.

        $filePath = config('suratapp.uploads_path').'/'.$file->file_name;
        if (!is_file($filePath)) {
            throw new ApiException('File dokumen tidak ditemukan di server', 404);
        }

        $fileToken = $this->auth->getOrCreateFileToken($authUser);

        $documentKey = 'file'.$file->id.'_'.filemtime($filePath);
        $documentUrl = $this->fileUrl($file->file_name, $fileToken);
        $callbackUrl = $this->callbackUrl($file->id, $fileToken);

        $config = [
            'document' => [
                'fileType' => 'pdf',
                'key' => $documentKey,
                'title' => $file->file_original_name ?: $file->file_name,
                'url' => $documentUrl,
                'permissions' => [
                    'edit' => $bolehEdit,
                    'download' => true,
                    'print' => true,
                ],
            ],
            'documentType' => 'pdf',
            'editorConfig' => [
                'callbackUrl' => $callbackUrl,
                'lang' => 'id',
                'user' => [
                    'id' => (string) $authUser->id,
                    'name' => $authUser->nama,
                ],
            ],
        ];

        $config['token'] = $this->jwt->encode($config);

        return response()->json([
            'config' => $config,
            'perihal' => $surat->perihal,
        ]);
    }

    /**
     * POST /onlyoffice_callback.php -- dipanggil OLEH OnlyOffice Document
     * Server (server-to-server), bukan Flutter app. HARUS SELALU membalas
     * {"error":0} apa pun yang terjadi di dalam, supaya docserver tidak
     * retry terus -- lihat komentar panjang di onlyoffice_callback.php lama.
     * Karena itu endpoint ini SENGAJA TIDAK melempar ApiException di jalur
     * kegagalan mana pun; setiap kegagalan cukup dicatat lalu tetap
     * membalas sukses.
     */
    public function callback(Request $request)
    {
        $rawBody = $request->getContent();
        $body = json_decode($rawBody, true);

        if (!is_array($body)) {
            return $this->ok();
        }

        // Docserver menandatangani SETIAP request keluar (termasuk callback
        // ini) dengan JWT di header Authorization. Tanpa verifikasi ini,
        // siapa pun yang tahu URL callback (berisi file_id & token) bisa
        // memalsukan payload "status":2 dengan "url" ARBITRER (SSRF + menimpa
        // isi dokumen surat). Wajibkan tanda tangan valid dulu.
        $authHeader = $request->header('Authorization', '');
        $docServerJwt = null;
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $authHeader, $jwtMatches)) {
            $docServerJwt = trim($jwtMatches[1]);
        }
        if (!$docServerJwt || $this->jwt->decode($docServerJwt) === null) {
            return $this->ok();
        }

        // Debug log -- membantu menelusuri payload asli dari docserver.
        // SENGAJA ditulis SETELAH verifikasi JWT di atas (bukan di awal),
        // supaya endpoint yang tidak bisa mewajibkan login ini (docserver
        // bukan user) tidak bisa dibanjiri payload sembarang tanpa batas
        // oleh siapa pun yang tahu URL-nya. Panjang body juga dibatasi
        // supaya satu request tidak bisa membuat satu baris log raksasa.
        $this->debugLog($rawBody);

        // PENTING (perbaikan keamanan): verifikasi JWT di header di atas
        // HANYA membuktikan "ada tanda tangan valid dari SUATU permintaan
        // docserver kita" -- TIDAK terikat ke ISI $body pada request INI.
        // Docserver OnlyOffice sungguhan SELALU menyertakan JWT KEDUA di
        // dalam body itu sendiri (field "token"), yang payload-nya
        // MENCERMINKAN PERSIS field body lain (key/status/url/dst) --
        // dikonfirmasi lewat storage/app/onlyoffice_callback.log produksi.
        // Tanpa verifikasi INI, siapa pun yang login (role apa pun) bisa
        // memakai JWT valid MANAPUN yang mereka punya (mis. dari config
        // editor dokumen mereka sendiri, yang dikembalikan ke browser tiap
        // kali membuka dokumen) untuk memalsukan "status":2 dengan "url"
        // arbitrer -- server akan meng-fetch url itu (SSRF) dan menimpa isi
        // surat_file APA PUN (file_id bisa ditebak, cuma integer). Wajibkan
        // token DI DALAM BODY juga valid, lalu pakai PAYLOAD TERVERIFIKASI
        // itu (bukan $body mentah) untuk status/url -- gagal = tolak diam-
        // diam (fail closed), bukan jatuh balik ke $body yang tidak terikat
        // ke tanda tangan mana pun.
        $bodyToken = is_string($body['token'] ?? null) ? $body['token'] : null;
        $verifiedPayload = $bodyToken ? $this->jwt->decode($bodyToken) : null;
        if ($verifiedPayload === null) {
            return $this->ok();
        }

        $fileId = filter_var($request->query('file_id'), FILTER_VALIDATE_INT);
        if (!$fileId) {
            return $this->ok();
        }

        // file_token (BUKAN token sesi login) -- lihat komentar
        // optionalAuthByFileToken() & callbackUrl di surat_edit_docx.php/
        // surat_edit_pdf.php untuk alasan lengkap.
        $authUser = $this->auth->optionalAuthByFileToken($request);
        if (!$authUser) {
            return $this->ok();
        }

        $suratFile = SuratFile::query()->with('surat')->find($fileId);

        // Lapis kedua (defense-in-depth): file_token di atas cuma
        // membuktikan "user INI sedang login", BUKAN "user INI pernah
        // diberi izin edit file INI" (satu file_token dipakai bersama utk
        // SEMUA file yang dibuka user yang sama, lihat komentar
        // getOrCreateFileToken()). Tolak kalau user tidak benar-benar
        // berhak atas surat pemilik file ini -- sama seperti
        // FileServeController::authorizeFile().
        if (!$suratFile || !$suratFile->surat || !$suratFile->surat->bolehDiaksesOleh($authUser)) {
            return $this->ok();
        }

        $status = $verifiedPayload['status'] ?? null;
        $url = $verifiedPayload['url'] ?? null;

        // status 2 = MustSave (user selesai edit, dokumen siap diunduh &
        // disimpan). status 6 = Corrupted/MustForceSave -- diperlakukan sama.
        if (in_array($status, [2, 6], true) && !empty($url) && $suratFile->file_name) {
            // Lapis ketiga: URL dari payload TERVERIFIKASI di atas seharusnya
            // SELALU menunjuk ke docserver kita sendiri (host loopback
            // tempat container OnlyOffice jalan, lihat suratapp.onlyoffice_port)
            // -- tolak kalau bukan, walau tanda tangannya valid (jaga-jaga
            // kalau JWT_SECRET bersama pernah bocor/ditebak).
            $host = parse_url((string) $url, PHP_URL_HOST);
            if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
                return $this->ok();
            }

            // PENTING: URL ini menunjuk ke docserver kita sendiri lewat
            // HTTPS sertifikat self-signed di produksi -- verifikasi
            // sertifikat dimatikan SENGAJA di sini karena URL-nya sudah
            // ditandatangani JWT oleh docserver kita sendiri (bukan input
            // dari luar), jadi bukan penurunan keamanan yang berarti.
            $context = stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $newContent = @file_get_contents($url, false, $context);

            if ($newContent !== false) {
                $filePath = config('suratapp.uploads_path').'/'.$suratFile->file_name;
                $this->crypto->encryptedPut($filePath, $newContent);

                // Hapus cache pratinjau PDF lama supaya viewer
                // menampilkan versi terbaru.
                $cachedPdf = config('suratapp.preview_cache_path').'/'
                    .pathinfo($suratFile->file_name, PATHINFO_FILENAME).'.pdf';
                if (is_file($cachedPdf)) {
                    unlink($cachedPdf);
                }

                ActivityLogger::log(
                    $request,
                    null,
                    $authUser->nama,
                    'update',
                    'Dokumen surat diedit langsung lewat editor di web',
                    (int) $suratFile->surat_id,
                );
            }
        }

        return $this->ok();
    }

    /** {"error":0} -- satu-satunya bentuk balasan onlyoffice_callback.php, apa pun hasilnya. */
    private function ok()
    {
        return response()->json(['error' => 0]);
    }

    /** Migrasi 1:1 dari logika debug-log di onlyoffice_callback.php lama. */
    private function debugLog(string $rawBody): void
    {
        $logLine = date('Y-m-d H:i:s').' '.substr($rawBody, 0, 4000).PHP_EOL;
        $logPath = storage_path('app/onlyoffice_callback.log');
        if (is_file($logPath) && filesize($logPath) > 5 * 1024 * 1024) {
            // Log sudah terlalu besar (>5MB) -- mulai lagi dari kosong,
            // ini cuma bantuan debug, bukan arsip.
            file_put_contents($logPath, '');
        }
        file_put_contents($logPath, $logLine, FILE_APPEND);
    }

    private function intParam(Request $request, string $name): int
    {
        $value = filter_var($request->query($name), FILTER_VALIDATE_INT);
        if (!$value) {
            throw new ApiException("Parameter {$name} wajib diisi", 400);
        }

        return $value;
    }

    /**
     * true kalau $namaUser berhak mengedit dokumen surat yang sedang di
     * tahap approval $approvalRows saat ini -- pejabat pemegang tahap harus
     * PUNYA baris approval untuk surat ini, DAN seluruh tahap SEBELUM
     * miliknya (urutan lebih kecil) sudah 'disetujui'. Logika identik dipakai
     * surat_edit_docx.php & surat_edit_pdf.php lama (disalin persis, bukan
     * SuratFileService::bolehKelolaFileSuratKeluar() -- method itu punya
     * aturan berbeda, dipakai untuk tambah/hapus/ganti-nama file, bukan izin
     * edit konten lewat OnlyOffice).
     */
    private function bolehEditTahap(\Illuminate\Support\Collection $approvalRows, string $namaUser): bool
    {
        $tahapSaya = $approvalRows->first(fn ($row) => $row->role === $namaUser);
        if (!$tahapSaya) {
            return false;
        }

        foreach ($approvalRows as $row) {
            if ($row->urutan < $tahapSaya->urutan && $row->status !== 'disetujui') {
                return false;
            }
        }

        return true;
    }

    private function fileUrl(string $fileName, string $fileToken): string
    {
        return 'http://'.config('suratapp.onlyoffice_host_gateway').'/uploads/'.$fileName
            .'?token='.urlencode($fileToken);
    }

    private function callbackUrl(int $fileId, string $fileToken): string
    {
        return 'http://'.config('suratapp.onlyoffice_host_gateway').'/onlyoffice_callback.php?file_id='.$fileId
            .'&token='.urlencode($fileToken);
    }
}
