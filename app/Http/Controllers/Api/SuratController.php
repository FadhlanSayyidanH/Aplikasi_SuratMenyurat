<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Surat;
use App\Models\SuratApproval;
use App\Models\SuratDisposisi;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BagService;
use App\Services\BaseUrlResolver;
use App\Services\SuratFileService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi 1:1 dari backend/public/surat_*.php (proyek PHP lama, kecuali
 * surat_file_*.php yang jadi modul tersendiri) -- CRUD & alur kerja
 * (state machine) Surat Masuk/Keluar: pembuatan, listing, rantai approval
 * berjenjang, gerbang disposisi, ajukan ulang, ambil alih, dsb.
 */
class SuratController extends Controller
{
    public function __construct(
        private BagService $bagService,
        private SuratFileService $suratFiles,
    ) {}

    /** POST /surat_create.php */
    public function create(Request $request)
    {
        $authUser = $request->user();

        $jenis = (string) $request->input('jenis', '');
        $nomorSurat = trim((string) $request->input('nomor_surat', ''));
        $tanggal = trim((string) $request->input('tanggal', ''));
        $tanggalInputSistem = trim((string) $request->input('tanggal_input_sistem', ''));
        $perihal = trim((string) $request->input('perihal', ''));
        $namaPengaju = trim((string) $request->input('nama_pengaju', ''));
        $klasifikasi = trim((string) $request->input('klasifikasi', ''));
        $keterangan = trim((string) $request->input('keterangan', ''));
        $kabagDituju = trim((string) $request->input('kabag_dituju', ''));
        $kaurMemberIdRaw = $request->input('kaur_member_id');
        $kaurMemberId = is_numeric($kaurMemberIdRaw) ? ((int) $kaurMemberIdRaw ?: null) : null;
        $dibuatOleh = $authUser->nama;

        if ($jenis === 'masuk' && !$authUser->boleh_input_masuk) {
            throw new ApiException('Akun ini belum diberi izin menginput Surat Masuk, hubungi admin', 403);
        }
        if ($jenis === 'keluar' && !$authUser->boleh_input_keluar) {
            throw new ApiException('Akun ini belum diberi izin menginput Surat Keluar, hubungi admin', 403);
        }

        if (!in_array($jenis, ['masuk', 'keluar'], true)) {
            throw new ApiException('Jenis surat tidak valid', 400);
        }
        if ($tanggal === '' || $perihal === '') {
            throw new ApiException('Tanggal dan perihal wajib diisi', 400);
        }
        if ($jenis === 'masuk') {
            if ($nomorSurat === '') {
                throw new ApiException('Nomor surat wajib diisi', 400);
            }
            if ($namaPengaju === '') {
                throw new ApiException('Nama pengirim surat wajib diisi', 400);
            }
            if ($tanggalInputSistem === '') {
                throw new ApiException('Tanggal dimasukkan ke sistem wajib diisi', 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalInputSistem)) {
                throw new ApiException('Format tanggal dimasukkan ke sistem tidak valid, gunakan YYYY-MM-DD', 400);
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            throw new ApiException('Format tanggal tidak valid, gunakan YYYY-MM-DD', 400);
        }
        if ($klasifikasi === '') {
            throw new ApiException('Klasifikasi surat wajib diisi', 400);
        }

        $anggotaJalurKeluar = [];
        if ($jenis === 'keluar') {
            if ($kabagDituju === '') {
                throw new ApiException('Bagian yang dituju wajib dipilih untuk surat keluar', 400);
            }
            try {
                $anggotaJalurKeluar = $this->bagService->rantaiKeluarUntukBag($kabagDituju, $kaurMemberId);
            } catch (\RuntimeException $e) {
                throw new ApiException($e->getMessage(), 400);
            }
        }

        $mimeWhitelist = config('suratapp.mime_keluar');
        $labelFormat = 'DOCX, PPTX, XLSX, PDF, JPG, atau PNG';

        $filesInput = array_values(array_filter(
            (array) $request->file('files', []),
            fn ($f) => $f instanceof UploadedFile,
        ));
        if (!$filesInput) {
            throw new ApiException("Minimal satu file $labelFormat surat wajib diunggah", 400);
        }

        $displayNames = $request->input('file_display_names', []);
        if (!is_array($displayNames)) {
            $displayNames = [];
        }

        $stored = [];
        try {
            if ($jenis === 'masuk') {
                // 2 foto atau lebih sekaligus (mis. hasil foto kamera per
                // halaman lewat app mobile) otomatis digabung jadi SATU PDF
                // multi-halaman -- lihat komentar lengkap di
                // SuratFileService::simpanFileSuratMasuk().
                $stored = $this->suratFiles->simpanFileSuratMasuk($filesInput, $displayNames, $mimeWhitelist);
            } else {
                foreach ($filesInput as $index => $file) {
                    $saved = $this->suratFiles->simpanFileSurat($file, $mimeWhitelist);
                    $customName = trim((string) ($displayNames[$index] ?? ''));
                    if ($customName !== '') {
                        $saved['file_original_name'] = $this->suratFiles->terapkanNamaTampilan($saved, $customName);
                    }
                    $stored[] = $saved;
                }
            }
        } catch (\RuntimeException $e) {
            foreach ($stored as $s) {
                @unlink(config('suratapp.uploads_path').'/'.$s['file_name']);
            }
            throw new ApiException($e->getMessage(), 400);
        }

        $kasubditGerbangUserId = null;
        if ($jenis === 'masuk') {
            $deteksi = $this->bagService->deteksiKasubditUntukTurmin((int) $authUser->id);
            $kasubditGerbangUserId = $deteksi['id'] ?? null;
        }

        $namaPengajuValue = $namaPengaju === '' ? null : $namaPengaju;
        $kabagDitujuValue = $kabagDituju === '' ? null : $kabagDituju;
        $keteranganValue = $keterangan === '' ? null : $keterangan;
        $nomorSuratInsert = $jenis === 'keluar' ? null : $nomorSurat;
        $tanggalInputSistemValue = $jenis === 'masuk' ? $tanggalInputSistem : null;

        $surat = new Surat();
        $surat->jenis = $jenis;
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
            DB::table('surat_file')->insert([
                'surat_id' => $id,
                'urutan' => $index,
                'file_name' => $s['file_name'],
                'file_original_name' => $s['file_original_name'],
                'created_at' => now(),
            ]);
        }

        $approvalRoles = match ($jenis) {
            'keluar' => $anggotaJalurKeluar,
            'masuk' => ['Turmin', 'Kasubdit'],
            default => [],
        };

        $approvalDetail = [];
        foreach ($approvalRoles as $index => $role) {
            $urutan = $index + 1;
            $turminInputSendiri = $jenis === 'masuk' && $index === 0;
            $statusAwal = $turminInputSendiri ? 'disetujui' : 'menunggu';
            $catatanAwal = $turminInputSendiri ? "Surat diinput oleh $dibuatOleh." : null;
            $diprosesOlehAwal = $turminInputSendiri ? $dibuatOleh : null;
            $diprosesAtAwal = $turminInputSendiri ? now()->format('Y-m-d H:i:s') : null;

            DB::table('surat_approval')->insert([
                'surat_id' => $id,
                'urutan' => $urutan,
                'role' => $role,
                'status' => $statusAwal,
                'catatan' => $catatanAwal,
                'diproses_oleh' => $diprosesOlehAwal,
                'diproses_at' => $diprosesAtAwal,
            ]);
            $approvalDetail[] = [
                'urutan' => $urutan,
                'role' => $role,
                'status' => $statusAwal,
                'catatan' => $catatanAwal,
                'diproses_oleh' => $diprosesOlehAwal,
                'diproses_at' => $diprosesAtAwal,
            ];
        }

        $jenisLabel = $jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
        $labelNomorSurat = $nomorSuratInsert !== null ? "$jenisLabel $nomorSuratInsert" : $jenisLabel;
        ActivityLogger::log($request, null, $dibuatOleh, 'create', "$labelNomorSurat ($perihal)", $id);

        $baseUrl = BaseUrlResolver::resolve($request);
        $files = $this->suratFiles->getSuratFiles($id, $baseUrl, $authUser);

        return response()->json([
            'id' => $id,
            'jenis' => $jenis,
            'nomor_surat' => $nomorSuratInsert,
            'tanggal' => $tanggal,
            'tanggal_input_sistem' => $tanggalInputSistemValue,
            'perihal' => $perihal,
            'nama_pengaju' => $namaPengajuValue,
            'klasifikasi' => $klasifikasi,
            'disposisi' => null,
            'disposisi_detail' => [],
            'kabag_dituju' => $kabagDitujuValue,
            'approval_detail' => $approvalDetail,
            'keterangan' => $keteranganValue,
            'files' => $files,
        ]);
    }

