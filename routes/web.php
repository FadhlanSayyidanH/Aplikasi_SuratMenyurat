<?php

use App\Livewire\Auth\Login;
use App\Livewire\Landing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Tampilan web (Livewire) -- migrasi dari lib/ (aplikasi Flutter) pada
// proyek lama. Dipecah per modul (require di bawah) dengan pola yang sama
// seperti routes/api_*.php, supaya tiap modul bisa dikerjakan independen
// tanpa saling tumpang tindih pada satu file.

Route::get('/', Landing::class)->name('landing');

Route::get('/login', Login::class)->name('login')->middleware('guest');

Route::post('/logout', function () {
    $user = Auth::user();
    if ($user) {
        \App\Services\ActivityLogger::log(request(), $user->username, $user->nama, 'logout');
        // Bersihkan penanda "sesi aktif" -- lihat App\Http\Middleware\
        // EnsureSingleWebSession. Bukan wajib (login berikutnya selalu
        // menimpa nilainya), tapi mencegah nilai basi menempel di akun
        // setelah logout bersih.
        $user->forceFill(['web_session_id' => null])->save();
    }
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('landing');
})->name('logout')->middleware('auth');

require __DIR__.'/web_dashboard.php';
require __DIR__.'/web_surat_form.php';
require __DIR__.'/web_surat_review.php';
require __DIR__.'/web_progress.php';
require __DIR__.'/web_users.php';
require __DIR__.'/web_bag.php';
require __DIR__.'/web_activitylog.php';
require __DIR__.'/web_annotate.php';
require __DIR__.'/web_onlyoffice.php';
require __DIR__.'/web_riwayat.php';
require __DIR__.'/web_storage.php';
require __DIR__.'/web_webpush.php';
require __DIR__.'/web_pengumuman.php';
