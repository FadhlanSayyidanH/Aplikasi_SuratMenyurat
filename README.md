# Surat Ditajenad — Backend Laravel

Konversi penuh backend PHP murni (`../surat_ditajenad/backend`) ke Laravel 13,
untuk aplikasi Flutter **Surat Menyurat Ditajenad TNI AD (CARAKA-BINUM)**.
Backend lama tetap ada sebagai referensi read-only, tidak diubah sama sekali.

## Kompatibilitas API

Setiap endpoint sengaja dibuat dengan **path yang persis sama** dengan file
PHP lama (termasuk akhiran `.php`), sehingga aplikasi Flutter yang sudah ada
bisa langsung dipakai dengan proyek ini hanya dengan mengganti base URL
backend — tidak perlu mengubah kode Dart sama sekali. Bentuk request/response
JSON, urutan validasi, kode status HTTP, dan pesan error juga direplikasi
1:1 dari sumber PHP-nya.

Lihat `routes/api.php` (dan file `routes/api_*.php` per modul) untuk katalog
lengkap endpoint, dan `backend/DOCUMENTATION.md` di proyek lama untuk konteks
bisnis penuh (state machine surat, model peran, dsb — meski beberapa
detail di sana sudah tidak akurat dibanding source code aktualnya, terutama
soal struktur `bag_keluar`/`bag_masuk`).

## Struktur

- `app/Models/` — Eloquent 1:1 dari `backend/schema.sql`.
- `app/Services/` — migrasi helper `backend/config/*.php` (`BagService`,
  `SuratFileService`, `TokenAuthService`, `ActivityLogger`,
  `BaseUrlResolver`, `OnlyOfficeJwtService`).
- `app/Http/Controllers/Api/` — satu controller per modul lama.
- `app/Http/Middleware/` — `CorsMiddleware`, `TokenAuthMiddleware`
  (alias `auth.token`), `AdminOnlyMiddleware` (alias `auth.admin`) — autentikasi
  bearer-token stateless (bukan session Laravel), meniru `require_auth()`/
  `require_admin()` lama.
- `app/Exceptions/ApiException.php` — error terkontrol, dirender seragam
  sebagai `{"error": "..."}` lewat `bootstrap/app.php`.
- `config/suratapp.php` — semua konstanta yang dulu ada di `backend/config/*.php`
  (TTL token, kredensial OnlyOffice, whitelist MIME, dsb), sekarang lewat `.env`.

## Menjalankan secara lokal

Butuh PHP 8.2+, ekstensi `mysqli`/`pdo_mysql`/`gd`/`fileinfo`, MySQL/MariaDB,
dan Composer (`~/.local/bin/composer` di sandbox dev ini kalau belum ada
secara global).

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi `.env`: `DB_*` (default sudah cocok untuk MySQL lewat LAMPP —
`DB_SOCKET=/opt/lampp/var/mysql/mysql.sock`, database `surat_ditajenad_laravel`),
`ONLYOFFICE_JWT_SECRET` (samakan dengan `backend/config/onlyoffice.php` kalau
mau tetap kompatibel dengan Document Server yang sama).

```bash
php artisan migrate --seed   # buat skema + 1 akun admin awal (admin/admin123)
php artisan serve --port=8800
```

Ganti password `admin` setelah login pertama lewat `auth_change_password.php`.

## Yang BELUM ikut dipindahkan dari proyek lama

- **Data & lampiran lama** — ini proyek/skema baru (`surat_ditajenad_laravel`),
  bukan migrasi data dari database `surat_ditajenad` yang sudah berjalan.
  Kalau perlu memindahkan data produksi, tulis skrip migrasi data terpisah
  (skema tabel sudah identik, jadi ini murni `INSERT ... SELECT` antar
  database/koneksi, plus salin `backend/storage/uploads/` ke
  `storage/app/uploads/` di proyek ini).
- **Deployment** (`backend/deploy/` — provisioning Nginx/PHP-FPM/MariaDB/
  Docker OnlyOffice) belum direplikasi untuk proyek Laravel ini.
- Frontend Flutter (`lib/`) tidak disentuh — tetap di proyek lama, cukup ganti
  base URL API-nya (`lib/services/api_config.dart`) ke backend Laravel ini
  kalau ingin dipakai bersama.
