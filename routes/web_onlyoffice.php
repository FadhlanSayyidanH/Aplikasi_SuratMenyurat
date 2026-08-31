<?php

use App\Livewire\SuratFileEditor;
use Illuminate\Support\Facades\Route;

// Halaman pembuka editor OnlyOffice (docx/pptx/xlsx) & viewer/editor PDF --
// setara backend/public/onlyoffice_editor.html pada proyek lama. Dibuka
// dari Surat Review (routes/web_surat_review.php) saat lampiran BUKAN foto
// (lihat SuratReview::editDocumentAndOpen()) -- {suratFile} resolve
// otomatis lewat implicit route-model binding karena
// SuratFileEditor::mount() type-hint SuratFile $suratFile. Komponen
// memanggil GET /surat_edit_docx.php atau /surat_edit_pdf.php (endpoint API
// yang sudah ada, App\Http\Controllers\Api\OnlyOfficeController) lewat
// fetch() di JS sisi klien untuk ambil konfigurasi editor bertanda-tangan
// JWT, lalu merender DocsAPI.DocEditor (atau <iframe> PDF native kalau
// hanya berhak lihat).
Route::get('/lampiran/{suratFile}/editor', SuratFileEditor::class)->name('surat-file.editor')->middleware('auth');
