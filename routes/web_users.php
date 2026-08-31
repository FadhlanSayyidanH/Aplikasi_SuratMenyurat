<?php

use App\Livewire\HakAkses;
use App\Livewire\StrukturAnggota;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;

// Manajemen Pengguna, Hak Akses, Struktur Anggota (admin saja) -- migrasi
// dari lib/screens/user_management_screen.dart, hak_akses_screen.dart,
// struktur_anggota_screen.dart.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/pengguna', UserManagement::class)->name('users.index');
    Route::get('/hak-akses', HakAkses::class)->name('hak-akses.index');
    Route::get('/struktur-anggota', StrukturAnggota::class)->name('struktur-anggota.index');
});
