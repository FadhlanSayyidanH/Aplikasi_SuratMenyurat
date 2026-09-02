# Kontrol Akses

Two parallel auth systems on the same database: stateless bearer-token auth for the REST API (Flutter app), and normal Laravel session auth for the Livewire web app — plus a single-active-session-per-account restriction on the web side.

## Roles
`admin`, `pimpinan`, `Kasubdit`, `Kabag`, `Turmin`, `user` — stored on `User.role`. Fixed-position names (`Kabagtu Subditbinum`, `Turmin Subditbinum`, `Kasubditbinum`) are listed in `config/suratapp.php` (`nama_posisi_tetap`).

- **`turmin` is a dashboard input-gate role, not a recipient role.** `SidebarMenuService`/`Dashboard` treat any `role='turmin'` account as the "Turmin" gate for entering Surat Masuk, and **hard-return an empty inbox** for it (`isTurminMasuk()` → `notifikasiMasuk()`/`daftarDashboardMasuk()` return `collect()`). An account that is meant to *receive* disposisi must be `role='user'` (Pejabat). If a `turmin` account is also listed as a Bag's Penerima Disposisi it can still open the letter by direct link and fill its disposisi (access is by `nama`, see below), but it will never appear in any inbox list or badge — this is a real misconfiguration trap, not a bug. (Seen 2026-09-02: account `turminbagpers`.)

## API (token) auth
- `app/Http/Middleware/TokenAuthMiddleware.php` (`auth.token`): validates the bearer token via `App\Services\TokenAuthService::requireAuth()`, then `Auth::setUser()` for the rest of the request — stateless, no session writes, mirrors the old `require_auth()` from the pre-Laravel PHP backend.
- `App\Http\Middleware\AdminOnlyMiddleware` (`auth.admin`): admin-only gate for API routes, layered on top of `auth.token`.
- File-serving endpoints (`app/Http/Controllers/Api/FileServeController.php`, `routes/api_files.php`) deliberately do NOT use `auth.token` — they authenticate via a short-lived `file_token` query param checked directly in the controller via `TokenAuthService::requireFileToken()`.
- Token TTL: `AUTH_TOKEN_TTL_SECONDS` (default 12h), file token TTL: `FILE_TOKEN_TTL_SECONDS` (default 30min) — both in `config/suratapp.php`.
- Login lockout: `LOGIN_MAX_ATTEMPTS` / `LOGIN_LOCKOUT_MINUTES` (config/env).

## Web (session) auth
- `App\Http\Middleware\RoleMiddleware` (`role:admin`, etc.): checks `$request->user()->role` against the allowed list for a given web route — this is the Livewire-side equivalent of the API's `auth.admin`.
- `App\Http\Middleware\EnsureSingleWebSession`: enforces **one active web session per account**. `App\Livewire\Auth\Login` writes the new session ID into `users.web_session_id` (single value, same pattern as the API's `token` column) on login. Registered **globally** in the `web` middleware group (`bootstrap/app.php`) — applies to every authenticated request including Livewire's `POST /livewire/update` polling, not just full page loads. If the session ID no longer matches (someone logged in elsewhere), the current session is force-logged-out with a message.
- `CorsMiddleware` (`app/Http/Middleware/CorsMiddleware.php`): allows localhost/LAN always, plus `CORS_EXTRA_ORIGIN` (env) for the production domain.

## Access to a specific letter (Surat)
Distinct from role — see `Surat::bolehDiaksesOleh()` in [surat-masuk.md](surat-masuk.md) — admin/pimpinan bypass everything else, all other roles must have a matching `nama` in that letter's disposisi/approval rows.
