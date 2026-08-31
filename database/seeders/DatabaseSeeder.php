<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Akun admin awal -- setara instruksi manual "buat user admin pertama"
     * di README proyek PHP lama. Ganti passwordnya setelah login pertama.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'nama' => 'Administrator',
                'role' => 'admin',
                'boleh_input_masuk' => false,
                'boleh_input_keluar' => false,
            ],
        );
    }
}
