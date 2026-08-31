<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Grup dalam grup" -- satu Bag (jalur Surat Keluar) boleh punya lebih dari
// satu Kasi, masing-masing dengan beberapa Kaur di bawahnya (bag_member.
// kasi_grup_id). ON DELETE CASCADE ke kasi_user_id SENGAJA (bukan SET NULL)
// -- grup ini didefinisikan OLEH Kasi-nya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_kasi_grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bag_id')->constrained('bag_keluar')->cascadeOnDelete();
            $table->foreignId('kasi_user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('urutan')->default(1);
            $table->unique(['bag_id', 'kasi_user_id'], 'uniq_bag_kasi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_kasi_grup');
    }
};
