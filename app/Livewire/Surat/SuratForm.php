<?php

namespace App\Livewire\Surat;

use App\Livewire\Concerns\MengelolaRantaiSuratKeluar;
use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\SuratFile;
use App\Services\ActivityLogger;
use App\Services\BagService;
use App\Services\SuratFileService;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Wizard 5 langkah untuk membuat Surat Masuk/Keluar -- migrasi dari
 * lib/screens/surat_form_screen.dart. Aturan bisnisnya (urutan validasi,
 * field wajib per jenis, whitelist MIME file, pembangunan rantai approval)
 * SENGAJA meniru persis App\Http\Controllers\Api\SuratController@create
 * (endpoint JSON yang sudah teruji dipakai app Flutter) -- lihat komentar
 * di setiap langkah submit() untuk potongan controller yang ditiru.
 */
#[Layout('layouts.app', ['title' => 'Buat Surat'])]
class SuratForm extends Component
{
    use WithFileUploads;
    use MengelolaRantaiSuratKeluar;

    /**
     * Guard UX klien untuk total ukuran seluruh lampiran (sama seperti
     * _batasTotalUkuranBerkas di Flutter) -- validasi SEBENARNYA (per-file)
     * tetap dilakukan SuratFileService::simpanFileSurat() saat submit.
     */
    private const BATAS_TOTAL_UKURAN_BERKAS = 200 * 1024 * 1024;

    protected BagService $bagService;

    protected SuratFileService $suratFileService;

    public int $step = 0;

    public string $jenis = 'masuk';

    public bool $canMasuk = false;

    public bool $canKeluar = false;

    // Langkah 0 -- Surat Masuk.
    public string $nomorSurat = '';

    public string $namaPengaju = '';

    // Langkah 1.
    public string $tanggal = '';

    public string $klasifikasi = '';

    public string $klasifikasiLainnya = '';

    // Langkah 2.
    public string $perihal = '';

    public string $keterangan = '';

    // Langkah 3.
    /** Buffer transien terikat wire:model -- dipindah ke $uploadedFiles begitu upload selesai, lihat updatedFiles(). */
    public array $files = [];

    /** @var TemporaryUploadedFile[] Akumulasi seluruh file yang sudah dipilih pengguna. */
    public array $uploadedFiles = [];

    /** Nama tampilan per file (index sejajar dengan $uploadedFiles) -- prefill nama asli, bisa diganti bebas. */
    public array $displayNames = [];

    public bool $fileError = false;

    public ?string $submitError = null;

    public bool $isSubmitting = false;

    public function boot(BagService $bagService, SuratFileService $suratFileService): void
    {
        $this->bagService = $bagService;
        $this->suratFileService = $suratFileService;
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->canMasuk = (bool) $user->boleh_input_masuk;
        $this->canKeluar = (bool) $user->boleh_input_keluar;

        $this->jenis = (!$this->canMasuk && $this->canKeluar) ? 'keluar' : 'masuk';
        $this->klasifikasi = Surat::KLASIFIKASI_OPTIONS[0];
        $this->tanggal = now()->format('Y-m-d');

        $this->loadBagsKeluar();
    }

    public function getIsMasukProperty(): bool
    {
        return $this->jenis === 'masuk';
    }

    public function getStepTitlesProperty(): array
    {
        return [
            $this->isMasuk ? 'Data Pengirim' : 'Bagian Penerbit',
            'Tanggal & Klasifikasi',
            'Perihal & Catatan',
            'Unggah File',
            'Resume',
        ];
    }

    /**
     * Urutan LENGKAP rantai approval Surat Keluar untuk Bag/Kaur/rantai
     * manual yang sedang terpilih -- dipakai untuk pratinjau "Alur Rute" di
     * Langkah 1 & Resume. isMasuk digagalkan lebih dulu karena Surat Masuk
     * tidak punya rantai Bag/Kaur sama sekali (lihat submit()).
     */
    public function getAlurRuteProperty(): array
    {
        if ($this->isMasuk) {
            return [];
        }

        return $this->hitungAlurRuteKeluar();
    }

