<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'jenis', 'nomor_surat', 'tanggal', 'tanggal_input_sistem', 'perihal', 'nama_pengaju',
    'klasifikasi', 'disposisi', 'kabag_dituju', 'asal_tujuan', 'sifat', 'keterangan',
    'status', 'catatan_proses', 'diproses_oleh', 'diproses_at', 'konfirmasi_kaur_at',
    'kasubdit_gerbang_user_id',
])]
class Surat extends Model
{
    protected $table = 'surat';

    public $timestamps = false;

    /** Nilai dropdown khusus "Lainnya (isi manual)" -- lihat _klasifikasiLainnyaSentinel di Flutter. */
    public const KLASIFIKASI_LAINNYA = '__lainnya__';

    /**
     * Urutan sama seperti enum KlasifikasiSurat di lib/models/surat.dart.
     * Dipakai bersama oleh App\Livewire\Surat\SuratForm (buat surat baru)
     * dan App\Livewire\SuratReview (edit data awal) supaya daftarnya selalu
     * cocok satu sama lain.
     */
    public const KLASIFIKASI_OPTIONS = [
        'Surat Keputusan', 'Surat Telegram', 'Surat Edaran', 'Surat Perintah',
        'Surat Biasa', 'Surat Nota Dinas', 'Surat Pengantar', 'Surat Undangan', 'Surat Rahasia',
    ];

    protected function casts(): array
    {
        // Format eksplisit (bukan default ISO8601 Carbon) supaya bentuk JSON
        // persis sama seperti mysqli lama: tanggal "Y-m-d", datetime
        // "Y-m-d H:i:s" -- Flutter app mem-parse string ini apa adanya.
        return [
            'tanggal' => 'date:Y-m-d',
            'tanggal_input_sistem' => 'date:Y-m-d',
            'diproses_at' => 'datetime:Y-m-d H:i:s',
            'konfirmasi_kaur_at' => 'datetime:Y-m-d H:i:s',
            'created_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function files()
    {
        return $this->hasMany(SuratFile::class, 'surat_id')->orderBy('urutan')->orderBy('id');
    }

    public function disposisi()
    {
        return $this->hasMany(SuratDisposisi::class, 'surat_id')->orderBy('id');
    }

    public function approval()
    {
        return $this->hasMany(SuratApproval::class, 'surat_id')->orderBy('urutan');
    }

    public function kasubditGerbang()
    {
        return $this->belongsTo(User::class, 'kasubdit_gerbang_user_id');
    }

    /**
     * Tanggal yang dipakai untuk mengurutkan & menampilkan ringkasan surat.
     * Surat Masuk: tanggal_input_sistem (kapan surat itu MASUK ke sistem/
     * diinput Turmin) -- BUKAN $this->tanggal (tanggal surat itu sendiri
     * DITERBITKAN oleh pengirim, bisa jauh lebih lama dari saat baru
     * diterima/dicatat di sini). Surat Keluar tidak punya
     * tanggal_input_sistem (selalu null), jadi selalu pakai tanggal biasa.
     * Sumber tunggal -- dipakai App\Livewire\Dashboard (urutan daftar) dan
     * App\Livewire\SuratReview (tanggal ringkas di header) supaya konsisten.
     */
    public function tanggalUrut(): \Illuminate\Support\Carbon
    {
        return $this->jenis === 'masuk' ? ($this->tanggal_input_sistem ?? $this->tanggal) : $this->tanggal;
    }

    /**
     * true kalau $user berhak melihat surat ini -- admin/pimpinan bebas,
     * selain itu harus benar-benar terlibat (tujuan disposisi, salah satu
     * tahap approval, atau pernah memprosesnya). Sumber tunggal aturan ini
     * -- dipakai SuratReview::authorizeAccess(), OnlyOfficeController, dan
     * FileServeController (akses lampiran mentah) supaya ketiganya selalu
     * konsisten satu sama lain.
     */
    public function bolehDiaksesOleh(User $user): bool
    {
        if (in_array($user->role, ['admin', 'pimpinan'], true)) {
            return true;
        }

        $nama = $user->nama;

        return $this->disposisi()->where('role', $nama)->exists()
            || $this->approval()->where('role', $nama)->exists()
            || $this->approval()->where('diproses_oleh', $nama)->exists();
    }
}
