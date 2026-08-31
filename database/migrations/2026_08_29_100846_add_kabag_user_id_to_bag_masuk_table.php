<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Akun Kabag -- gerbang BARU antara Kasubdit dan penerima disposisi
     * Bag ini. Kalau diisi, surat yang diteruskan Kasubdit ke Bag ini
     * berhenti dulu di Kabag (isi disposisi + pilih anggota mana yang
     * dituju lewat checkbox) alih-alih langsung diblast ke SELURUH
     * bag_disposisi_anggota seperti sebelumnya. Kalau NULL (Bag belum
     * diatur Kabag-nya), perilaku lama tetap berlaku -- lihat
     * App\Livewire\SuratReview & Api\SuratController@updateStatus.
     */
    public function up(): void
    {
        Schema::table('bag_masuk', function (Blueprint $table) {
            $table->foreignId('kabag_user_id')->nullable()->after('kasubdit_user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bag_masuk', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kabag_user_id');
        });
    }
};