    public function getKlasifikasiLainnyaAktifProperty(): bool
    {
        return $this->klasifikasi === Surat::KLASIFIKASI_LAINNYA;
    }

    public function getKlasifikasiOptionsProperty(): array
    {
        return Surat::KLASIFIKASI_OPTIONS;
    }

    public function getKlasifikasiLainnyaSentinelProperty(): string
    {
        return Surat::KLASIFIKASI_LAINNYA;
    }

    public function getKlasifikasiFinalProperty(): string
    {
        return $this->klasifikasiLainnyaAktif ? trim($this->klasifikasiLainnya) : $this->klasifikasi;
    }

    /** Daftar file terpilih siap-tampil (nama asli, ukuran manusiawi, ekstensi) -- dipakai langkah 3 & resume. */
    public function getBerkasTampilanProperty(): array
    {
        $out = [];
        foreach ($this->uploadedFiles as $index => $file) {
            // File livewire-tmp bisa saja sudah tidak ada lagi (mis. sempat
            // ke-submit lalu dipindahkan ke storage permanen, tapi render
            // berikutnya -- request lain yang tertunda/balapan -- masih
            // memegang snapshot komponen lama) -- lewati saja daripada
            // getSize() melempar League\Flysystem\UnableToRetrieveMetadata
            // dan bikin 500.
            if (!$this->fileMasihAda($file)) {
                continue;
            }
            $nama = $file->getClientOriginalName();
            $out[] = [
                'index' => $index,
                'nama_asli' => $nama,
                'ukuran' => $this->formatUkuran($file->getSize()),
                'ekstensi' => strtolower(pathinfo($nama, PATHINFO_EXTENSION)),
            ];
        }

        return $out;
    }

    /** Lihat komentar di getBerkasTampilanProperty(). */
    private function fileMasihAda($file): bool
    {
        return method_exists($file, 'exists') ? $file->exists() : is_file($file->getRealPath());
    }

    private function formatUkuran(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }

    public function setJenis(string $jenis): void
    {
        if (!in_array($jenis, ['masuk', 'keluar'], true)) {
            return;
        }
        $this->jenis = $jenis;
        $this->jalurError = false;
        $this->resetErrorBag();
    }

    /** Hook Livewire otomatis dipanggil begitu upload $files selesai. */
    public function updatedFiles(): void
    {
        if (!$this->files) {
            return;
        }

        $totalSekarang = array_sum(array_map(fn ($f) => $this->fileMasihAda($f) ? $f->getSize() : 0, $this->uploadedFiles));
        $totalBaru = array_sum(array_map(fn ($f) => $f->getSize(), $this->files));
        if ($totalSekarang + $totalBaru > self::BATAS_TOTAL_UKURAN_BERKAS) {
            $this->addError('files', 'Total ukuran seluruh file maksimal 200 MB. Pilih file yang lebih kecil atau kurangi jumlah file.');
            $this->files = [];

            return;
        }

        foreach ($this->files as $file) {
            $this->uploadedFiles[] = $file;
            $this->displayNames[] = $file->getClientOriginalName();
        }
        $this->files = [];
        $this->fileError = false;
        $this->resetErrorBag('files');
    }

    public function removeFile(int $index): void
    {
        unset($this->uploadedFiles[$index], $this->displayNames[$index]);
        $this->uploadedFiles = array_values($this->uploadedFiles);
        $this->displayNames = array_values($this->displayNames);
    }

    /**
     * Validasi HANYA field milik langkah yang sedang tampil, lalu maju ke
     * langkah berikutnya kalau lolos -- sama seperti _lanjut() di Flutter.
     */
    public function nextStep(): void
    {
        $valid = match ($this->step) {
            0 => $this->validateStep0(),
            1 => $this->validateStep1(),
            2 => $this->validateStep2(),
            3 => $this->validateStep3(),
            default => true,
        };

        if ($valid) {
            $this->step++;
        }
    }

    public function prevStep(): void
    {
        if ($this->step > 0) {
            $this->step--;
        }
    }

