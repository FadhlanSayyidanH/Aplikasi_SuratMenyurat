<?php

use App\Livewire\BagManagementKeluar;
use App\Livewire\BagManagementMasuk;
use Illuminate\Support\Facades\Route;

// Manajemen Bag Surat Masuk & Surat Keluar (admin saja) -- migrasi dari
// lib/screens/bag_management_screen.dart (satu screen Flutter, mode
// BagMode.suratMasuk/suratKeluar menentukan seluruh state). Diimplementasi
// di sini sebagai satu kelas dasar (App\Livewire\BagManagementBase, seluruh
// logika) dengan dua subclass tipis (BagManagementMasuk/BagManagementKeluar,
// cuma menentukan mode) supaya masing-masing bisa dipasang sebagai
// komponen Livewire full-page terpisah di sini.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/bag/masuk', BagManagementMasuk::class)->name('bag.masuk');
    Route::get('/bag/keluar', BagManagementKeluar::class)->name('bag.keluar');
});
