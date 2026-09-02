<?php

use App\Livewire\LaporanGangguanAdmin;
use Illuminate\Support\Facades\Route;

// Tinjau laporan gangguan/kendala dari user (admin saja) -- lihat
// App\Livewire\LaporanGangguanAdmin. Widget pelapornya sendiri
// (App\Livewire\LaporanGangguanWidget) dirender di layouts.app untuk
// SEMUA user ber-login, bukan di sini.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/laporan-gangguan', LaporanGangguanAdmin::class)->name('laporan-gangguan.index');
});
