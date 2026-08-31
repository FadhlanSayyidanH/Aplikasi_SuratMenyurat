<?php

namespace App\Livewire\Dashboard;

/**
 * Migrasi dari enum `DisposisiRole` + extension `DisposisiRoleLabel`/
 * `DisposisiRoleBagian` di lib/models/surat.dart (Flutter) -- 20 label
 * pejabat TETAP yang dikenali lewat kecocokan PERSIS dengan `users.nama`
 * (bukan lewat kolom role/struktur Bag), dipakai Dashboard untuk mendeteksi
 * identitas pejabat spesifik (mis. Kabagpam Subditbinum) & bagiannya.
 *
 * Akun Bag/"grup dalam grup" nama bebas (fitur lebih baru) TIDAK cocok
 * salah satu dari label ini -- itu ditangani terpisah lewat App\Services\
 * BagService (lihat App\Livewire\Dashboard::bagianSayaDinamis()).
 */
final class DisposisiRoles
{
    public const KASUBDIT_BINUM = 'Kasubditbinum';

    public const TURMIN_SUBDITBINUM = 'Turmin Subditbinum';

    public const KABAGTU_SUBDITBINUM = 'Kabagtu Subditbinum';

    /** label => nama bagian (null kalau role ini tidak punya struktur Kabag/Kasi/Kaur). */
    public const BAGIAN = [
        'Kabagpam Subditbinum' => 'Bagpam Subditbinum',
        'Kasi Bagpam Subditbinum' => 'Bagpam Subditbinum',
        'Kaur Bagpam Subditbinum' => 'Bagpam Subditbinum',

        'Kabagpers Subditbinum' => 'Bagpers Subditbinum',
        'Kasi Bagpers Subditbinum' => 'Bagpers Subditbinum',
        'Kaur Bagpers Subditbinum' => 'Bagpers Subditbinum',

        'Kabaglog Subditbinum' => 'Baglog Subditbinum',
        'Kasi Baglog Subditbinum' => 'Baglog Subditbinum',
        'Kaur Baglog Subditbinum' => 'Baglog Subditbinum',

        'Kabagter Subditbinum' => 'Bagter Subditbinum',
        'Kasi Bagter Subditbinum' => 'Bagter Subditbinum',
        'Kaur Bagter Subditbinum' => 'Bagter Subditbinum',

        'Kabagrenproggar Subditbinum' => 'Bagrenproggar Subditbinum',
        'Kasi Bagrenproggar Subditbinum' => 'Bagrenproggar Subditbinum',
        'Kaur Bagrenproggar Subditbinum' => 'Bagrenproggar Subditbinum',

        self::KABAGTU_SUBDITBINUM => null,
        'Kabagurdal' => null,
        'Kainfolahta' => null,
        self::KASUBDIT_BINUM => null,
        self::TURMIN_SUBDITBINUM => null,
    ];

    /** true untuk kelima label Kaur (satu per jalur Surat Keluar). */
    public static function isKaur(?string $label): bool
    {
        return $label !== null && str_starts_with($label, 'Kaur ');
    }

    /** true kalau $nama cocok PERSIS salah satu dari 20 label tetap ini. */
    public static function isValidLabel(?string $nama): bool
    {
        return $nama !== null && array_key_exists($nama, self::BAGIAN);
    }

    /** Nama bagian (mis. "Bagpers Subditbinum") milik $nama, null kalau tidak punya struktur/tidak cocok. */
    public static function bagianFor(?string $nama): ?string
    {
        return $nama !== null ? (self::BAGIAN[$nama] ?? null) : null;
    }

    /**
     * Turmin & Kasubditbinum tidak pernah jadi tujuan disposisi Surat Masuk --
     * peran mereka adalah gate pemeriksaan berjenjang (tahap tetap), bukan
     * tujuan disposisi biasa.
     */
    public static function isGateRoleMasuk(?string $nama): bool
    {
        return $nama === self::TURMIN_SUBDITBINUM || $nama === self::KASUBDIT_BINUM;
    }
}
