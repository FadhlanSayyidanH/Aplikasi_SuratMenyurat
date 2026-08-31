<?php

use App\Http\Controllers\Api\FileServeController;
use App\Http\Controllers\Api\OnlyOfficeController;
use Illuminate\Support\Facades\Route;

// File router (/uploads/...) + integrasi OnlyOffice -- surat_edit_docx.php,
// surat_edit_pdf.php, onlyoffice_callback.php. Diisi oleh FileServeController
// & OnlyOfficeController.

// --- Penyaji berkas mentah (migrasi backend/public/router.php) -----------
// Keduanya TIDAK pakai middleware auth.token -- autentikasi lewat
// `file_token` (?token=) diperiksa langsung di controller lewat
// TokenAuthService::requireFileToken(), persis seperti require_file_token()
// pada router.php lama.
Route::get('/uploads/preview/{path}', [FileServeController::class, 'preview'])
    ->where('path', '[A-Za-z0-9_.\-]+\.(?:docx|pptx|xlsx)');

Route::get('/uploads/{filename}', [FileServeController::class, 'serve'])
    ->where('filename', '[A-Za-z0-9_.\-]+');

// --- Integrasi OnlyOffice (migrasi surat_edit_docx.php, surat_edit_pdf.php,
// onlyoffice_callback.php) -------------------------------------------------
// surat_edit_docx.php/surat_edit_pdf.php dipanggil oleh Flutter app (login
// sesi biasa) -- pakai auth.token. onlyoffice_callback.php dipanggil OLEH
// OnlyOffice Document Server sendiri (server-to-server) -- otentikasi lewat
// JWT docserver + file_token di dalam controller, BUKAN auth.token.
Route::get('/surat_edit_docx.php', [OnlyOfficeController::class, 'editDocx'])->middleware('auth.token');
Route::get('/surat_edit_pdf.php', [OnlyOfficeController::class, 'editPdf'])->middleware('auth.token');
Route::post('/onlyoffice_callback.php', [OnlyOfficeController::class, 'callback']);
