<?php

use App\Livewire\SuratProgress;
use Illuminate\Support\Facades\Route;

// Pelacakan progres Surat Keluar -- PUBLIK, tanpa login. Migrasi dari
// lib/screens/surat_progress_screen.dart + surat_progress_detail_screen.dart
// (detail adalah state di komponen yang sama lewat $selectedId, lihat
// App\Livewire\SuratProgress -- tidak perlu route terpisah). SENGAJA TIDAK
// diberi middleware 'auth' -- ini satu-satunya halaman di aplikasi ini yang
// memang harus bisa diakses tanpa login sama sekali.
Route::get('/progres', SuratProgress::class)->name('surat.progress');
