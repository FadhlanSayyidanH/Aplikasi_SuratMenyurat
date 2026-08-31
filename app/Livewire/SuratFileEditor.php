<?php

namespace App\Livewire;

use App\Models\Surat;
use App\Models\SuratFile;
use App\Services\TokenAuthService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman pembuka editor OnlyOffice (docx/pptx/xlsx) & viewer/editor PDF --
 * setara backend/public/onlyoffice_editor.html pada proyek lama. Dibuka
 * lewat route 'surat-file.editor' (lihat routes/web_onlyoffice.php), baik
 * dari tautan "Buka" di SuratReview maupun redirect dari
 * SuratReview::editDocumentAndOpen() -- keduanya navigasi di tab yang sama.
 *
 * Komponen ini SENGAJA tidak memanggil OnlyOfficeController secara langsung
 * -- konfigurasi editor (izin, URL dokumen, JWT) diambil dari
 * GET /surat_edit_docx.php atau /surat_edit_pdf.php lewat fetch() di JS sisi
 * klien (lihat resources/views/livewire/surat-file-editor.blade.php),
 * PERSIS seperti onlyoffice_editor.html lama, karena endpoint itu SUDAH
 * jadi sumber kebenaran otorisasi (403 kalau tidak berhak) dan sudah diuji
 * -- tidak perlu (dan sebaiknya tidak) diduplikasi di sini.
 *
 * Kendala: endpoint config tsb memakai middleware 'auth.token' (bearer
 * token independen di kolom users.token, lihat TokenAuthService), BUKAN
 * sesi Laravel biasa yang dipakai halaman Livewire ini (Auth::login()
 * lewat app/Livewire/Auth/Login.php). Supaya fetch() dari halaman
 * berbasis-sesi ini bisa lolos middleware tsb, mount() di bawah
 * memastikan user yang sedang login sesi web ini juga punya token API
 * (users.token) yang masih berlaku -- kalau belum ada/sudah kedaluwarsa,
 * di-mint ulang lewat langkah yang SAMA seperti
 * App\Http\Controllers\Api\AuthController::login() (minus verifikasi
 * password, karena user sudah terautentikasi lewat middleware 'auth' pada
 * route ini). Token itu lalu disuntikkan ke view untuk dipakai sebagai
 * header Authorization: Bearer di fetch().
 */
#[Layout('layouts.app', ['title' => 'Editor Dokumen'])]
class SuratFileEditor extends Component
{
    public int $fileId;

    public string $fileDisplayName = '';

    /** 'surat_edit_docx.php' atau 'surat_edit_pdf.php' -- lihat routes/api_files.php. */
    public string $configEndpoint = '';

    /** false kalau ekstensi file bukan salah satu yang didukung editor ini. */
    public bool $supported = true;

    public string $unsupportedMessage = '';

    /** Token API (users.token) dipakai fetch() JS di view, BUKAN token sesi Livewire. */
    public string $apiToken = '';

    public int $onlyofficePort = 8801;

    public function mount(SuratFile $suratFile, TokenAuthService $auth): void
    {
        $surat = $suratFile->surat;
        abort_unless($surat, 404);
        $this->authorizeAccess($surat);

        $this->fileId = $suratFile->id;
        $this->fileDisplayName = $suratFile->file_original_name ?: $suratFile->file_name;

        $ekstensi = strtolower(pathinfo($suratFile->file_name, PATHINFO_EXTENSION));
        $isOffice = in_array($ekstensi, ['docx', 'pptx', 'xlsx'], true);
        $isPdf = $ekstensi === 'pdf';

        if (!$isOffice && !$isPdf) {
            $this->supported = false;
            $this->unsupportedMessage = 'Tipe file ini ('.strtoupper($ekstensi ?: '?')
                .') tidak didukung oleh editor dokumen. Hanya .docx, .pptx, .xlsx, dan .pdf yang bisa dibuka di sini.';

            return;
        }

        $this->configEndpoint = $isPdf ? 'surat_edit_pdf.php' : 'surat_edit_docx.php';
        // Lihat komentar 'onlyoffice_public_port' di config/suratapp.php --
        // di produksi, browser klien HARUS lewat port proxy HTTPS Nginx
        // (Document Server sendiri cuma bisa diakses dari VM ini lewat
        // 127.0.0.1, tidak diekspos ke publik).
        $this->onlyofficePort = app()->environment('production')
            ? (int) config('suratapp.onlyoffice_public_port')
            : (int) config('suratapp.onlyoffice_port');
        $this->apiToken = $this->ensureApiToken($auth);
    }

    /**
     * Cermin SuratReview::authorizeAccess()/SuratFileAnnotate::authorizeAccess()
     * -- pemeriksaan kasar di level halaman (biar tidak render placeholder
     * untuk orang yang jelas tidak terlibat sama sekali). Pemeriksaan HALUS
     * (giliran tahap approval, dst) tetap dilakukan ulang oleh
     * OnlyOfficeController lewat fetch() JS -- itulah otorisasi yang
     * sesungguhnya mengontrol apakah dokumen jadi kebuka.
     */
    private function authorizeAccess(Surat $surat): void
    {
        abort_unless($surat->bolehDiaksesOleh(Auth::user()), 403, 'Anda tidak berhak mengakses lampiran surat ini.');
    }

    /**
     * users.token (dipakai TokenAuthMiddleware) terpisah dari sesi web --
     * lihat komentar panjang di kelas ini. Perpanjang token yang masih
     * berlaku (supaya tab lain punya token API yang sama tidak langsung
     * invalid), atau mint baru kalau belum ada/sudah kedaluwarsa -- persis
     * langkah AuthController::login().
     */
    private function ensureApiToken(TokenAuthService $auth): string
    {
        $user = Auth::user();

        if ($user->token && $user->token_expires_at && $user->token_expires_at->isFuture()) {
            return $user->token;
        }

        $token = $auth->generateAuthToken();
        $user->token = $token;
        $user->token_expires_at = now()->addSeconds(config('suratapp.auth_token_ttl_seconds'));
        $user->save();

        return $token;
    }

    public function render()
    {
        return view('livewire.surat-file-editor');
    }
}
