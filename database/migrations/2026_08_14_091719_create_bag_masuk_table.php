<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bag tujuan/gerbang Surat Masuk. turmin_user_id/kasubdit_user_id: akun
// yang menjabat gerbang 2 tahap Surat Masuk KHUSUS Bag ini -- satu akun
// boleh jadi Turmin/Kasubdit di lebih dari satu Bag (tidak ada constraint
// unik). ON DELETE SET NULL karena ini slot "siapa sedang menjabat".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_masuk', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique('uniq_bag_masuk_nama');
            $table->foreignId('turmin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kasubdit_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_masuk');
    }
};
