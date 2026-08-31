<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Satu akun cuma boleh punya SATU sesi web (Livewire) aktif sekaligus --
// login baru dari perangkat/browser lain otomatis mengeluarkan sesi lama
// (persis seperti token API di AuthController::login(), yang sudah begitu
// dari awal karena kolom `users.token` cuma menyimpan SATU nilai). Lihat
// App\Livewire\Auth\Login::login() (menulis kolom ini) & App\Http\Middleware\
// EnsureSingleWebSession (membaca & menegakkannya tiap request).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('web_session_id')->nullable()->after('file_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('web_session_id');
        });
    }
};
