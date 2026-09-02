# Surat Masuk

Incoming-letter workflow: Turmin inputs the letter → Kasubdit approves and routes it to one or more Bag (organizational units) → each Bag's members (or its Kabag first, if it has one) fill in a disposisi (their response/annotation). Kasubdit can also add ad-hoc extra recipients ("Tembusan Manual") outside the Bag structure.

## Key files
- Model: `app/Models/Surat.php` (`jenis = 'masuk'`), `app/Models/SuratDisposisi.php`, `app/Models/SuratApproval.php`
- Web: `app/Livewire/Surat/SuratForm.php` (create), `app/Livewire/SuratReview.php` (review/approval/disposisi UI), route `routes/web_surat_review.php` (`/surat/{surat}`, name `surat.review`)
- API: `app/Http/Controllers/Api/SuratController.php` (mirrors the Livewire logic for the Flutter app), `routes/api_surat.php`
- Org structure: `app/Services/BagService.php` (Bag/Kabag/anggota lookups, disposisi routing rules), `app/Models/BagMasuk.php`, `app/Models/BagMember.php`, `app/Models/BagDisposisiAnggota.php`

## Data model
- `surat_approval`: **sequential** chain, one row per stage (`urutan` order), used for the Turmin→Kasubdit approval steps.
- `surat_disposisi`: **parallel/independent** — one row per recipient, each filled independently, no ordering. Used once the letter is routed to a Bag/Kabag/manual recipient.
- **Critical invariant**: both `surat_approval.role` and `surat_disposisi.role` store the recipient's `nama` **string**, not a role/permission name. `Surat::bolehDiaksesOleh()` (`app/Models/Surat.php:91`) checks these columns generically — ANY code path that inserts a row with a given `nama` automatically grants that user view/edit access to the letter, regardless of mechanism. Because of this, **always re-derive a user's `nama` server-side from their `user_id`** before inserting a disposisi/approval row — never trust a client-supplied name string directly.

