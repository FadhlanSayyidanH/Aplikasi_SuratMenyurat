<?php

use App\Livewire\SuratFileAnnotate;
use Illuminate\Support\Facades\Route;

// Editor coret-coret (markup) untuk lampiran foto -- migrasi dari
// lib/screens/image_annotate_screen.dart. Dibuka dari Surat Review
// (routes/web_surat_review.php) saat lampiran berupa foto (lihat
// SuratReview::editDocumentAndOpen()) -- {suratFile} resolve otomatis lewat
// implicit route-model binding karena SuratFileAnnotate::mount() type-hint
// SuratFile $suratFile.
Route::get('/lampiran/{suratFile}/coret', SuratFileAnnotate::class)->name('surat-file.annotate')->middleware('auth');
