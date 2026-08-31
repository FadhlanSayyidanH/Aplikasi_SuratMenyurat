<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Manajemen Pengguna (admin saja) -- users_list/create/update/delete.php,
// user_akses_update.php. Diisi oleh UserController.

Route::middleware(['auth.token', 'auth.admin'])->group(function () {
    Route::get('/users_list.php', [UserController::class, 'list']);
    Route::post('/users_create.php', [UserController::class, 'create']);
    Route::post('/users_update.php', [UserController::class, 'update']);
    Route::post('/users_delete.php', [UserController::class, 'delete']);
    Route::post('/user_akses_update.php', [UserController::class, 'aksesUpdate']);
});