    /** Hanya langkah yang sudah dilewati boleh diketuk untuk kembali meninjau/mengubah isiannya. */
    public function goToStep(int $index): void
    {
        if ($index < $this->step) {
            $this->step = $index;
        }
    }

    private function validateStep0(): bool
    {
        if ($this->isMasuk) {
            $this->validate([
                'nomorSurat' => 'required',
                'namaPengaju' => 'required',
            ], [
                'nomorSurat.required' => 'Nomor surat wajib diisi',
                'namaPengaju.required' => 'Nama pengirim surat wajib diisi',
            ]);

            return true;
        }

        if ($this->modeManual) {
            $hasRantaiManual = count($this->rantaiManual) > 0;
            $this->jalurError = !$hasRantaiManual;
            $this->kaurError = false;

            return $hasRantaiManual;
        }

        // Bagian Penerbit SATU tampilan gabungan (dropdown "Kaur Penyiap Surat" --
        // sudah terisi otomatis kalau jalurOtomatis) -- validasi berlaku sama untuk
        // hasil deteksi otomatis maupun pilihan manual, cukup pastikan sudah terisi.
        $hasJalur = $this->bagTerpilihId !== null;
        $hasKaur = !$this->perluPilihKaur || $this->kaurMemberId !== null;
        $this->jalurError = !$hasJalur;
        $this->kaurError = $hasJalur && !$hasKaur;

        return $hasJalur && $hasKaur;
    }

    private function validateStep1(): bool
    {
        $this->validate([
            'tanggal' => 'required|date',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.date' => 'Format tanggal tidak valid',
        ]);

        if ($this->klasifikasiLainnyaAktif) {
            $this->validate([
                'klasifikasiLainnya' => 'required|max:50',
            ], [
                'klasifikasiLainnya.required' => 'Jenis surat wajib diisi',
            ]);
        }

        return true;
    }

    private function validateStep2(): bool
    {
        $this->validate([
            'perihal' => 'required',
        ], [
            'perihal.required' => 'Perihal wajib diisi',
        ]);

        return true;
    }

    private function validateStep3(): bool
    {
        $hasFile = count($this->uploadedFiles) > 0;
        $this->fileError = !$hasFile;

        return $hasFile;
    }

    private function fail(string $message, int $step): void
    {
        $this->submitError = $message;
        $this->step = $step;
        $this->isSubmitting = false;
    }

    /**
     * Bungkus TemporaryUploadedFile Livewire jadi Illuminate\Http\UploadedFile
     * "test mode" -- TemporaryUploadedFile::move() akan gagal lewat
     * move_uploaded_file() (file itu bukan hasil upload HTTP mentah pada
     * request saat ini, tapi file lokal hasil unggahan sementara Livewire).
     * Dengan $test=true, ->move() dari Symfony jatuh ke File::move() biasa
     * (rename()), yang bekerja untuk file lokal apa pun -- SuratFileService
     * tidak perlu tahu/berubah sama sekali soal ini.
     */
    private function toRealUploadedFile(TemporaryUploadedFile $temp): UploadedFile
    {
        return new UploadedFile(
            $temp->getRealPath(),
            $temp->getClientOriginalName(),
            $temp->getMimeType(),
            null,
            true,
        );
    }

