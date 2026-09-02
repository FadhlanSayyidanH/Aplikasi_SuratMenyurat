# Surat Ditajenad (CARAKA-BINUM)

[English](README.md) · **Bahasa Indonesia**

Backend Laravel untuk **Surat Menyurat Ditajenad TNI AD (CARAKA-BINUM)** —
sistem administrasi surat masuk & keluar untuk Direktorat Ajudan Jenderal
TNI Angkatan Darat, Subditbinum. Menangani alur persetujuan berjenjang,
disposisi, dan pengarsipan lampiran, dengan web app (Livewire) dan REST API
(untuk aplikasi Flutter pendamping) yang berjalan di atas basis data yang sama.

## Fitur utama

- **Surat Masuk** — input oleh Turmin, persetujuan Kasubdit, lalu diteruskan
  ke Bag tujuan. Bag yang punya akun Kabag terdaftar akan berhenti dulu di
  Kabag (isi disposisi + pilih anggota mana yang dituju); Bag tanpa Kabag
  otomatis diteruskan ke seluruh anggotanya. Kasubdit juga bisa menambahkan
  **Tembusan Manual** — akun bebas di luar struktur Bag yang ikut jadi
  tujuan disposisi dan bisa mengisi responsnya sendiri. Penerima disposisi
  juga bisa **meneruskan ke sesama anggota Bag yang sama**
  ("teruskan ke rekan sebag").
- **Surat Keluar** — rantai persetujuan otomatis mengikuti struktur
  Bag/Kasi/Kaur, atau rantai manual (bebas pilih siapa saja yang memproses,
  bebas urutan).
- **Lampiran & OnlyOffice** — pratinjau/edit/anotasi dokumen (PDF, DOCX,
  PPTX, XLSX) langsung di browser lewat OnlyOffice Document Server, dengan
  opsi enkripsi-at-rest untuk file yang diunggah.
- **Notifikasi** — Web Push ke notification bar HP/desktop (tetap masuk
  meski browser tertutup) dan pengumuman broadcast dari admin.
- **Laporan Gangguan** — tombol mengambang di setiap halaman (user login)
  untuk melaporkan bug/kendala/saran langsung di aplikasi; admin meninjau &
  menandai selesai di menu "Laporan Gangguan".
- **Kontrol akses** — autentikasi berbasis token untuk API (dipakai aplikasi
  Flutter) dan sesi web biasa untuk Livewire, dengan peran admin/pimpinan/
  Kasubdit/Kabag/Turmin/user serta pembatasan satu sesi aktif per akun.
- **Log aktivitas** — jejak audit untuk setiap aksi penting pada surat.

## Struktur

- `app/Models/` — Eloquent untuk seluruh entitas (Surat, SuratApproval,
  SuratDisposisi, SuratFile, BagMasuk/BagKeluar, User, dll).
- `app/Services/` — logika inti (`BagService` untuk struktur organisasi &
  routing disposisi, `SuratFileService`, `TokenAuthService`,
  `FileEncryptionService`, `ActivityLogger`, `OnlyOfficeJwtService`).
- `app/Http/Controllers/Api/` — REST API untuk aplikasi Flutter, satu
  controller per modul.
- `app/Livewire/` — web app (form surat, review/approval, manajemen Bag,
  hak akses, dsb).
- `app/Http/Middleware/` — `CorsMiddleware`, `TokenAuthMiddleware`
  (`auth.token`), `AdminOnlyMiddleware` (`auth.admin`) untuk autentikasi
  bearer-token stateless di sisi API.
- `config/suratapp.php` — seluruh konfigurasi khusus aplikasi (TTL token,
  kredensial OnlyOffice, instruksi disposisi valid, dsb), semuanya lewat
  `.env`.
- `docs/features/` — dokumentasi ringkas per-fitur; baca yang relevan
  daripada menelusuri ulang sebuah subsistem dari kode.

## Menjalankan secara lokal

Butuh PHP 8.2+, ekstensi `mysqli`/`pdo_mysql`/`gd`/`fileinfo`,
MySQL/MariaDB, dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi `.env` sesuai kebutuhan (koneksi database, `ONLYOFFICE_JWT_SECRET` kalau
mau memakai integrasi OnlyOffice, VAPID key untuk Web Push lewat
`php artisan webpush:vapid`).

```bash
php artisan migrate --seed   # buat skema + 1 akun admin awal (admin/admin123)
php artisan serve --port=8800
```

Ganti password akun `admin` setelah login pertama.
