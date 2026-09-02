# Administrasi (admin-only panels)

Config/management screens, all `role:admin` gated. Not listed in the top-level README feature list but real, distinct features worth their own reference.

## User Management
- `app/Livewire/UserManagement.php` (`/pengguna`) — account CRUD.
- `app/Livewire/HakAkses.php` (`/hak-akses`) — per-account switches for "boleh input Surat Masuk" / "boleh input Surat Keluar".
- **Pattern to know**: both of these (and `StrukturAnggota` below) deliberately **duplicate** business logic line-by-line from their API-controller counterparts (`app/Http/Controllers/Api/UserController.php`, `PimpinanController.php`) rather than calling the controller via HTTP — because the Livewire component runs in a web session, not through the bearer-token API. **If you fix a bug in one, check the other for the same bug** (validation order, uniqueness checks, when `token`/`token_expires_at` get cleared).
- Clearing `token`/`token_expires_at` on a user forces logout of their *API/Flutter* session only — has no effect on any web session (separate mechanism, see `EnsureSingleWebSession` in [kontrol-akses.md](kontrol-akses.md)).

## Struktur Anggota
- `app/Livewire/StrukturAnggota.php` (`/struktur-anggota`) — manages each `role='pimpinan'` account's "jajaran" (subordinates list), via `app/Models/PimpinanAnggota.php`.
- **Not an authorization boundary** — purely filters what shows in a Pimpinan's "Surat Masuk Jajaran" sidebar menu. Don't confuse with `Surat::bolehDiaksesOleh()` (see [surat-masuk.md](surat-masuk.md)).

## Manajemen Bag
- `app/Livewire/BagManagementBase.php` + thin subclasses `BagManagementMasuk`/`BagManagementKeluar` (`/bag/masuk`, `/bag/keluar`) — one shared component, mode determines which org structure (Surat Masuk Bag/Kabag/anggota vs Surat Keluar Bag/Kasi/Kaur) is being edited.
- Mirrors the old Flutter single-screen-with-BagMode-param architecture.
- Underlying models: `BagMasuk`, `BagKeluar`, `BagMember`, `BagKasiGrup`, `BagDisposisiAnggota`.

## Storage Monitor
- `app/Livewire/StorageMonitor.php` (`/storage-server`) — VPS disk usage (`disk_total_space()`/`disk_free_space()`, cheap & always real-time) + letter-attachment folder size breakdown (`config('suratapp.uploads_path')`, recursive — can be slow with many files, so **cached 5 minutes**) + DB size (via `information_schema`, cheap).
- New feature, no equivalent in the old Flutter/PHP project.

## Laporan Gangguan
- Replaces the old floating **WhatsApp "Kontak Admin"** button (`resources/views/components/kontak-admin.blade.php` + `KONTAK_ADMIN_NAMA`/`KONTAK_ADMIN_WA` env — all removed). Now the help channel stays in-app and is visible to every admin.
- **Reporter widget**: `app/Livewire/LaporanGangguanWidget.php` (`<livewire:laporan-gangguan-widget />`), rendered in `layouts/app.blade.php` **only** — logged-in users. The `layouts/guest.blade.php` login page has no report channel by design (a report needs an identity). Floating button bottom-right; popover form = kategori (`bug`/`kendala`/`saran`, from `LaporanGangguan::KATEGORI`) + free-text `pesan` (≤1000).
- On submit: `pelapor_nama`/`pelapor_username` are **re-derived from `Auth::user()` server-side** (same invariant as disposisi rows, see [surat-masuk.md](surat-masuk.md)); `halaman` = HTTP referer, `user_agent` captured; unknown `kategori` falls back to `kendala`. Writes an `activity_log` row (`aksi=create`).
- **Admin panel**: `app/Livewire/LaporanGangguanAdmin.php` (`/laporan-gangguan`, `role:admin`), mirrors `PengumumanAdmin`. Filter tabs Baru/Selesai/Semua (`#[Url] $filter`, default `baru`); actions `tandaiSelesai` (sets `status`, `ditangani_oleh`, `ditangani_pada`), `tandaiBaru` (clears them), `hapus`.
- **Sidebar badge**: the "Laporan Gangguan" link in the admin "Sistem" menu shows a red count of `status='baru'` rows — computed inline in `layouts/app.blade.php` (`$jmlLaporanBaru`), same badge markup as the Surat Masuk/Keluar menu items.
- Model `app/Models/LaporanGangguan.php` / table `laporan_gangguan` — same lightweight pattern as `pengumuman` (no `updated_at`). No API/Flutter endpoint (the old widget was web-only too).
