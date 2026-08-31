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
     * "Terakhir dilihat" server-side per user untuk push notification --
     * padanan server-side dari properti Livewire idNotifikasiKeluarDiketahui
     * dkk di App\Livewire\Dashboard::deteksiNotifikasiBaru() (yang cuma
     * hidup selama tab browser terbuka & polling). Dipakai oleh
     * App\Console\Commands\KirimWebPushNotifikasi supaya push cuma
     * dikirim untuk surat yang BENAR-BENAR baru masuk notifikasi user itu,
     * bukan re-kirim ulang tiap command jalan (tiap menit lewat scheduler).
     */
    public function up(): void
    {
        Schema::create('push_notification_state', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->json('id_perlu_ditindak');
            $table->json('id_arsip_keluar');
            $table->json('id_masuk');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notification_state');
    }
};
