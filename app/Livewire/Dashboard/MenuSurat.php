<?php

namespace App\Livewire\Dashboard;

/**
 * Migrasi dari enum privat `_MenuSurat` di lib/screens/dashboard_screen.dart
 * (Flutter). Nilai string tiap kasus dipakai sebagai slug query string
 * `?menu=` di URL web (lihat App\Livewire\Dashboard, properti #[Url] $menu)
 * -- Flutter tidak punya konsep URL sama sekali (satu StatefulWidget dengan
 * state `_menu` di memori), jadi slug ini murni pilihan baru untuk web,
 * dibuat readable & stabil supaya bookmark/tombol back browser bekerja.
 */
enum MenuSurat: string
{
    // Surat Masuk (akun Administrator): gabungan SELURUH surat masuk apa pun
    // bagian tujuan disposisinya, satu folder saja.
    case ArsipMasuk = 'arsip-masuk';

    // Surat Masuk Jajaran (akun Pimpinan): SATU folder DINAMIS per anggota
    // jajaran (nama anggotanya sendiri disimpan terpisah di properti $anggota
    // milik komponen, lihat komentar yang sama di dashboard_screen.dart --
    // enum ini cuma bisa punya nilai tetap).
    case JajaranAnggota = 'jajaran-anggota';

    // Surat Masuk (akun pejabat): antrian belum direspon milik pejabat itu
    // sendiri, dan arsip surat yang sudah diisi disposisinya.
    case DashboardMasuk = 'dashboard-masuk';
    case SuratTerespon = 'surat-terespon';

    // Dashboard gabungan (akun Administrator) -- slug 'ringkasan' dipilih
    // (bukan 'dashboard') supaya tidak rancu dengan path route /dashboard
    // itu sendiri saat muncul di query string (/dashboard?menu=ringkasan).
    case Dashboard = 'ringkasan';

    case DashboardKeluar = 'dashboard-keluar';

    // Arsip & Progres Surat Keluar: gabungan bekas dua menu terpisah "Arsip
    // Surat Keluar" (surat yang sudah selesai -- disetujui/ditolak) dan
    // "Progress Surat Keluar" (surat yang masih berjalan, tidak dibatasi
    // giliran/tahap milik sendiri) -- lihat App\Livewire\Dashboard::daftar().
    case ArsipProgresKeluar = 'arsip-progres-keluar';

    public function isMasuk(): bool
    {
        return match ($this) {
            self::ArsipMasuk, self::JajaranAnggota, self::DashboardMasuk, self::SuratTerespon => true,
            default => false,
        };
    }

    public function jenisFilter(): string
    {
        return $this->isMasuk() ? 'masuk' : 'keluar';
    }

    /** Null untuk kasus yang statusnya difilter khusus di logika muat data (bukan satu status tetap). */
    public function status(): ?string
    {
        return match ($this) {
            self::Dashboard, self::DashboardKeluar => 'menunggu',
            default => null,
        };
    }

    /** [JajaranAnggota] SENGAJA tidak ditangani di sini (nama anggotanya dinamis) -- lihat App\Livewire\Dashboard::judulMenuAktif(). */
    public function judul(): string
    {
        return match ($this) {
            self::ArsipMasuk, self::SuratTerespon => 'Arsip Surat Masuk',
            self::JajaranAnggota => '',
            self::Dashboard, self::DashboardMasuk => 'Belum Direspon',
            self::DashboardKeluar => 'Belum Diproses',
            self::ArsipProgresKeluar => 'Arsip & Progres Surat Keluar',
        };
    }

    public function labelSidebar(): string
    {
        return match ($this) {
            self::ArsipMasuk, self::SuratTerespon => 'Arsip Surat Masuk',
            self::JajaranAnggota => '',
            self::Dashboard => 'Dashboard Surat',
            self::DashboardMasuk => 'Dashboard Surat Masuk',
            self::DashboardKeluar => 'Dashboard Surat Keluar',
            self::ArsipProgresKeluar => 'Arsip & Progres Surat Keluar',
        };
    }

    public function pesanKosong(): string
    {
        return match ($this) {
            self::ArsipMasuk => 'Belum ada surat masuk.',
            self::JajaranAnggota => 'Belum ada surat masuk untuk anggota ini.',
            self::SuratTerespon => 'Belum ada surat yang Anda respon.',
            self::Dashboard => 'Tidak ada surat yang menunggu diproses/direspon.',
            self::DashboardMasuk => 'Tidak ada surat masuk yang menunggu direspon.',
            self::DashboardKeluar => 'Tidak ada surat keluar yang menunggu diproses.',
            self::ArsipProgresKeluar => 'Belum ada surat keluar yang sedang diproses maupun yang sudah selesai.',
        };
    }
}
