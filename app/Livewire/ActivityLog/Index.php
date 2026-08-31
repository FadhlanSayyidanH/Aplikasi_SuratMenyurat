<?php

namespace App\Livewire\ActivityLog;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Log Aktivitas (admin-only, read-only) -- migrasi dari
 * lib/screens/activity_log_screen.dart. Layar Flutter aslinya TIDAK punya
 * filter apapun (cuma daftar + tombol Muat Ulang + Hapus Log), tapi karena
 * ini tabel yang bisa berisi ratusan/ribuan baris dan di web (bukan list
 * mobile yang di-scroll santai) itu jadi kurang praktis untuk ditelusuri,
 * di sini ditambah filter ringan (aksi/kata kunci/rentang tanggal) --
 * SEMUA dihitung in-memory lewat #[Computed] di atas satu query yang sama
 * (2000 baris terbaru, meniru limit ActivityLogController::list() persis),
 * BUKAN query terpisah per filter -- jadi tidak menambah beban DB dan tidak
 * mengubah aturan bisnis apapun, murni penyaringan tampilan.
 *
 * Aturan "hapus log" MENIRU PERSIS ActivityLogController::clear(): hitung
 * total baris lalu DELETE semuanya, baru SETELAH itu catat satu baris log
 * baru beraksi 'clear_log' supaya log tidak pernah benar-benar kosong.
 */
#[Layout('layouts.app', ['title' => 'Log Aktivitas'])]
class Index extends Component
{
    /** Kata kunci bebas: dicocokkan ke nama, username, dan keterangan. */
    public string $search = '';

    /** '' = semua aksi. Nilai lain harus salah satu kunci di self::AKSI_INFO. */
    public string $aksiFilter = '';

    /** Format 'Y-m-d' (dari <input type="date">) atau string kosong = tidak dibatasi. */
    public string $dateFrom = '';

    public string $dateTo = '';

    /**
     * Info tampilan per jenis aksi -- cermin persis _aksiInfoUntuk() di
     * activity_log_screen.dart (label, warna). Ikonnya dirender langsung di
     * Blade (lihat partial aksi-icon) supaya tidak perlu mengelola nama
     * ikon Material di sisi PHP.
     */
    public const AKSI_INFO = [
        'login' => ['label' => 'Login', 'text' => 'text-secondary-green', 'bg' => 'bg-secondary-green/12'],
        'logout' => ['label' => 'Logout', 'text' => 'text-text-muted', 'bg' => 'bg-text-muted/12'],
        'create' => ['label' => 'Tambah', 'text' => 'text-primary-green', 'bg' => 'bg-primary-green/12'],
        'update' => ['label' => 'Ubah', 'text' => 'text-orange-800', 'bg' => 'bg-orange-800/12'],
        'delete' => ['label' => 'Hapus', 'text' => 'text-app-error', 'bg' => 'bg-app-error/12'],
        'clear_log' => ['label' => 'Hapus Log', 'text' => 'text-indigo-700', 'bg' => 'bg-indigo-700/12'],
    ];

    /**
     * 2000 baris terbaru -- LIMIT ini sengaja disamakan persis dengan
     * ActivityLogController::list() (lihat komentar di sana): jaring
     * pengaman supaya query/response tidak tumbuh tanpa batas, jauh lebih
     * dari cukup untuk kebutuhan audit praktis Administrator.
     */
    #[Computed]
    public function logs()
    {
        return ActivityLog::query()
            ->orderByDesc('waktu')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();
    }

    #[Computed]
    public function filteredLogs()
    {
        $search = mb_strtolower(trim($this->search));

        return $this->logs->filter(function (ActivityLog $row) use ($search) {
            if ($this->aksiFilter !== '' && $row->aksi !== $this->aksiFilter) {
                return false;
            }
            if ($this->dateFrom !== '' && $row->waktu->format('Y-m-d') < $this->dateFrom) {
                return false;
            }
            if ($this->dateTo !== '' && $row->waktu->format('Y-m-d') > $this->dateTo) {
                return false;
            }
            if ($search !== '') {
                $haystack = mb_strtolower($row->nama.' '.($row->username ?? '').' '.($row->keterangan ?? ''));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /** Daftar aksi yang benar-benar muncul di 2000 baris termuat, untuk isi dropdown filter (plus jenis dikenal yang belum tentu muncul). */
    #[Computed]
    public function aksiTersedia(): array
    {
        $dikenal = array_keys(self::AKSI_INFO);
        $dariData = $this->logs->pluck('aksi')->unique()->values()->all();

        return array_values(array_unique([...$dikenal, ...$dariData]));
    }

    public function resetFilter(): void
    {
        $this->search = '';
        $this->aksiFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    /** Cermin persis ActivityLogController::clear(). */
    public function clearLog(): void
    {
        if ($this->logs->isEmpty()) {
            return;
        }

        $user = Auth::user();

        $total = ActivityLog::query()->count();

        ActivityLog::query()->delete();

        // Log "log dihapus" tetap tercatat -- dibuat SETELAH penghapusan
        // supaya log tidak pernah benar-benar kosong, selalu menyisakan
        // jejak siapa & kapan log dibersihkan.
        ActivityLogger::log(
            request(),
            $user->username,
            $user->nama,
            'clear_log',
            "Menghapus $total catatan log sebelumnya",
        );

        $this->dispatch('notify', message: "Seluruh log aktivitas ($total baris) berhasil dihapus.");
    }

    /**
     * Ringkasan browser & OS dari string User-Agent mentah -- cermin persis
     * _ringkasPerangkat() di activity_log_screen.dart (bukan parser
     * lengkap, cukup untuk kasus umum).
     */
    public static function ringkasPerangkat(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return 'Tidak diketahui';
        }

        $browser = 'Browser lain';
        if (str_contains($userAgent, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera')) {
            $browser = 'Opera';
        } elseif (str_contains($userAgent, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari/')) {
            $browser = 'Safari';
        }

        $os = 'OS lain';
        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        return "$browser • $os";
    }

    public function render()
    {
        return view('livewire.activity-log.index');
    }
}
