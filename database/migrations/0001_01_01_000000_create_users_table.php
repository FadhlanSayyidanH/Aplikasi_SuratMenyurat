<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Skema persis backend/schema.sql `users` (sistem lama surat_ditajenad) --
// role menentukan tampilan (admin/user/pimpinan/turmin); token/file_token
// dipakai autentikasi bearer-token stateless (bukan session Laravel), lihat
// app/Services/Auth/TokenAuthenticator.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('nama', 100);
            $table->enum('role', ['admin', 'user', 'pimpinan', 'turmin'])->default('user');
            $table->timestamp('created_at')->useCurrent();
            $table->char('token', 64)->nullable()->unique('uniq_token');
            $table->dateTime('token_expires_at')->nullable();
            $table->char('file_token', 64)->nullable()->unique('uniq_file_token');
            $table->dateTime('file_token_expires_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->boolean('boleh_input_masuk')->default(false);
            $table->boolean('boleh_input_keluar')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
