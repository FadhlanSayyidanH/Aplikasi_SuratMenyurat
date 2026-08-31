<?php

use App\Http\Controllers\WebPushController;
use Illuminate\Support\Facades\Route;

// Simpan/hapus subscription push notification browser -- lihat toggle
// "Aktifkan notifikasi HP" di layouts.app & resources/js/app.js.
Route::middleware('auth')->group(function () {
    Route::post('/webpush/subscribe', [WebPushController::class, 'subscribe'])->name('webpush.subscribe');
    Route::post('/webpush/unsubscribe', [WebPushController::class, 'unsubscribe'])->name('webpush.unsubscribe');
});
