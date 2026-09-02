# Feature docs — Surat Ditajenad (CARAKA-BINUM)

Dense reference for future sessions — read the relevant file instead of re-exploring the codebase from scratch. See also the app-level `README.md` for the general overview.

- [surat-masuk.md](surat-masuk.md) — incoming letters: Turmin → Kasubdit approval → Bag/Kabag disposisi routing → Tembusan Manual; `surat_disposisi` parallel model, access-control invariant.
- [surat-keluar.md](surat-keluar.md) — outgoing letters: sequential approval chain, auto Bag/Kasi/Kaur routing vs. free manual chain.
- [lampiran-onlyoffice.md](lampiran-onlyoffice.md) — attachments, OnlyOffice editor/annotation, encryption-at-rest, known server-permission gotchas.
- [notifikasi.md](notifikasi.md) — Web Push (browser/OS notifications) and admin broadcast Pengumuman banner.
- [kontrol-akses.md](kontrol-akses.md) — API token auth vs. web session auth, roles, single-active-web-session enforcement.
- [log-aktivitas.md](log-aktivitas.md) — audit trail of significant actions.
- [administrasi.md](administrasi.md) — admin-only panels: user management, hak akses, struktur anggota, Bag management, storage monitor, laporan gangguan (in-app issue reports, replaces the WhatsApp button).
