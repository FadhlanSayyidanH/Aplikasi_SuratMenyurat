<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Seluruh endpoint API sistem Surat Menyurat Ditajenad -- path SENGAJA
// dibuat sama persis dengan backend/public/*.php pada proyek PHP lama
// (termasuk akhiran .php), supaya aplikasi Flutter yang sudah ada bisa
// dipakai tanpa perubahan apa pun selain mengganti base URL backend.
//
// Dipecah per modul (require terpisah di bawah) supaya tiap modul bisa
// dikerjakan independen tanpa saling tumpang tindih pada satu file.

// --- Autentikasi ---------------------------------------------------------
Route::post('/auth_login.php', [AuthController::class, 'login']);
Route::post('/auth_logout.php', [AuthController::class, 'logout'])->middleware('auth.token');
Route::post('/auth_change_password.php', [AuthController::class, 'changePassword'])->middleware('auth.token');

require __DIR__.'/api_users.php';
require __DIR__.'/api_bag.php';
require __DIR__.'/api_pimpinan.php';
require __DIR__.'/api_activitylog.php';
require __DIR__.'/api_surat.php';
require __DIR__.'/api_suratfile.php';
require __DIR__.'/api_files.php';
