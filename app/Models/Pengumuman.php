<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman admin ke SELURUH akun -- tampil di dashboard tiap user sampai
 * admin hapus (baris ada = aktif, dihapus = dicabut; tidak ada flag
 * aktif/tidak terpisah, disengaja supaya sederhana).
 */
class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    public $timestamps = false;

    protected $fillable = ['pesan', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
