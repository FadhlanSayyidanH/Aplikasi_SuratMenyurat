{{--
    Bar/bubble satu baris surat -- migrasi dari lib/widgets/surat_bar_list.dart
    (Flutter, `_buildBar` + `_BadgeStatusDisposisi` + `_BadgeStatusKeluar` +
    `_KeteranganTanggalProses`). Dipakai berulang oleh dashboard.blade.php:
    daftar flat (Dashboard gabungan/Dashboard Surat Masuk/Keluar), hasil
    pencarian, folder browser (leaf klasifikasi), dan dropdown notifikasi.

    Props:
    - surat: App\Models\Surat (relasi disposisi/approval TIDAK dipakai di sini,
      seluruh keputusan Sudah/Belum Direspon sudah dihitung di komponen induk).
    - tanggalUrut: Carbon (Surat::tanggalUrut -- tanggal_input_sistem untuk
      surat masuk, tanggal untuk surat keluar).
    - sudahDirespon: bool|null -- null = tidak relevan (surat keluar).
    - selectable/selected: checkbox seleksi hapus massal (admin saja).
--}}
@props([
    'surat',
    'tanggalUrut',
    'sudahDirespon' => null,
    'selectable' => false,
    'selected' => false,
])

@php
    $isMasuk = $surat->jenis === 'masuk';
    [$statusKeluarClass, $statusKeluarLabel] = match ($surat->status) {
        'disetujui' => ['bg-secondary-green/10 text-secondary-green', 'Disetujui'],
        'ditolak' => ['bg-app-error/10 text-app-error', 'Ditolak'],
        default => ['bg-gold/10 text-gold', 'Sedang Proses'],
    };
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-xl border border-gray-300 bg-app-surface px-4 py-3.5 transition hover:border-primary-green/30']) }}>
    @if ($selectable)
        <input
            type="checkbox"
            wire:click="toggleSelectSurat({{ $surat->id }})"
            @checked($selected)
            onclick="event.stopPropagation()"
            class="mt-1.5 h-4 w-4 shrink-0 rounded border-gray-300 text-primary-green focus:ring-primary-green"
        >
    @endif

    <a href="{{ route('surat.review', $surat->id) }}" wire:navigate class="flex min-w-0 flex-1 items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $isMasuk ? 'bg-gold/15 text-gold' : 'bg-primary-green/15 text-primary-green' }}">
            @if ($isMasuk)
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8l9 6 9-6"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 17L17 7M17 7H8M17 7v9"/></svg>
            @endif
        </span>

        <span class="min-w-0 flex-1">
            <span class="line-clamp-2 block text-sm font-semibold text-text-dark">{{ $surat->perihal }}</span>

            <span class="mt-1.5 flex items-center gap-1.5 text-xs font-bold text-primary-green">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v3M16 4v3"/></svg>
                {{ $tanggalUrut->format('d/m/Y') }}
            </span>

            @if ($surat->nomor_surat)
                <span class="mt-1 block truncate text-xs text-text-muted">No. {{ $surat->nomor_surat }}</span>
            @endif

            @if ($surat->jenis === 'keluar' && $surat->diproses_at)
                <span class="mt-1 flex items-center gap-1.5 text-xs font-semibold {{ $surat->status === 'disetujui' ? 'text-secondary-green' : 'text-app-error' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        @if ($surat->status === 'disetujui')<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>@else<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>@endif
                    </svg>
                    {{ $surat->status === 'disetujui' ? 'Disetujui' : 'Ditolak' }} pada {{ $surat->diproses_at->format('d/m/Y H:i') }}
                </span>
            @elseif ($isMasuk && $sudahDirespon !== null)
                <span class="mt-1 flex items-center gap-1.5 text-xs font-semibold {{ $sudahDirespon ? 'text-secondary-green' : 'text-app-error' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        @if ($sudahDirespon)<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>@else<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>@endif
                    </svg>
                    {{ $sudahDirespon ? 'Sudah Direspon' : 'Belum Direspon' }}
                </span>
            @endif

            <span class="mt-2 flex flex-wrap gap-1.5">
                <span class="rounded-full bg-app-background px-2.5 py-1 text-[11px] font-medium text-text-dark">{{ $surat->klasifikasi ?? '-' }}</span>
                @if ($surat->jenis === 'keluar')
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusKeluarClass }}">{{ $statusKeluarLabel }}</span>
                @endif
            </span>
        </span>
    </a>

    <span class="mt-1.5 shrink-0 self-start text-text-muted">
        @isset($trailing)
            {{ $trailing }}
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        @endisset
    </span>
</div>
