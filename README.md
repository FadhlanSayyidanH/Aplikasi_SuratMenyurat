# Surat Ditajenad (CARAKA-BINUM)

**English** · [Bahasa Indonesia](README.id.md)

Laravel backend for **Surat Menyurat Ditajenad TNI AD (CARAKA-BINUM)** — an
incoming/outgoing letter administration system for the Adjutant General
Directorate of the Indonesian Army (Ditajenad), Subditbinum. It handles the
multi-stage approval flow, disposition (disposisi), and attachment archiving,
with both a web app (Livewire) and a REST API (for the companion Flutter app)
running on the same database.

## Key features

- **Incoming letters (Surat Masuk)** — entered by Turmin, approved by Kasubdit,
  then routed to the target Bag (unit). A Bag that has a registered Kabag stops
  at the Kabag first (fill in the disposition + pick which members it goes to);
  a Bag without a Kabag is forwarded to all of its members automatically.
  Kasubdit can also add **manual carbon copies (Tembusan Manual)** — arbitrary
  accounts outside the Bag structure that also become disposition targets and
  fill in their own response. Recipients can additionally **forward to fellow
  members of the same Bag** ("teruskan ke rekan sebag").
- **Outgoing letters (Surat Keluar)** — an approval chain that automatically
  follows the Bag/Kasi/Kaur structure, or a manual chain (pick anyone, in any
  order).
- **Attachments & OnlyOffice** — preview/edit/annotate documents (PDF, DOCX,
  PPTX, XLSX) directly in the browser via an OnlyOffice Document Server, with
  optional encryption-at-rest for uploaded files.
- **Notifications** — Web Push to the phone/desktop notification bar (delivered
  even when the browser is closed) plus admin broadcast announcements.
- **Issue reports (Laporan Gangguan)** — a floating button on every page (for
  logged-in users) to report bugs/problems/suggestions in-app; admins review
  and mark them resolved from the "Laporan Gangguan" menu.
- **Access control** — token-based authentication for the API (used by the
  Flutter app) and normal web sessions for Livewire, with roles
  admin/pimpinan/Kasubdit/Kabag/Turmin/user and a single-active-session limit
  per account.
- **Activity log** — an audit trail for every significant action on a letter.

## Structure

- `app/Models/` — Eloquent models for every entity (Surat, SuratApproval,
  SuratDisposisi, SuratFile, BagMasuk/BagKeluar, User, etc.).
- `app/Services/` — core logic (`BagService` for the org structure &
  disposition routing, `SuratFileService`, `TokenAuthService`,
  `FileEncryptionService`, `ActivityLogger`, `OnlyOfficeJwtService`).
- `app/Http/Controllers/Api/` — the REST API for the Flutter app, one
  controller per module.
- `app/Livewire/` — the web app (letter forms, review/approval, Bag
  management, access rights, etc.).
- `app/Http/Middleware/` — `CorsMiddleware`, `TokenAuthMiddleware`
  (`auth.token`), `AdminOnlyMiddleware` (`auth.admin`) for stateless
  bearer-token authentication on the API side.
- `config/suratapp.php` — all app-specific configuration (token TTLs,
  OnlyOffice credentials, valid disposition instructions, etc.), all via
  `.env`.
- `docs/features/` — dense per-feature reference docs; read the relevant one
  instead of re-deriving a subsystem from the code.

## Running locally

Requires PHP 8.2+, the `mysqli`/`pdo_mysql`/`gd`/`fileinfo` extensions,
MySQL/MariaDB, and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Fill in `.env` as needed (database connection, `ONLYOFFICE_JWT_SECRET` if you
want the OnlyOffice integration, VAPID keys for Web Push via
`php artisan webpush:vapid`).

```bash
php artisan migrate --seed   # build the schema + one initial admin account (admin/admin123)
php artisan serve --port=8800
```

Change the `admin` account password after the first login.
