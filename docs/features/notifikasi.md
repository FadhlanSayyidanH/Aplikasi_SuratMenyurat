# Notifikasi (Web Push & Pengumuman)

Two independent notification mechanisms: browser/OS-level Web Push (works even with the browser closed), and an admin-broadcast announcement banner shown in-app on the dashboard.

## Web Push
- Controller: `app/Http/Controllers/WebPushController.php` (`subscribe`/`unsubscribe`, `routes/web_webpush.php`) — stores/deletes the browser's `PushSubscription` (endpoint + `p256dh`/`auth` keys) on the `User` model via `updatePushSubscription()`/`deletePushSubscription()`.
- Sending: `App\Console\Commands\KirimWebPushNotifikasi`, scheduled every minute (`routes/console.php`) — requires `* * * * * php artisan schedule:run` cron on the server.
- Client toggle: "Aktifkan Notifikasi HP" in `layouts/app` + `resources/js/app.js` (calls `pushManager.subscribe()`, posts result to `/webpush/subscribe`).
- VAPID keypair generated per-environment via `php artisan webpush:vapid` — **never copy across environments** (each VPS/deploy gets its own).
- **Origin-scoped**: a subscription is tied to the exact browser origin that created it. A domain change (e.g. VPS migration to a new domain) makes all existing subscriptions permanently inert — they self-clean via the existing `410 Gone` → auto-delete handling, but users must re-click the toggle once on the new domain. Not a bug, just how the Web Push API works.

## Pengumuman (admin broadcast)
- Model: `app/Models/Pengumuman.php` — no active/inactive flag by design: a row existing = active, admin deleting it = revoked. Simpler by design, not an oversight.
- Admin UI: `app/Livewire/PengumumanAdmin.php` (`/pengumuman`, admin-only, `routes/web_pengumuman.php`) — send/revoke.
- Display: rendered inside `app/Livewire/Dashboard.php` (banner, all roles) — NOT in `PengumumanAdmin` itself; that component only manages the CRUD side.

## Access control note
Both features are session/auth-gated, not tied to the `Surat::bolehDiaksesOleh()` disposisi/approval logic used elsewhere in the app (see [surat-masuk.md](surat-masuk.md)) — see [kontrol-akses.md](kontrol-akses.md) for the general role/session model.
