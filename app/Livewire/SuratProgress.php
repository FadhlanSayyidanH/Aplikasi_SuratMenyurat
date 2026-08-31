<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman publik (TANPA login) "Progres Surat" -- migrasi dari
 * lib/screens/surat_progress_screen.dart + surat_progress_detail_screen.dart
 * (proyek Flutter lama). Satu komponen Livewire menangani baik pencarian
 * (list) maupun detail (master-detail sederhana lewat $selectedId), sesuai
 * catatan di routes/web_progress.php -- kedua layar Flutter aslinya cuma
 * "list" lalu "push ke detail", yang lebih pas jadi satu halaman web
 * daripada dua route terpisah.
 *
 * SELURUH query di sini SENGAJA menyalin persis logika
 * App\Http\Controllers\Api\SuratController::progressPublic() (query surat
 * jenis='keluar' + LIKE perihal case-insensitive, minimal 3 karakter --
 * guard anti-enumerasi yang disengaja, JANGAN dihapus) -- BUKAN memanggil
 * controller/endpoint API itu. Field yang dikembalikan juga PERSIS sama:
 * TIDAK ada diproses_oleh, TIDAK ada URL file -- ini permukaan publik,
 * jangan sampai bocor informasi yang controller referensi saja tidak
 * mengekspos.
 */
#[Layout('layouts.guest')]
class SuratProgress extends Component
{
    public string $perihal = '';

    public ?int $selectedId = null;

    /** Cermin App\Http\Controllers\Api\SuratController::progressPublic() -- query + shape identik. */
    public function results(): array
    {
        $perihal = trim($this->perihal);

        if (mb_strlen($perihal) < 3) {
            return [];
        }

        $rows = DB::table('surat')->where('jenis', 'keluar')
            ->whereRaw('LOWER(perihal) LIKE LOWER(?)', ['%'.$perihal.'%'])
            ->orderBy('tanggal')->orderBy('id')
            ->get(['id', 'jenis', 'nomor_surat', 'tanggal', 'perihal', 'klasifikasi', 'kabag_dituju', 'status']);

        $data = [];
        $ids = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $ids[] = (int) $arr['id'];
            $data[(int) $arr['id']] = $arr;
        }

        $approvalBySurat = [];
        if ($ids) {
            foreach (DB::table('surat_approval')->whereIn('surat_id', $ids)->orderBy('urutan')->get() as $a) {
                $approvalBySurat[$a->surat_id][] = [
                    'urutan' => (int) $a->urutan,
                    'role' => $a->role,
                    'status' => $a->status,
                    'catatan' => $a->catatan,
                    'diproses_at' => $a->diproses_at,
                ];
            }
        }

        foreach ($data as $suratId => &$row) {
            $row['approval_detail'] = $approvalBySurat[$suratId] ?? [];
        }
        unset($row);

        return array_values($data);
    }

    /** Baris terpilih (kalau ada) di antara $this->results() -- sengaja TIDAK lookup by id lepas dari pencarian, supaya guard minimal-3-karakter di atas tidak bisa dilewati dengan menebak id. */
    public function selected(): ?array
    {
        if ($this->selectedId === null) {
            return null;
        }
        foreach ($this->results() as $row) {
            if ($row['id'] === $this->selectedId) {
                return $row;
            }
        }

        return null;
    }

    /** Cermin Surat.tahapApprovalAktif (Flutter) / SuratReview::tahapAktif() -- tahap "menunggu" pertama, hanya relevan selama status surat masih menunggu. */
    public function tahapAktif(array $row): ?array
    {
        if ($row['status'] !== 'menunggu') {
            return null;
        }
        $menunggu = array_values(array_filter($row['approval_detail'], fn ($a) => $a['status'] === 'menunggu'));
        if (!$menunggu) {
            return null;
        }
        usort($menunggu, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

        return $menunggu[0];
    }

    public function pilih(int $id): void
    {
        $this->selectedId = $id;
    }

    public function kembali(): void
    {
        $this->selectedId = null;
    }

    public function updatedPerihal(): void
    {
        // Ganti kata kunci pencarian selalu kembali ke daftar (mencegah
        // detail lama nyangkut menampilkan surat yang sudah tak lagi cocok).
        $this->selectedId = null;
    }

    public function render()
    {
        return view('livewire.surat-progress');
    }
}