    /** GET /surat_list.php */
    public function list(Request $request)
    {
        $authUser = $request->user();

        $status = $request->query('status');
        $jenis = $request->query('jenis');
        $disposisi = trim((string) $request->query('disposisi', ''));
        $tahap = trim((string) $request->query('tahap', ''));
        $peran = trim((string) $request->query('peran', ''));
        $diprosesOleh = trim((string) $request->query('diproses_oleh', ''));
        $kabagDituju = trim((string) $request->query('kabag_dituju', ''));
        $id = filter_var($request->query('id'), FILTER_VALIDATE_INT);
        $order = strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('surat');

        if ($id) {
            $query->where('id', $id);
        }
        if (in_array($status, ['menunggu', 'disetujui', 'ditolak'], true)) {
            $query->where('status', $status);
        }
        if (in_array($jenis, ['masuk', 'keluar'], true)) {
            $query->where('jenis', $jenis);
        }
        if ($kabagDituju !== '') {
            $query->where('kabag_dituju', $kabagDituju);
        }
        if ($disposisi !== '') {
            $query->whereExists(function ($q) use ($disposisi) {
                $q->select(DB::raw(1))->from('surat_disposisi as sd')
                    ->whereColumn('sd.surat_id', 'surat.id')->where('sd.role', $disposisi);
            });
        }
        if ($tahap !== '') {
            $query->whereExists(function ($q) use ($tahap) {
                $q->select(DB::raw(1))->from('surat_approval as sa')
                    ->whereColumn('sa.surat_id', 'surat.id')
                    ->where('sa.role', $tahap)->where('sa.status', 'menunggu')
                    ->whereRaw('sa.urutan = (SELECT MIN(sa2.urutan) FROM surat_approval sa2 WHERE sa2.surat_id = surat.id AND sa2.status = \'menunggu\')');
            });
        }
        if ($peran === 'Kasubdit') {
            $authUserId = (int) $authUser->id;
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('surat_approval as sa')
                    ->whereColumn('sa.surat_id', 'surat.id')->where('sa.role', 'Kasubdit');
            })->where(function ($q) use ($authUserId) {
                $q->whereNull('surat.kasubdit_gerbang_user_id')->orWhere('surat.kasubdit_gerbang_user_id', $authUserId);
            });
        } elseif ($peran !== '') {
            $query->whereExists(function ($q) use ($peran) {
                $q->select(DB::raw(1))->from('surat_approval as sa')
                    ->whereColumn('sa.surat_id', 'surat.id')->where('sa.role', $peran);
            });
        }
        if ($diprosesOleh !== '') {
            $query->whereExists(function ($q) use ($diprosesOleh) {
                $q->select(DB::raw(1))->from('surat_approval as sa4')
                    ->whereColumn('sa4.surat_id', 'surat.id')->where('sa4.diproses_oleh', $diprosesOleh);
            });
        }

        // Batas keamanan wajib -- akun bukan admin/pimpinan hanya boleh
        // melihat surat yang benar-benar melibatkan mereka.
        if (!in_array($authUser->role, ['admin', 'pimpinan'], true)) {
            $nama = $authUser->nama;
            $query->where(function ($q) use ($nama) {
                $q->whereExists(function ($sub) use ($nama) {
                    $sub->select(DB::raw(1))->from('surat_disposisi as sd2')
                        ->whereColumn('sd2.surat_id', 'surat.id')->where('sd2.role', $nama);
                })->orWhereExists(function ($sub) use ($nama) {
                    $sub->select(DB::raw(1))->from('surat_approval as sa2')
                        ->whereColumn('sa2.surat_id', 'surat.id')->where('sa2.role', $nama);
                })->orWhereExists(function ($sub) use ($nama) {
                    $sub->select(DB::raw(1))->from('surat_approval as sa3')
                        ->whereColumn('sa3.surat_id', 'surat.id')->where('sa3.diproses_oleh', $nama);
                });
            });
        }

        $rows = $query->orderBy('created_at', $order)->orderBy('id', $order)->get();

        $data = [];
        $masukIds = [];
        $allIds = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            if ($arr['jenis'] === 'masuk') {
                $masukIds[] = (int) $arr['id'];
            }
            $allIds[] = (int) $arr['id'];
            $data[(int) $arr['id']] = $arr;
        }

        $baseUrl = BaseUrlResolver::resolve($request);
        $filesBySurat = $this->suratFiles->getSuratFilesBatch($allIds, $baseUrl, $authUser);

        $disposisiBySurat = [];
        if ($masukIds) {
            foreach (DB::table('surat_disposisi')->whereIn('surat_id', $masukIds)->orderBy('id')->get() as $d) {
                $disposisiBySurat[$d->surat_id][] = [
                    'role' => $d->role,
                    'catatan' => $d->catatan,
                    'diproses_oleh' => $d->diproses_oleh,
                    'diproses_at' => $d->diproses_at,
                ];
            }
        }

        $approvalBySurat = [];
        if ($allIds) {
            foreach (DB::table('surat_approval')->whereIn('surat_id', $allIds)->orderBy('urutan')->get() as $a) {
                $approvalBySurat[$a->surat_id][] = [
                    'urutan' => (int) $a->urutan,
                    'role' => $a->role,
                    'status' => $a->status,
                    'instruksi' => $a->instruksi,
                    'catatan' => $a->catatan,
                    'diproses_oleh' => $a->diproses_oleh,
                    'diproses_at' => $a->diproses_at,
                ];
            }
        }

        foreach ($data as $suratId => &$row) {
            if ($row['jenis'] === 'masuk') {
                $row['disposisi_detail'] = $disposisiBySurat[$suratId] ?? [];
            }
            $row['approval_detail'] = $approvalBySurat[$suratId] ?? [];
            $row['files'] = $filesBySurat[$suratId] ?? [];
        }
        unset($row);

        return response()->json(array_values($data));
    }

    /** POST /surat_update_status.php */
    public function updateStatus(Request $request)
    {
        $authUser = $request->user();

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        $keputusan = (string) $request->input('status', '');
        $catatan = trim((string) $request->input('catatan', ''));
        $catatanValue = $catatan === '' ? null : $catatan;
        $diprosesOleh = $authUser->nama;

        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }
        if (!in_array($keputusan, ['disetujui', 'ditolak'], true)) {
            throw new ApiException('Status harus "disetujui" atau "ditolak"', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }

        if ($surat->jenis === 'masuk' && $authUser->role === 'turmin') {
            throw new ApiException('Akun Turmin hanya bisa melihat, tidak bisa memproses approval', 403);
        }

        $stage = DB::table('surat_approval')->where('surat_id', $id)->where('role', $diprosesOleh)->first();

        if (!$stage && $surat->jenis === 'masuk') {
            $stageKasubdit = DB::table('surat_approval')->where('surat_id', $id)->where('role', 'Kasubdit')
                ->where(function ($q) use ($diprosesOleh) {
                    $q->where('status', 'menunggu')->orWhere('diproses_oleh', $diprosesOleh);
                })->first();

            $kasubditGerbangUserId = $surat->kasubdit_gerbang_user_id === null ? null : (int) $surat->kasubdit_gerbang_user_id;
            $berhakKlaimMenunggu = $kasubditGerbangUserId === null
                ? $this->bagService->akunKasubditManapun((int) $authUser->id)
                : $kasubditGerbangUserId === (int) $authUser->id;

            if ($stageKasubdit && ($stageKasubdit->status !== 'menunggu' || $berhakKlaimMenunggu)) {
                $stage = $stageKasubdit;
            }
        }

        if (!$stage) {
            throw new ApiException('Anda bukan salah satu tahap approval pada surat ini', 403);
        }

        if ($surat->jenis === 'keluar' && $surat->status === 'disetujui' && $authUser->role !== 'pimpinan') {
            throw new ApiException('Surat sudah disetujui sepenuhnya, hanya pimpinan yang bisa mengubah keputusan sekarang', 403);
        }

        $disposisiBagIdsInput = $request->input('disposisi_bag_ids', []);
        $disposisiBagIds = [];
        if (is_array($disposisiBagIdsInput)) {
            foreach ($disposisiBagIdsInput as $v) {
                $bagId = filter_var($v, FILTER_VALIDATE_INT);
                if ($bagId) {
                    $disposisiBagIds[] = $bagId;
                }
            }
        }
        $disposisiList = [];

        $instruksiInput = $request->input('instruksi', []);
        $instruksiList = [];
        if (is_array($instruksiInput)) {
            $instruksiList = array_values(array_filter(array_map('trim', $instruksiInput), fn ($v) => $v !== ''));
        } elseif (is_string($instruksiInput) && trim($instruksiInput) !== '') {
            $instruksiList = array_values(array_filter(array_map('trim', explode(',', $instruksiInput)), fn ($v) => $v !== ''));
        }

        $isGateAkhirDisposisi = $surat->jenis === 'masuk' && $stage->role === 'Kasubdit';
        $instruksiValid = config('suratapp.instruksi_disposisi_valid');

        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            if (!$instruksiList) {
                throw new ApiException('Pilih minimal satu instruksi disposisi terlebih dahulu', 400);
            }
            foreach ($instruksiList as $kode) {
                if (!array_key_exists($kode, $instruksiValid)) {
                    throw new ApiException('Instruksi tidak valid: '.$kode, 400);
                }
            }
            foreach ($disposisiBagIds as $bagId) {
                if (!$this->bagService->bagMilikKasubdit($bagId, (int) $authUser->id)) {
                    throw new ApiException('Anda bukan Kasubdit Bag yang dipilih: '.$bagId, 403);
                }

                // Bag ini SUDAH diatur Kabag-nya -- surat berhenti dulu di
                // Kabag (isi disposisi + pilih anggota lewat checkbox,
                // lihat updateDisposisi()), TIDAK langsung diblast ke
                // seluruh anggota seperti sebelumnya.
                $kabagNama = $this->bagService->kabagNamaUntukBag($bagId);
                if ($kabagNama !== null) {
                    if (!in_array($kabagNama, $disposisiList, true)) {
                        $disposisiList[] = $kabagNama;
                    }

                    continue;
                }

                // Bag belum punya Kabag terdaftar -- perilaku lama tetap
                // berlaku, blast langsung ke seluruh anggota.
                $anggota = $this->bagService->anggotaBagUntukDisposisi($bagId);
                if (!$anggota) {
                    throw new ApiException('Bag tidak valid atau belum punya anggota: '.$bagId, 400);
                }
                foreach ($anggota as $a) {
                    if (!in_array($a['nama'], $disposisiList, true)) {
                        $disposisiList[] = $a['nama'];
                    }
                }
            }

            // Tembusan Manual (paritas dengan SuratReview::simpan() sisi
            // web) -- akun bebas pilih di luar struktur Bag. $nama DIAMBIL
            // ULANG dari DB via user_id (bukan dipakai langsung dari input
            // klien) supaya tidak bisa dipalsukan jadi nama akun lain.
            $tembusanUserIdsInput = $request->input('tembusan_user_ids', []);
            if (is_array($tembusanUserIdsInput)) {
                foreach ($tembusanUserIdsInput as $v) {
                    $userId = filter_var($v, FILTER_VALIDATE_INT);
                    if (!$userId) {
                        continue;
                    }
                    $u = User::query()->whereNotIn('role', ['admin', 'turmin'])->find($userId, ['nama']);
                    if ($u && !in_array($u->nama, $disposisiList, true)) {
                        $disposisiList[] = $u->nama;
                    }
                }
            }
        }

        $instruksiValue = ($isGateAkhirDisposisi && $keputusan === 'disetujui' && $instruksiList)
            ? implode(',', $instruksiList)
            : null;

        $adaSebelumBelumSetuju = DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)
            ->where('status', '!=', 'disetujui')->exists();

        if ($adaSebelumBelumSetuju) {
            throw new ApiException('Belum giliran Anda untuk memproses surat ini', 403);
        }

        $statusLama = $stage->status;
        $isRevisi = $statusLama !== 'menunggu';

        DB::table('surat_approval')->where('id', $stage->id)->update([
            'status' => $keputusan,
            'instruksi' => $instruksiValue,
            'catatan' => $catatanValue,
            'diproses_oleh' => $diprosesOleh,
            'diproses_at' => now(),
        ]);

        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            DB::table('surat_disposisi')->where('surat_id', $id)->delete();
            foreach ($disposisiList as $role) {
                DB::table('surat_disposisi')->insert(['surat_id' => $id, 'role' => $role]);
            }
            $disposisiValue = $disposisiList ? implode(',', $disposisiList) : null;
            DB::table('surat')->where('id', $id)->update(['disposisi' => $disposisiValue]);
        }

        $this->recomputeStatusSurat($id);

        $totalStages = DB::table('surat_approval')->where('surat_id', $id)->count();

        $labelLama = match ($statusLama) {
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            default => null,
        };
        $labelBaru = $keputusan === 'disetujui' ? 'Disetujui' : 'Ditolak';
        $verbaBaru = $keputusan === 'disetujui' ? 'menyetujui' : 'menolak';
        $jenisLabel = $surat->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
        $labelNomorSurat = $surat->nomor_surat ? "$jenisLabel {$surat->nomor_surat}" : $jenisLabel;
        $keterangan = "$labelNomorSurat ({$surat->perihal}): ".($isRevisi
            ? "{$stage->role} mengubah keputusan tahap {$stage->urutan}/$totalStages dari $labelLama menjadi $labelBaru"
            : "{$stage->role} $verbaBaru tahap {$stage->urutan}/$totalStages");
        if ($isGateAkhirDisposisi && $keputusan === 'disetujui') {
            $instruksiLabels = array_map(fn ($kode) => $instruksiValid[$kode] ?? $kode, $instruksiList);
            $keterangan .= ', instruksi: '.implode(', ', $instruksiLabels).($disposisiList
                ? ', diteruskan ke: '.implode(', ', $disposisiList)
                : ' (tanpa tujuan disposisi)');
        }

        ActivityLogger::log($request, null, $diprosesOleh, 'update', $keterangan, $id);

        return response()->json($this->detailResponse($id, $authUser));
    }

    /** POST /surat_update_disposisi.php */
    public function updateDisposisi(Request $request)
    {
        $authUser = $request->user();

        if ($authUser->role === 'turmin') {
            throw new ApiException('Akun Turmin hanya bisa melihat, tidak bisa mengisi disposisi', 403);
        }

        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        $role = trim((string) $request->input('role', ''));
        $catatan = trim((string) $request->input('catatan', ''));
        $diprosesOleh = $authUser->nama;

        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }
        if (!$this->bagService->namaValidUntukDisposisi($role)) {
            throw new ApiException('Pejabat tujuan disposisi tidak valid', 400);
        }
        if ($diprosesOleh !== $role) {
            throw new ApiException('Anda hanya boleh mengisi disposisi milik Anda sendiri', 403);
        }
        if ($catatan === '') {
            throw new ApiException('Isi Disposisi wajib diisi', 400);
        }

        $exists = DB::table('surat_disposisi as sd')
            ->join('surat as s', 's.id', '=', 'sd.surat_id')
            ->where('sd.surat_id', $id)->where('sd.role', $role)->where('s.jenis', 'masuk')
            ->exists();
        if (!$exists) {
            throw new ApiException('Surat masuk untuk pejabat ini tidak ditemukan', 404);
        }

        $gateCount = DB::table('surat_approval')->where('surat_id', $id)->count();
        if ($gateCount > 0) {
            $suratStatus = DB::table('surat')->where('id', $id)->value('status');
            if ($suratStatus !== 'disetujui') {
                throw new ApiException('Surat ini belum disetujui Turmin/Kasubditbinum, belum bisa diisi disposisinya', 403);
            }
        }

        DB::table('surat_disposisi')->where('surat_id', $id)->where('role', $role)->update([
            'catatan' => $catatan,
            'diproses_oleh' => $diprosesOleh,
            'diproses_at' => now(),
        ]);

        $nomorSurat0 = DB::table('surat')->where('id', $id)->value('nomor_surat');
        $perihal0 = DB::table('surat')->where('id', $id)->value('perihal');
        ActivityLogger::log(
            $request, null, $diprosesOleh, 'update',
            "Isi Disposisi $role - Surat Masuk $nomorSurat0 ($perihal0)", $id,
        );

        // Sama seperti App\Livewire\SuratReview::simpanDisposisi() -- kalau
        // $role ini akun Kabag, sekalian proses "teruskan ke anggota"
        // (opsional, dikirim client sebagai array anggota_terpilih berisi
        // user_id). Lihat BagService::teruskanDisposisiKabag().
        $anggotaTerpilihInput = $request->input('anggota_terpilih', []);
        $userIdsTerpilih = is_array($anggotaTerpilihInput)
            ? array_values(array_filter(array_map('intval', $anggotaTerpilihInput)))
            : [];
        if ($userIdsTerpilih) {
            $namaBaru = $this->bagService->teruskanDisposisiKabag($id, $role, $userIdsTerpilih);
            if ($namaBaru) {
                $suratRow = DB::table('surat')->where('id', $id)->first();
                $gabunganLama = $suratRow && $suratRow->disposisi ? explode(',', $suratRow->disposisi) : [];
                $gabunganBaru = array_values(array_unique([...$gabunganLama, ...$namaBaru]));
                DB::table('surat')->where('id', $id)->update(['disposisi' => implode(',', $gabunganBaru)]);

                ActivityLogger::log(
                    $request, null, $diprosesOleh, 'update',
                    "Kabag $role meneruskan Surat Masuk $nomorSurat0 ($perihal0) ke: ".implode(', ', $namaBaru), $id,
                );
            }
        }

        $row = $this->detailResponse($id, $authUser);

        return response()->json($row);
    }

    /** POST /surat_approval_lompat.php */
    public function approvalLompat(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang bisa diproses sebelum giliran', 400);
        }
        if ($surat->status !== 'menunggu') {
            throw new ApiException('Surat ini sudah selesai diproses', 403);
        }

        $stage = DB::table('surat_approval')->where('surat_id', $id)->where('role', $authUser->nama)->first();
        if (!$stage) {
            throw new ApiException('Anda bukan salah satu tahap approval pada surat ini', 403);
        }
        if ($stage->status !== 'menunggu') {
            throw new ApiException('Tahap Anda sudah pernah diproses sebelumnya', 403);
        }

        $tahapSebelumnya = DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)->where('status', 'menunggu')
            ->orderBy('urutan')->get();

        if ($tahapSebelumnya->isEmpty()) {
            throw new ApiException('Sudah giliran Anda, tidak perlu melompat tahap', 400);
        }

        $catatanAuto = "Dilewati -- otomatis disetujui karena {$authUser->nama} memproses tahapnya lebih dulu";
        $autoOleh = 'Sistem (dilewati)';
        DB::table('surat_approval')
            ->where('surat_id', $id)->where('urutan', '<', $stage->urutan)->where('status', 'menunggu')
            ->update(['status' => 'disetujui', 'catatan' => $catatanAuto, 'diproses_oleh' => $autoOleh, 'diproses_at' => now()]);

        $daftarTahap = $tahapSebelumnya->pluck('role')->implode(', ');
        ActivityLogger::log(
            $request, null, $authUser->nama, 'update',
            "Surat Keluar ({$surat->perihal}): {$authUser->nama} memproses tahap {$stage->urutan} "
                ."sebelum gilirannya, tahap $daftarTahap otomatis disetujui",
            $id,
        );

        return response()->json($this->detailResponse($id, $authUser));
    }

    /** POST /surat_approval_mundur.php */
    public function approvalMundur(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang punya alur ini', 400);
        }
        if ($surat->status !== 'menunggu') {
            throw new ApiException('Surat ini sudah selesai diproses', 403);
        }

        $approvalRows = DB::table('surat_approval')->where('surat_id', $id)->orderBy('urutan')->get();
        $tahapSaya = $approvalRows->firstWhere('role', $authUser->nama);

        if (!$tahapSaya) {
            throw new ApiException('Anda bukan salah satu tahap approval pada surat ini', 403);
        }
        if ($tahapSaya->status === 'menunggu') {
            throw new ApiException('Tahap Anda belum pernah diproses, tidak perlu diambil alih untuk revisi', 400);
        }

        foreach ($approvalRows as $row) {
            if ($row->urutan < $tahapSaya->urutan && $row->status !== 'disetujui') {
                throw new ApiException('Tahap sebelum Anda belum sepenuhnya disetujui', 403);
            }
        }

        DB::table('surat_approval')->where('surat_id', $id)->where('urutan', '>=', $tahapSaya->urutan)->update([
            'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);
        DB::table('surat')->where('id', $id)->update([
            'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);

        ActivityLogger::log(
            $request, null, $authUser->nama, 'update',
            "Surat Keluar ({$surat->perihal}): {$authUser->nama} mengambil alih untuk revisi, "
                ."rantai approval direset mulai tahap {$tahapSaya->urutan} ({$authUser->nama})",
            $id,
        );

        return response()->json($this->detailResponse($id, $authUser));
    }

    /** POST /surat_konfirmasi_kaur.php */
    public function konfirmasiKaur(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang punya alur ini', 400);
        }
        if (!in_array($surat->status, ['disetujui', 'ditolak'], true)) {
            throw new ApiException('Surat ini belum disetujui sepenuhnya ataupun ditolak', 403);
        }

        $stage1 = DB::table('surat_approval')->where('surat_id', $id)->where('urutan', 1)->first();
        if (!$stage1 || $stage1->role !== $authUser->nama) {
            throw new ApiException('Hanya pengaju pertama (Kaur) surat ini yang boleh mengonfirmasi/mengabaikan', 403);
        }

        DB::table('surat')->where('id', $id)->update(['konfirmasi_kaur_at' => now()]);

        $keterangan = $surat->status === 'ditolak'
            ? "Abaikan notifikasi Surat Keluar yang ditolak ({$surat->perihal}), tidak akan ditindaklanjuti"
            : "Konfirmasi penerimaan Surat Keluar yang sudah disetujui ({$surat->perihal})";
        ActivityLogger::log($request, null, $authUser->nama, 'update', $keterangan, $id);

        return response()->json($this->detailResponse($id, $authUser));
    }

    /** POST /surat_resubmit.php */
    public function resubmit(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang bisa diajukan ulang di sini', 400);
        }
        if ($surat->status !== 'ditolak') {
            throw new ApiException('Surat cuma bisa diajukan ulang saat statusnya Ditolak', 403);
        }

        $roles = DB::table('surat_approval')->where('surat_id', $id)->pluck('role')->all();
        if (!in_array($authUser->nama, $roles, true)) {
            throw new ApiException('Hanya pejabat di rantai approval surat ini yang boleh mengajukan ulang', 403);
        }

        DB::table('surat_approval')->where('surat_id', $id)->update([
            'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);
        DB::table('surat')->where('id', $id)->update([
            'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);

        ActivityLogger::log(
            $request, null, $authUser->nama, 'update',
            "Ajukan ulang Surat Keluar ({$surat->perihal}), rantai approval direset dari tahap pertama", $id,
        );

        return response()->json($this->suratFiles->suratDetailDenganFiles($id, $authUser));
    }

    /** POST /surat_delete_ditolak.php */
    public function deleteDitolak(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang bisa dihapus di sini', 400);
        }
        if ($surat->status !== 'ditolak') {
            throw new ApiException('Surat cuma bisa dihapus di sini saat statusnya Ditolak', 403);
        }

        $roles = DB::table('surat_approval')->where('surat_id', $id)->pluck('role')->all();
        if (!in_array($authUser->nama, $roles, true)) {
            throw new ApiException('Hanya pejabat di rantai approval surat ini yang boleh menghapusnya', 403);
        }

        $fileNames = DB::table('surat_file')->where('surat_id', $id)->pluck('file_name')->all();
        $perihal = $surat->perihal;

        DB::table('surat')->where('id', $id)->delete();

        ActivityLogger::log($request, null, $authUser->nama, 'delete', "Menghapus Surat Keluar yang ditolak ($perihal)");

        $this->hapusFileFisik($fileNames);

        return response()->json(['deleted' => 1]);
    }

    /** POST /surat_delete.php (admin, bulk) */
    public function destroy(Request $request)
    {
        $authUser = $request->user();
        if ($authUser->role !== 'admin') {
            throw new ApiException('Hanya Administrator yang bisa mengakses ini', 403);
        }

        $rawIds = $request->input('ids', []);
        if (!is_array($rawIds)) {
            $rawIds = [$rawIds];
        }
        $ids = [];
        foreach ($rawIds as $rawId) {
            $val = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($val && $val > 0) {
                $ids[] = $val;
            }
        }
        $ids = array_values(array_unique($ids));
        $dihapusOleh = $authUser->nama;

        if (!$ids) {
            throw new ApiException('Tidak ada ID surat yang valid', 400);
        }

        $suratRows = DB::table('surat')->whereIn('id', $ids)->get(['id', 'jenis', 'nomor_surat']);
        $deletedIds = $suratRows->pluck('id')->all();
        $nomorList = $suratRows->map(fn ($r) => $r->nomor_surat ?? "surat #{$r->id}")->all();

        $fileNames = $deletedIds ? DB::table('surat_file')->whereIn('surat_id', $deletedIds)->pluck('file_name')->all() : [];

        $deletedCount = DB::table('surat')->whereIn('id', $ids)->delete();

        ActivityLogger::log($request, null, $dihapusOleh, 'delete', "Menghapus $deletedCount surat: ".implode(', ', $nomorList));

        $this->hapusFileFisik($fileNames);

        return response()->json(['deleted' => $deletedCount]);
    }

    /** POST /surat_edit_reset_progress.php */
    public function editResetProgress(Request $request)
    {
        $authUser = $request->user();
        $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
        if (!$id) {
            throw new ApiException('ID surat tidak valid', 400);
        }

        $surat = DB::table('surat')->where('id', $id)->first();
        if (!$surat) {
            throw new ApiException('Surat tidak ditemukan', 404);
        }
        if ($surat->jenis !== 'keluar') {
            throw new ApiException('Hanya Surat Keluar yang punya alur ini', 400);
        }
        if ($surat->status !== 'menunggu') {
            throw new ApiException('Surat ini tidak sedang dalam proses approval', 403);
        }

        $approvalRows = DB::table('surat_approval')->where('surat_id', $id)->orderBy('urutan')->get();
        $tahapSaya = $approvalRows->firstWhere('role', $authUser->nama);

        $bolehEdit = false;
        if ($tahapSaya) {
            $bolehEdit = true;
            foreach ($approvalRows as $row) {
                if ($row->urutan < $tahapSaya->urutan && $row->status !== 'disetujui') {
                    $bolehEdit = false;
                    break;
                }
            }
        }

        if (!$bolehEdit) {
            throw new ApiException('Anda tidak berhak mengedit dokumen ini saat ini', 403);
        }

        DB::table('surat_approval')->where('surat_id', $id)->where('urutan', '>=', $tahapSaya->urutan)->update([
            'status' => 'menunggu', 'instruksi' => null, 'catatan' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);
        DB::table('surat')->where('id', $id)->update([
            'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
        ]);

        ActivityLogger::log(
            $request, null, $authUser->nama, 'update',
            "Edit dokumen Surat Keluar ({$surat->perihal}), rantai approval direset mulai tahap {$tahapSaya->urutan} ({$authUser->nama})",
            $id,
        );

        return response()->json($this->detailResponse($id, $authUser));
    }

    /** GET /surat_activity_log.php */
    public function activityLog(Request $request)
    {
        $suratId = filter_var($request->query('surat_id'), FILTER_VALIDATE_INT);
        if (!$suratId) {
            throw new ApiException('Parameter surat_id wajib diisi', 400);
        }

        $rows = DB::table('activity_log')->where('surat_id', $suratId)
            ->orderBy('waktu')->orderBy('id')
            ->get(['waktu', 'username', 'nama', 'aksi', 'keterangan']);

        return response()->json($rows->values());
    }

    /** GET /surat_progress_public.php (publik, tanpa login) */
    public function progressPublic(Request $request)
    {
        $perihal = trim((string) $request->query('perihal', ''));

        if (strlen($perihal) < 3) {
            return response()->json([]);
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

        return response()->json(array_values($data));
    }

    /**
     * Hitung ulang surat.status dari seluruh baris surat_approval --
     * dipanggil setiap kali ada tahap yang diproses/direvisi.
     */
    private function recomputeStatusSurat(int $suratId): void
    {
        $rows = DB::table('surat_approval')->where('surat_id', $suratId)->orderBy('urutan')->get();

        $ditolak = $rows->where('status', 'ditolak')->values();
        $menunggu = $rows->where('status', 'menunggu')->values();

        if ($ditolak->isNotEmpty()) {
            $final = $ditolak->sortByDesc(fn ($r) => (string) $r->diproses_at)->first();
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'ditolak',
                'catatan_proses' => $final->catatan,
                'diproses_oleh' => $final->diproses_oleh,
                'diproses_at' => $final->diproses_at,
            ]);
        } elseif ($menunggu->isNotEmpty()) {
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'menunggu', 'catatan_proses' => null, 'diproses_oleh' => null, 'diproses_at' => null,
            ]);
        } else {
            $final = $rows->last();
            DB::table('surat')->where('id', $suratId)->update([
                'status' => 'disetujui',
                'catatan_proses' => $final->catatan,
                'diproses_oleh' => $final->diproses_oleh,
                'diproses_at' => $final->diproses_at,
            ]);
        }
    }

    /** Ambil satu row surat + files + approval_detail (+ disposisi_detail kalau masuk), bentuk siap-JSON. */
    private function detailResponse(int $suratId, User $authUser): array
    {
        $row = (array) DB::table('surat')->where('id', $suratId)->first();

        $baseUrl = BaseUrlResolver::resolve(request());
        $row['files'] = $this->suratFiles->getSuratFiles($suratId, $baseUrl, $authUser);

        $row['approval_detail'] = DB::table('surat_approval')->where('surat_id', $suratId)->orderBy('urutan')
            ->get(['urutan', 'role', 'status', 'instruksi', 'catatan', 'diproses_oleh', 'diproses_at'])
            ->map(fn ($r) => (array) $r)->all();

        if ($row['jenis'] === 'masuk') {
            $row['disposisi_detail'] = DB::table('surat_disposisi')->where('surat_id', $suratId)->orderBy('id')
                ->get(['role', 'catatan', 'diproses_oleh', 'diproses_at'])
                ->map(fn ($r) => (array) $r)->all();
        }

        return $row;
    }

    private function hapusFileFisik(array $fileNames): void
    {
        $uploadsDir = config('suratapp.uploads_path');
        $previewCacheDir = config('suratapp.preview_cache_path');
        foreach ($fileNames as $fileName) {
            $path = $uploadsDir.'/'.$fileName;
            if (is_file($path)) {
                @unlink($path);
            }
            $cachedPdf = $previewCacheDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';
            if (is_file($cachedPdf)) {
                @unlink($cachedPdf);
            }
        }
    }
}
