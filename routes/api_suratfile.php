<?php

use App\Http\Controllers\Api\SuratFileController;
use Illuminate\Support\Facades\Route;

// File Surat -- surat_file_add/delete/rename.php,
// surat_file_annotation_get/save.php. Diisi oleh SuratFileController.

Route::post('/surat_file_add.php', [SuratFileController::class, 'add'])
    ->middleware('auth.token');

Route::post('/surat_file_delete.php', [SuratFileController::class, 'delete'])
    ->middleware('auth.token');

Route::post('/surat_file_rename.php', [SuratFileController::class, 'rename'])
    ->middleware('auth.token');

Route::get('/surat_file_annotation_get.php', [SuratFileController::class, 'annotationGet'])
    ->middleware('auth.token');

Route::post('/surat_file_annotation_save.php', [SuratFileController::class, 'annotationSave'])
    ->middleware('auth.token');
