<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seluruh model otorisasi rantai approval (ambilAlih(), approvalLompat(),
 * approvalMundur(), konfirmasiKaur(), dst di App\Livewire\SuratReview &
 * Api\SuratController) mencocokkan "ini akun siapa" lewat users.nama (STRING),
 * bukan foreign key -- keunikannya SELAMA INI cuma dijaga di level aplikasi
 * (App\Livewire\UserManagement::simpan()/updatedUsername(), scoped
 * `where('role', '!=', 'admin')`). Tanpa constraint di DB, insert langsung/
 * import/race condition antar dua createUser() bisa menghasilkan dua akun
 * dengan nama sama yang jadi "bisa saling gantikan" di tahap approval manapun.
 *
 * Ditegakkan lewat KOLOM VIRTUAL (bukan unique index langsung di `nama`) --
 * nilainya nama itu sendiri untuk role selain admin, NULL untuk admin, supaya
 * PERSIS meniru scope pengecekan aplikasi di atas (admin sengaja dikecualikan
 * -- baris NULL tidak dianggap bentrok oleh unique index, standar di MySQL).
 * Hanya berlaku untuk MySQL (produksi) -- lihat migrasi
 * 2026_08_18_061327_add_rejected_to_users_profile_status_enum.php di
 * Dosier_Elektronik untuk kasus serupa yang PERNAH bikin seluruh test suite
 * gagal karena sintaks MySQL-only jalan di SQLite (dipakai phpunit.xml testing
 * di app ini juga) -- driver lain SENGAJA dilewati, bukan lupa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_kunci_unik', 100)
                ->virtualAs("CASE WHEN `role` != 'admin' THEN `nama` ELSE NULL END")
                ->nullable()
                ->after('nama');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('nama_kunci_unik');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nama_kunci_unik']);
            $table->dropColumn('nama_kunci_unik');
        });
    }
};
