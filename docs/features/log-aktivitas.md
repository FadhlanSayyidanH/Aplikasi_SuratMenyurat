# Log Aktivitas

Audit trail — one row per significant action (login, logout, create, update, delete, clear_log) across the app.

## Key files
- Model: `app/Models/ActivityLog.php`
- Service: `app/Services/ActivityLogger.php` — static `log()` helper, called from throughout the app wherever a loggable action happens (e.g. login/logout in `routes/web.php`, letter CRUD/workflow actions in `SuratReview.php`/`SuratController.php`)
- Web: `app/Livewire/ActivityLog/Index.php` (`/log-aktivitas`, admin-only, `routes/web_activitylog.php`)
- API: `app/Http/Controllers/Api/ActivityLogController.php` (`list`/`clear`, both `auth.token` + `auth.admin`, `routes/api_activitylog.php`)

## Notable design choice
`ActivityLogger::clientIp()` deliberately uses `$request->ip()` (raw TCP connection IP) directly, **not** the `X-Forwarded-For` header — nginx faces the internet directly in production (no trusted reverse proxy in front), so trusting that header would let any client forge the IP recorded in the audit log just by setting it themselves.

## Fields
`surat_id` (nullable — not every action is letter-specific), `username`, `nama`, `aksi`, `keterangan` (free-text detail), `ip_address`, `user_agent`.
