<?php

use App\Http\Controllers\Api\PimpinanController;
use Illuminate\Support\Facades\Route;

// Pimpinan / Struktur Anggota -- pimpinan_list.php,
// pimpinan_anggota_add/remove.php. Diisi oleh PimpinanController.
//
// pimpinan_list.php sengaja HANYA butuh login (bukan admin) -- dipakai
// akun Pimpinan sendiri untuk menyaring menu sidebar "Surat Masuk
// Jajaran" miliknya. CRUD-nya (add/remove) tetap admin-only.
Route::get('/pimpinan_list.php', [PimpinanController::class, 'list'])
    ->middleware('auth.token');

Route::post('/pimpinan_anggota_add.php', [PimpinanController::class, 'add'])
    ->middleware(['auth.token', 'auth.admin']);

Route::post('/pimpinan_anggota_remove.php', [PimpinanController::class, 'remove'])
    ->middleware(['auth.token', 'auth.admin']);
