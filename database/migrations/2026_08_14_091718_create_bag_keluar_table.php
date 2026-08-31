<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Jalur Surat Keluar. Sengaja TABEL TERPISAH dari bag_masuk (bukan satu
// tabel `bag` dengan flag untuk_keluar) -- lihat backend/config/bag.php
// (proyek PHP lama) untuk alasan lengkapnya: supaya CRUD di panel admin
// "Bag Surat Keluar" tidak pernah menyentuh Bag di panel "Bag Surat Masuk".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique('uniq_nama');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_keluar');
    }
};
