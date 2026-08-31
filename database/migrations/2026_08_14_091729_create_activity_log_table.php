<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('surat_id')->nullable();
            $table->dateTime('waktu')->useCurrent();
            $table->string('username', 50)->nullable();
            $table->string('nama', 100);
            $table->string('aksi', 32);
            $table->text('keterangan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->index('waktu', 'idx_waktu');
            $table->index('surat_id', 'idx_activity_log_surat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
