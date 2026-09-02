<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Laporan gangguan/kendala dari user -- dikirim lewat widget mengambang
 * (App\Livewire\LaporanGangguanWidget, pengganti tombol WhatsApp "Kontak
 * Admin" lama) dan ditinjau admin di App\Livewire\LaporanGangguanAdmin.
 *
 * Pola tabel sama seperti App\Models\Pengumuman: tidak ada updated_at,
 * baris ada = laporan ada. `status` ('baru'/'selesai') dipakai admin untuk
 * melacak mana yang sudah ditangani (juga sumber angka badge sidebar).
 */
class LaporanGangguan extends Model
{
    protected $table = 'laporan_gangguan';

    public $timestamps = false;

    /** kode => label tampil. Dipakai widget, panel admin, & validasi. */
    public const KATEGORI = [
        'bug' => 'Bug',
        'kendala' => 'Kendala',
        'saran' => 'Saran',
    ];

    protected $fillable = [
        'pelapor_username',
        'pelapor_nama',
        'kategori',
        'pesan',
        'halaman',
        'user_agent',
        'status',
        'ditangani_oleh',
        'ditangani_pada',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'ditangani_pada' => 'datetime',
        ];
    }
}
