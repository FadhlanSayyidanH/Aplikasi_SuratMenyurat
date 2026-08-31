<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Struktur Anggota (panel admin): jajaran akun di bawah satu akun
// role='pimpinan', purely untuk menyaring menu sidebar "Surat Masuk
// Jajaran" -- bukan batas otorisasi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pimpinan_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pimpinan_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('anggota_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['pimpinan_id', 'anggota_id'], 'uniq_pimpinan_anggota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pimpinan_anggota');
    }
};
