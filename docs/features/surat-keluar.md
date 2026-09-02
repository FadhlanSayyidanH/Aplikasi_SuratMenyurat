# Surat Keluar

Outgoing-letter workflow: a sequential approval chain (`surat_approval`, ordered by `urutan`), each stage processed one at a time. The chain is either auto-detected from the sender's position in the Bag/Kasi/Kaur org structure, or built manually (free pick of people, any order).

## Key files
- Model: `app/Models/Surat.php` (`jenis = 'keluar'`), `app/Models/SuratApproval.php`, `app/Models/BagKeluar.php`, `app/Models/BagKasiGrup.php`
- Shared chain-selection logic (trait, used by BOTH create and edit so they never drift apart): `app/Livewire/Concerns/MengelolaRantaiSuratKeluar.php`
- Web: `app/Livewire/Surat/SuratForm.php` (create, full chain), `app/Livewire/SuratReview.php` (edit chain — only the not-yet-processed remainder)
- API: `app/Http/Controllers/Api/SuratController.php` (`approvalLompat`, `approvalMundur`, `konfirmasiKaur`, `editResetProgress` in `routes/api_surat.php`)
- Org structure/auto-routing: `app/Services/BagService.php` — `semuaBagKeluar()`, `deteksiJalurKeluarAkun()` (auto-detect the sender's own Bag/Kaur path)

## Two chain modes
1. **Automatic (Bag/Kasi/Kaur structure)**: `BagService::deteksiJalurKeluarAkun((int) auth()->id())` tries to uniquely determine the sender's own path through the org structure. If ambiguous/undetected, falls back to manual dropdown selection of Bag + Kaur member (`$bagTerpilihId`, `$kaurMemberId` in the trait) — NOT treated as an error, just no auto-suggestion.
2. **Manual (`$modeManual = true`)**: sender freely picks any people in any order (`$rantaiManual`, array of `{user_id, nama}`), completely independent of Bag structure. When manual mode is active, `kabag_dituju` is left `null` — a manual-chain letter isn't "owned" by any particular Bag for dashboard filtering purposes (see `SidebarMenuService::bagianEfektif()`).

This same manual-picker UI/UX (search input, dropdown, ordered list with remove buttons) is what Surat Masuk's "Tembusan Manual" feature was later styled to match — see [surat-masuk.md](surat-masuk.md).

## Editing an in-flight chain
`SuratReview.php` reuses `MengelolaRantaiSuratKeluar` to let the current stage owner edit the chain, but only the **remaining unprocessed** portion — already-approved stages are immutable. A past bug here was duplicate-approval-stage insertion when editing a chain mid-flight; fixed 2026-08-26 (see project memory `project_surat_edit_rantai_bugfix` if present).

## Access control
Same `Surat::bolehDiaksesOleh()` rule as Surat Masuk (`app/Models/Surat.php:91`) — checks `surat_approval.role` (recipient nama) and `diproses_oleh` (who actually processed a stage), plus admin/pimpinan bypass.

## Public tracking
`/progres` (`routes/web_progress.php`, `App\Livewire\SuratProgress`) is the **only unauthenticated** page in the app — lets anyone track a Surat Keluar's progress without login, mirroring the old Flutter `surat_progress_screen.dart` + detail view (same component, `$selectedId` state, no separate route for the detail).
