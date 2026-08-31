<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Keanggotaan user dalam bag_keluar (rantai approval Surat Keluar).
// kasi_grup_id NULL = anggota "datar" langsung di bawah Bag ini (Kabag/
// Kabagtu/Turmin/Kasubditbinum); diisi kalau baris ini seorang Kaur yang
// tercatat di bawah satu Kasi lewat bag_kasi_grup. bag_id tetap diisi juga
// untuk baris bersarang (didenormalisasi dari bag_kasi_grup.bag_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bag_id')->constrained('bag_keluar')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('urutan')->default(1);
            $table->foreignId('kasi_grup_id')->nullable()->constrained('bag_kasi_grup')->cascadeOnDelete();
            $table->unique(['bag_id', 'user_id'], 'uniq_bag_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_member');
    }
};