    /**
     * Migrasi 1:1 dari SuratController@create -- urutan validasi, pesan
     * error, dan tulisan DB (surat/surat_file/surat_approval) SENGAJA
     * dibuat sama persis dengan endpoint API tersebut.
     */
    public function submit(): void
    {
        $this->submitError = null;
        $this->isSubmitting = true;

        $user = auth()->user();

        // Baris ini meniru $jenis === 'masuk' && !$authUser->boleh_input_masuk di controller
        // (baris 50-55 SuratController@create) -- jaga-jaga kalau $jenis dimanipulasi klien.
        if ($this->jenis === 'masuk' && !$user->boleh_input_masuk) {
            $this->fail('Akun ini belum diberi izin menginput Surat Masuk, hubungi admin', 0);

            return;
        }
        if ($this->jenis === 'keluar' && !$user->boleh_input_keluar) {
            $this->fail('Akun ini belum diberi izin menginput Surat Keluar, hubungi admin', 0);

            return;
        }
        if (!in_array($this->jenis, ['masuk', 'keluar'], true)) {
            $this->fail('Jenis surat tidak valid', 0);

            return;
        }

        $tanggal = trim($this->tanggal);
        $perihal = trim($this->perihal);
        if ($tanggal === '' || $perihal === '') {
            $this->fail('Tanggal dan perihal wajib diisi', 1);

            return;
        }

        $nomorSurat = trim($this->nomorSurat);
        $namaPengaju = trim($this->namaPengaju);
        // Tidak diketik manual -- selalu "hari ini" saat surat ini benar-benar disimpan (lihat kolom tanggal_input_sistem).
        $tanggalInputSistem = now()->format('Y-m-d');

        if ($this->jenis === 'masuk') {
            if ($nomorSurat === '') {
                $this->fail('Nomor surat wajib diisi', 0);

                return;
            }
            if ($namaPengaju === '') {
                $this->fail('Nama pengirim surat wajib diisi', 0);

                return;
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $this->fail('Format tanggal tidak valid, gunakan YYYY-MM-DD', 1);

            return;
        }

        $klasifikasi = $this->klasifikasiFinal;
        if ($klasifikasi === '') {
            $this->fail('Klasifikasi surat wajib diisi', 1);

            return;
        }

        $kabagDituju = '';
        $anggotaJalurKeluar = [];
        if ($this->jenis === 'keluar') {
            if ($this->modeManual) {
                // Rantai bebas pilihan pengaju -- TIDAK terikat Bag mana pun,
                // jadi kabag_dituju dibiarkan null (surat ini tidak akan
                // muncul di dashboard "surat Bag saya", tapi tiap orang di
                // rantai tetap melihatnya lewat antrean approval pribadinya).
                $anggotaJalurKeluar = array_column($this->rantaiManual, 'nama');
                if (!$anggotaJalurKeluar) {
                    $this->fail('Pilih minimal satu orang untuk rantai proses manual', 0);

                    return;
                }
            } else {
                $kabagDituju = $this->bagTerpilih['nama'] ?? '';
                if ($kabagDituju === '') {
                    $this->fail('Bagian yang dituju wajib dipilih untuk surat keluar', 0);

                    return;
                }
                try {
                    $anggotaJalurKeluar = $this->bagService->rantaiKeluarUntukBag($kabagDituju, $this->kaurMemberId);
                } catch (\RuntimeException $e) {
                    $this->fail($e->getMessage(), 0);

                    return;
                }
            }
        }

        $mimeWhitelist = config('suratapp.mime_keluar');
        $labelFormat = 'DOCX, PPTX, XLSX, PDF, JPG, atau PNG';

        if (!$this->uploadedFiles) {
            $this->fail("Minimal satu file $labelFormat surat wajib diunggah", 3);

            return;
        }

        $realFiles = array_map(fn ($tempFile) => $this->toRealUploadedFile($tempFile), $this->uploadedFiles);

        $stored = [];
        try {
            if ($this->jenis === 'masuk') {
                // 2 foto atau lebih sekaligus (mis. hasil foto kamera per
                // halaman) otomatis digabung jadi SATU PDF multi-halaman --
                // lihat komentar lengkap di SuratFileService::simpanFileSuratMasuk().
                $stored = $this->suratFileService->simpanFileSuratMasuk($realFiles, $this->displayNames, $mimeWhitelist);
            } else {
                foreach ($realFiles as $index => $realFile) {
                    $saved = $this->suratFileService->simpanFileSurat($realFile, $mimeWhitelist);
                    $customName = trim($this->displayNames[$index] ?? '');
                    if ($customName !== '') {
                        $saved['file_original_name'] = $this->suratFileService->terapkanNamaTampilan($saved, $customName);
                    }
                    $stored[] = $saved;
                }
            }
        } catch (\RuntimeException $e) {
            foreach ($stored as $s) {
                @unlink(config('suratapp.uploads_path').'/'.$s['file_name']);
            }
            $this->fail($e->getMessage(), 3);

            return;
        }

        $kasubditGerbangUserId = null;
        if ($this->jenis === 'masuk') {
            $deteksi = $this->bagService->deteksiKasubditUntukTurmin((int) $user->id);
            $kasubditGerbangUserId = $deteksi['id'] ?? null;
        }

        $dibuatOleh = $user->nama;

        $namaPengajuValue = $namaPengaju === '' ? null : $namaPengaju;
        $kabagDitujuValue = $kabagDituju === '' ? null : $kabagDituju;
        $keteranganTrim = trim($this->keterangan);
        $keteranganValue = $keteranganTrim === '' ? null : $keteranganTrim;
        $nomorSuratInsert = $this->jenis === 'keluar' ? null : $nomorSurat;
        $tanggalInputSistemValue = $this->jenis === 'masuk' ? $tanggalInputSistem : null;

        $surat = new Surat();
        $surat->jenis = $this->jenis;
        $surat->nomor_surat = $nomorSuratInsert;
        $surat->tanggal = $tanggal;
        $surat->tanggal_input_sistem = $tanggalInputSistemValue;
        $surat->perihal = $perihal;
        $surat->nama_pengaju = $namaPengajuValue;
        $surat->klasifikasi = $klasifikasi;
        $surat->kabag_dituju = $kabagDitujuValue;
        $surat->keterangan = $keteranganValue;
        $surat->kasubdit_gerbang_user_id = $kasubditGerbangUserId;
        $surat->save();
        $id = $surat->id;

        foreach ($stored as $index => $s) {
            SuratFile::query()->create([
                'surat_id' => $id,
                'urutan' => $index,
                'file_name' => $s['file_name'],
                'file_original_name' => $s['file_original_name'],
            ]);
        }

        $approvalRoles = match ($this->jenis) {
            'keluar' => $anggotaJalurKeluar,
            'masuk' => ['Turmin', 'Kasubdit'],
            default => [],
        };

        foreach ($approvalRoles as $index => $role) {
            $urutan = $index + 1;
            $turminInputSendiri = $this->jenis === 'masuk' && $index === 0;

            SuratApproval::query()->create([
                'surat_id' => $id,
                'urutan' => $urutan,
                'role' => $role,
                'status' => $turminInputSendiri ? 'disetujui' : 'menunggu',
                'catatan' => $turminInputSendiri ? "Surat diinput oleh $dibuatOleh." : null,
                'diproses_oleh' => $turminInputSendiri ? $dibuatOleh : null,
                'diproses_at' => $turminInputSendiri ? now() : null,
            ]);
        }

        $jenisLabel = $this->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
        $labelNomorSurat = $nomorSuratInsert !== null ? "$jenisLabel $nomorSuratInsert" : $jenisLabel;
        ActivityLogger::log(request(), null, $dibuatOleh, 'create', "$labelNomorSurat ($perihal)", $id);

        // SENGAJA session()->flash() + redirect TANPA navigate:true (SPA),
        // BUKAN $this->dispatch('notify') seperti aksi lain di app ini --
        // sudah diuji langsung, TIDAK ada cara yang bisa diandalkan untuk
        // membuat toast/pesan muncul lagi SETELAH redirect(navigate:true):
        // dispatch Livewire tidak terbawa lewat transisi SPA-nya, x-init di
        // elemen anak yang muncul kondisional juga tidak ikut di-scan ulang
        // oleh Alpine (morph wire:navigate tidak memanggil Alpine.initTree()
        // pada node barunya), bahkan <script> polos pun TIDAK otomatis
        // dieksekusi ulang (beda dari morph komponen Livewire biasa yang
        // memang menjamin itu -- morph SPA wire:navigate rupanya tidak).
        // Satu-satunya yang terbukti bekerja: redirect PENUH (bukan SPA),
        // supaya seluruh halaman (termasuk <body>-nya) benar-benar dimuat
        // ulang dari nol -- trigger session('status') di layouts.app lalu
        // jalan normal seperti loading pertama kali.
        session()->flash('status', "$jenisLabel berhasil disimpan.");

        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.surat.form');
    }
}
