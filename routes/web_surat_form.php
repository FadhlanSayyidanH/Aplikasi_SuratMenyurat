<?php

use App\Livewire\Surat\SuratForm;
use Illuminate\Support\Facades\Route;

// Wizard buat Surat Masuk/Keluar -- migrasi dari
// lib/screens/surat_form_screen.dart. Nama route 'surat.create' HARUS
// dipertahankan -- dipakai layouts/app.blade.php.
Route::get('/surat/buat', SuratForm::class)->name('surat.create')->middleware('auth');
