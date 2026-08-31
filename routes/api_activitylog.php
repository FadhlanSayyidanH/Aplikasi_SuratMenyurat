<?php

use App\Http\Controllers\Api\ActivityLogController;
use Illuminate\Support\Facades\Route;

// Log Aktivitas (admin saja) -- activity_log_list.php,
// activity_log_clear.php. Diisi oleh ActivityLogController.
Route::get('/activity_log_list.php', [ActivityLogController::class, 'list'])
    ->middleware(['auth.token', 'auth.admin']);
Route::post('/activity_log_clear.php', [ActivityLogController::class, 'clear'])
    ->middleware(['auth.token', 'auth.admin']);