## Kabag routing
- `BagService::bagUntukKabagNama($nama)` returns the Bag whose Kabag matches `$nama`, with its `anggota_masuk` list.
- If a Bag has a registered Kabag account, the letter stops at the Kabag first (Kabag fills disposisi + picks which of the Bag's own members it goes to next). If a Bag has no Kabag, it's forwarded to all its members automatically.
- **Bug fixed**: `bagUntukKabagNama()` must exclude the Kabag's own `nama` from `anggota_masuk`, otherwise the Kabag sees itself in its own "forward to" list (self-forward). Fixed in `app/Services/BagService.php`.
- **Bug fixed**: `SuratReview::muatBagDisposisi()` pre-fills the Kasubdit's "forwarded to this Bag" checkbox. For Bags **with** a Kabag, the check must be based on whether the Kabag's `nama` alone has a disposisi row — the old logic required ALL members to match, which can never be true when only one row (the Kabag's) was ever created. Branch on `$bag['kabag']` presence.
- **Bug fixed (2026-09-02)**: `BagService::teruskanDisposisiKabag()` was **insert-only** — a Kabag could add members to the disposisi via the "teruskan ke" checkboxes but un-checking one (e.g. a Kaur that the Kasubdit had added directly via Tembusan Manual) did nothing on save. It's now a **full reconcile** over that Kabag's own bag members: checked-but-missing → insert, unchecked-but-present → delete **unless that member already filled their disposisi** (`catatan`/`diproses_oleh` set — kept, name returned in `dipertahankan` so the caller can notify). Only touches `bag_disposisi_anggota` members of the Kabag's own bag — never the Kabag's own row, `Kasubditbinum`, approval rows, or other bags' members. Callers (`SuratReview::simpanDisposisi()`, `Api\SuratController::updateDisposisi()`) now call it unconditionally whenever the acting role is a Kabag (so "uncheck everyone" works) and sync the removed names out of the `surat.disposisi` CSV. Return shape changed from `string[]` to `array{ditambah,dihapus,dipertahankan: string[]}`.

## Teruskan antar-anggota (rekan sebag)
Added 2026-09-02. A regular Penerima Disposisi (`bag_disposisi_anggota`) who holds a disposisi card on a Surat Masuk can forward it to **other Penerima Disposisi of the same Bag** (peer-to-peer). The Kabag is **not** a target here (Kabag has its own routing flow above).

- `surat_disposisi.ditambah_oleh` (nullable string, migration `2026_09_02_100000_...`) records **who forwarded** a disposisi row: the Kabag's `nama` for `teruskanDisposisiKabag()` inserts, the forwarder's `nama` for peer inserts, `NULL` for rows the Kasubdit gate created (`SuratReview::simpan()`).
- `BagService::teruskanDisposisiAntarAnggota($suratId, $dariNama, $userIds)` — valid targets = union of `bag_disposisi_anggota` of every Bag where `$dariNama` is a disposisi member, minus self. Adds a row (`ditambah_oleh = $dariNama`) for each checked target without one. **Cancels** (deletes) a target's row **only if** `row.ditambah_oleh === $dariNama` AND that target hasn't responded (`catatan` empty & `diproses_oleh` null) — so a member can only undo their *own* forward, never one made by the Kabag / gate / an already-answered peer. Returns `array{ditambah,dihapus,ditolak}`.
- `BagService::bagDisposisiIdsUntukNama($nama)` — helper: bag ids where `$nama` is a `bag_disposisi_anggota` member.
- `SuratReview`: `$rekanSebagTerpilih` (card `nama` => `int[]` user_id), `rekanSebagUntuk($nama)` (checkbox list + per-peer `punya_baris`/`bisa_uncheck` flags — locked peers shown checked+disabled with "(sudah menerima)"), `mount()` pre-fills the acting user's own card. `simpanDisposisi()` runs the peer branch as an `elseif` after the Kabag branch (mutually exclusive: a Kabag's `nama` isn't in `bag_disposisi_anggota`), syncing `surat.disposisi` CSV + an activity-log line the same way as the Kabag branch.
- `Api\SuratController::updateDisposisi()` mirrors it — client sends `rekan_terpilih[]` (user ids); absent → no-op, backward compatible.
- Tests: `tests/Feature/Livewire/SuratMasukTeruskanRekanTest.php` (8 cases).

## Tembusan Manual
Kasubdit can freely search and add extra recipients beyond the Bag structure — same UX pattern as Surat Keluar's manual chain picker (see [surat-keluar.md](surat-keluar.md)), but feeds Surat Masuk's parallel disposisi mechanism (the picked users can fill their own disposisi independently, not join an ordered chain).

- `SuratReview.php`: `$tembusanManualTerpilih` (array of `{user_id, nama}`), `$cariUserTembusan`, `getOpsiUserTembusanProperty()` (search results), `pilihUserTembusan()`, `hapusDariTembusanManual()`.
- On reopen, `muatBagDisposisi()` also back-fills `$tembusanManualTerpilih` from any disposisi rows whose `nama` isn't explained by an owned Bag's Kabag/anggota structure.
- In `simpan()`, the manual-tembusan loop (re-deriving `nama` from `user_id` server-side) is appended to `$disposisiList` right after the Bag-forwarding loop.
- `SuratController.php` (API) mirrors this for the Flutter app: accepts `tembusan_user_ids[]`, same re-derive-server-side pattern.
- `BagService::namaValidUntukDisposisi($nama)` has a 4th check — `surat_disposisi` rows with that `role` — which lets a manually-tembusan'd user fill their own disposisi (same mechanism that also lets a Kabag fill its own).
- UI: `resources/views/livewire/partials/surat-review-form-masuk.blade.php` — styled to match Surat Keluar's manual-chain picker exactly (search input, dropdown, `<ol>` selected-list with person icon + name + X remove button).
- Tests: `tests/Feature/Livewire/SuratMasukTembusanManualTest.php` (8 cases), `tests/Feature/Livewire/SuratMasukKabagTest.php` (9 cases, incl. self-forward exclusion and checkbox pre-fill regressions).

## Access control
See `Surat::bolehDiaksesOleh()` above — admin/pimpinan always allowed; otherwise the user's `nama` must appear in a disposisi row, an approval row, or as `diproses_oleh` on an approval row.

**`nama`-access ≠ inbox visibility.** Being a valid disposisi recipient (matched by `nama`) lets an account *open* and *respond to* a letter, but whether that letter shows up in the account's "Dashboard Surat Masuk" list / notification badge is a separate, `role`-gated path in `SidebarMenuService`. In particular a `role='turmin'` account gets a permanently empty inbox even when it has disposisi rows — see [kontrol-akses.md](kontrol-akses.md#roles). A disposisi recipient should be `role='user'`.
