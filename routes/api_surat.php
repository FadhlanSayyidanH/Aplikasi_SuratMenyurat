<?php

use App\Http\Controllers\Api\SuratController;
use Illuminate\Support\Facades\Route;

// Surat -- CRUD & alur kerja (approval/disposisi). Diisi oleh SuratController.
// Path sama persis dengan backend/public/surat_*.php pada proyek lama.

Route::get('/surat_progress_public.php', [SuratController::class, 'progressPublic']);

Route::middleware('auth.token')->group(function () {
    Route::post('/surat_create.php', [SuratController::class, 'create']);
    Route::get('/surat_list.php', [SuratController::class, 'list']);
    Route::post('/surat_update_status.php', [SuratController::class, 'updateStatus']);
    Route::post('/surat_update_disposisi.php', [SuratController::class, 'updateDisposisi']);
    Route::post('/surat_approval_lompat.php', [SuratController::class, 'approvalLompat']);
    Route::post('/surat_approval_mundur.php', [SuratController::class, 'approvalMundur']);
    Route::post('/surat_konfirmasi_kaur.php', [SuratController::class, 'konfirmasiKaur']);
    Route::post('/surat_resubmit.php', [SuratController::class, 'resubmit']);
    Route::post('/surat_delete_ditolak.php', [SuratController::class, 'deleteDitolak']);
    Route::post('/surat_edit_reset_progress.php', [SuratController::class, 'editResetProgress']);
    Route::get('/surat_activity_log.php', [SuratController::class, 'activityLog']);

    // Hanya admin -- ditegakkan juga lewat pengecekan role di dalam
    // controller sendiri (meniru require_admin() lama), middleware ini
    // lapisan pertama.
    Route::post('/surat_delete.php', [SuratController::class, 'destroy'])->middleware('auth.admin');
});
