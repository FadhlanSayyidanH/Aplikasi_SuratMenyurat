<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penerima disposisi Surat Masuk satu Bag -- SEPENUHNYA LEPAS dari
// bag_member/bag_kasi_grup (rantai approval Surat Keluar). Satu akun boleh
// muncul di kedua daftar independen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_disposisi_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bag_id')->constrained('bag_masuk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('urutan')->default(1);
            $table->unique(['bag_id', 'user_id'], 'uniq_bag_disposisi_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_disposisi_anggota');
    }
};
