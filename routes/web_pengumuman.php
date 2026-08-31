<?php

use App\Livewire\PengumumanAdmin;
use Illuminate\Support\Facades\Route;

// Kirim/cabut pengumuman ke seluruh akun (admin saja) -- lihat
// App\Livewire\PengumumanAdmin. Banner-nya sendiri tampil di dashboard
// SEMUA role, dirender lewat App\Livewire\Dashboard (lihat route di
// web_dashboard.php), bukan di sini.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/pengumuman', PengumumanAdmin::class)->name('pengumuman.index');
});
