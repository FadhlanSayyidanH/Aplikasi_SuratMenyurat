<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

// Dashboard (shell utama setelah login) -- migrasi dari
// lib/screens/dashboard_screen.dart + lib/screens/surat_kategori_browser.dart.
// Menu aktif diekspos lewat query string ?menu= (lihat properti #[Url]
// $menu di App\Livewire\Dashboard) supaya link/bookmark/tombol back
// browser bekerja seperti halaman web biasa, walau state-nya tetap satu
// komponen Livewire (bukan route terpisah per folder), sama seperti
// arsitektur satu-StatefulWidget di Flutter aslinya.
Route::get('/dashboard', Dashboard::class)->name('dashboard')->middleware('auth');
