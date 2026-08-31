<?php

use App\Livewire\SuratReview;
use Illuminate\Support\Facades\Route;

// Ruang kerja tinjau/approval satu surat -- migrasi dari
// lib/screens/surat_review_screen.dart. Nama route 'surat.review' &
// parameter {surat} dipertahankan (dipakai widget bubble surat di
// dashboard dkk untuk link ke sini) -- Laravel resolve otomatis lewat
// implicit route-model binding karena SuratReview::mount() type-hint
// Surat $surat.
Route::get('/surat/{surat}', SuratReview::class)->name('surat.review')->middleware('auth');
