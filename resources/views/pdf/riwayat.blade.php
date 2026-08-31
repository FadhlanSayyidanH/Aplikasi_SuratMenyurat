<?php
/**
 * Isi & struktur mengikuti lib/utils/riwayat_pdf.dart (proyek Flutter lama):
 * judul, tabel metadata surat, tabel "Tahapan Approval", lalu tabel
 * "Riwayat Aktivitas". Satu tambahan yang sengaja dibuat berbeda dari versi
 * Flutter: tabel "Disposisi" untuk Surat Masuk (riwayat_pdf.dart TIDAK
 * merender tujuan disposisi sama sekali) -- lihat catatan deviasi di
 * SuratRiwayatPdfController.
 *
 * dompdf HANYA mendukung subset CSS 2.1 (tanpa flexbox/grid) -- layout di
 * bawah SENGAJA dibuat dengan tabel & block-level elements saja.
 */
$labelJenis = $surat->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';
$labelStatus = match ($surat->status) {
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    default => 'Menunggu',
};
$labelAksi = fn (string $aksi) => match ($aksi) {
    'create' => 'Dibuat',
    'update' => 'Diproses',
    'delete' => 'Dihapus',
    'login' => 'Login',
    'logout' => 'Logout',
    default => $aksi,
};
// $approval/$disposisi datang dari DB::table (query builder polos, bukan
// Eloquent) -- diproses_at karenanya berupa string mentah "Y-m-d H:i:s"
// atau null, BUKAN instance Carbon, jadi tidak bisa langsung ->format().
$fmtWaktu = fn (?string $waktu) => $waktu ? \Illuminate\Support\Carbon::parse($waktu)->format('d/m/Y H:i') : '-';
$instruksiValid = config('suratapp.instruksi_disposisi_valid');
$labelInstruksi = function (?string $instruksi) use ($instruksiValid) {
    if (!$instruksi) {
        return '-';
    }
    $kode = array_filter(array_map('trim', explode(',', $instruksi)));
    if (!$kode) {
        return '-';
    }

    return implode(', ', array_map(fn ($k) => $instruksiValid[$k] ?? $k, $kode));
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Riwayat {{ $labelJenis }}{{ $surat->nomor_surat ? ' - '.$surat->nomor_surat : '' }}</title>
<style>
    @page { margin: 28px 34px; }
    * { box-sizing: border-box; }
    body { font-family: 'Helvetica', 'DejaVu Sans', sans-serif; font-size: 10px; color: #1b1f1d; }

    .header { border-bottom: 2px solid #c9a227; padding-bottom: 8px; margin-bottom: 14px; }
    .header .brand { font-size: 9px; font-weight: bold; color: #0b3d24; letter-spacing: 0.5px; text-transform: uppercase; }
    .header .brand-sub { font-size: 8px; color: #6b7570; }
    .header h1 { margin: 6px 0 0; font-size: 16px; color: #0b3d24; }

    h2.section-title {
        font-size: 11px; color: #ffffff; background-color: #0b3d24;
        padding: 4px 8px; margin: 16px 0 8px; border-radius: 2px;
    }

    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.meta td { padding: 3px 4px; vertical-align: top; font-size: 10px; }
    table.meta td.label { width: 150px; font-weight: bold; color: #1b1f1d; }
    table.meta td.value { color: #1b1f1d; }

    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.grid th, table.grid td { border: 1px solid #d8ddda; padding: 4px 5px; font-size: 9px; text-align: left; vertical-align: top; }
    table.grid th { background-color: #145c34; color: #ffffff; font-weight: bold; }
    table.grid tr:nth-child(even) td { background-color: #f4f6f5; }

    .status-disetujui { color: #145c34; font-weight: bold; }
    .status-ditolak { color: #b3261e; font-weight: bold; }
    .status-menunggu { color: #8a6d00; font-weight: bold; }

    .empty-note { font-size: 9px; color: #6b7570; font-style: italic; margin: 0 0 8px; }

    .footer-note { margin-top: 18px; font-size: 8px; color: #6b7570; border-top: 1px solid #d8ddda; padding-top: 6px; }
</style>
</head>
<body>
    <div class="header">
        <div class="brand">CARAKA-BINUM &bull; Ditajenad TNI AD</div>
        <div class="brand-sub">Sistem Surat Menyurat</div>
        <h1>Riwayat Proses Surat</h1>
    </div>

    <h2 class="section-title">Data Surat</h2>
    <table class="meta">
        <tr>
            <td class="label">Nomor Surat</td>
            <td class="value">{{ $surat->nomor_surat ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Jenis</td>
            <td class="value">{{ $labelJenis }}</td>
        </tr>
        <tr>
            <td class="label">Perihal</td>
            <td class="value">{{ $surat->perihal }}</td>
        </tr>
        <tr>
            <td class="label">Klasifikasi</td>
            <td class="value">{{ $surat->klasifikasi ?: '-' }}</td>
        </tr>
        @if ($surat->jenis === 'masuk')
            <tr>
                <td class="label">Nama Pengirim</td>
                <td class="value">{{ $surat->nama_pengaju ?: '-' }}</td>
            </tr>
        @else
            <tr>
                <td class="label">Bagian Dituju</td>
                <td class="value">{{ $surat->kabag_dituju ?: '-' }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">{{ $surat->jenis === 'masuk' ? 'Tanggal Surat Diterbitkan' : 'Tanggal Surat' }}</td>
            <td class="value">{{ optional($surat->tanggal)->format('d/m/Y') ?? '-' }}</td>
        </tr>
        @if ($surat->jenis === 'masuk')
            <tr>
                <td class="label">Tanggal Masuk Sistem</td>
                <td class="value">{{ optional($surat->tanggal_input_sistem)->format('d/m/Y') ?? '-' }}</td>
            </tr>
        @endif
        @if ($surat->keterangan)
            <tr>
                <td class="label">Keterangan</td>
                <td class="value">{{ $surat->keterangan }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Status</td>
            <td class="value status-{{ $surat->status }}">{{ $labelStatus }}</td>
        </tr>
        @if ($surat->catatan_proses)
            <tr>
                <td class="label">Catatan Keputusan Akhir</td>
                <td class="value">{{ $surat->catatan_proses }}</td>
            </tr>
        @endif
    </table>

    <h2 class="section-title">Tahapan Approval</h2>
    @if ($approval->isEmpty())
        <p class="empty-note">Belum ada tahapan approval.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 20%;">Tahap</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 18%;">Diproses Oleh</th>
                    <th style="width: 14%;">Waktu</th>
                    @if ($surat->jenis === 'masuk')
                        <th style="width: 14%;">Instruksi</th>
                    @endif
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($approval as $tahap)
                    <tr>
                        <td>{{ $tahap->urutan }}</td>
                        <td>{{ $tahap->role }}</td>
                        <td class="status-{{ $tahap->status }}">
                            {{ match ($tahap->status) { 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', default => 'Menunggu' } }}
                        </td>
                        <td>{{ $tahap->diproses_oleh ?: '-' }}</td>
                        <td>{{ $fmtWaktu($tahap->diproses_at) }}</td>
                        @if ($surat->jenis === 'masuk')
                            <td>{{ $labelInstruksi($tahap->instruksi) }}</td>
                        @endif
                        <td>{{ $tahap->catatan ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($surat->jenis === 'masuk')
        <h2 class="section-title">Disposisi</h2>
        @if ($disposisi->isEmpty())
            <p class="empty-note">Belum ada tujuan disposisi.</p>
        @else
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 25%;">Tujuan</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 18%;">Diproses Oleh</th>
                        <th style="width: 16%;">Waktu</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($disposisi as $tujuan)
                        <tr>
                            <td>{{ $tujuan->role }}</td>
                            <td>{{ $tujuan->catatan ? 'Sudah direspon' : 'Belum direspon' }}</td>
                            <td>{{ $tujuan->diproses_oleh ?: '-' }}</td>
                            <td>{{ $fmtWaktu($tujuan->diproses_at) }}</td>
                            <td>{{ $tujuan->catatan ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2 class="section-title">Riwayat Aktivitas</h2>
    @if ($riwayat->isEmpty())
        <p class="empty-note">Belum ada riwayat aktivitas.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 16%;">Waktu</th>
                    <th style="width: 12%;">Aksi</th>
                    <th style="width: 18%;">Oleh</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($riwayat as $item)
                    <tr>
                        <td>{{ optional($item->waktu)->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ $labelAksi($item->aksi) }}</td>
                        <td>{{ $item->nama }}</td>
                        <td>{{ $item->keterangan ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-note">
        Dokumen ini dibuat otomatis oleh Sistem Surat Menyurat Ditajenad pada {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
