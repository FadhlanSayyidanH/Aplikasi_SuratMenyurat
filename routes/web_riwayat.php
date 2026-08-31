<?php

use App\Http\Controllers\SuratRiwayatPdfController;
use Illuminate\Support\Facades\Route;

// Unduh PDF ringkasan riwayat satu surat (metadata + rantai approval +
// disposisi + log aktivitas) -- migrasi dari lib/utils/riwayat_pdf.dart
// (dulu dirender di klien Flutter via syncfusion_flutter_pdf; di web
// dirender SERVER-SIDE lewat barryvdh/laravel-dompdf, lalu di-stream
// sebagai unduhan). Lihat SuratRiwayatPdfController untuk implementasi &
// batas otorisasi (cermin SuratController::list()).
Route::get('/surat/{surat}/riwayat.pdf', SuratRiwayatPdfController::class)
    ->name('surat.riwayat-pdf')->middleware('auth');
