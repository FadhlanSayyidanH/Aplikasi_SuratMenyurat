{{--
    Satu baris "Tahapan Approval" -- dipakai untuk rantai Surat Keluar
    maupun gerbang Turmin/Kasubdit Surat Masuk. Cermin _ApprovalStageTile
    di surat_review_screen.dart.

    Variabel: $tahap (row surat_approval), $aktif (bool).
--}}
@php
    $instruksiValid = config('suratapp.instruksi_disposisi_valid');

    $style = match ($tahap->status) {
        'disetujui' => ['dot' => 'bg-secondary-green', 'text' => 'text-secondary-green', 'border' => 'border-secondary-green/50'],
        'ditolak' => ['dot' => 'bg-app-error', 'text' => 'text-app-error', 'border' => 'border-app-error/50'],
        default => $aktif
            ? ['dot' => 'bg-gold', 'text' => 'text-gold', 'border' => 'border-gold/50']
            : ['dot' => 'bg-text-muted', 'text' => 'text-text-muted', 'border' => 'border-gray-200'],
    };

    $subtitle = match ($tahap->status) {
        'disetujui' => 'Disetujui'
            .($tahap->diproses_at ? ' pada '.\Illuminate\Support\Carbon::parse($tahap->diproses_at)->format('d/m/Y H:i') : '')
            .($tahap->instruksi ? ' · '.collect(explode(',', $tahap->instruksi))->map(fn ($k) => $instruksiValid[$k] ?? $k)->implode(', ') : ''),
        'ditolak' => 'Ditolak'.($tahap->diproses_at ? ' pada '.\Illuminate\Support\Carbon::parse($tahap->diproses_at)->format('d/m/Y H:i') : ''),
        default => $aktif ? 'Sedang menunggu keputusan' : 'Belum diproses',
    };

    // SENGAJA diproses_oleh (nama akun yang BENAR memproses), bukan role --
    // role gerbang Surat Masuk cuma label generik tetap ('Turmin'/'Kasubdit').
    // Fallback ke role kalau belum diproses, atau kalau dilewati otomatis
    // lewat "Ambil Alih" (diproses_oleh = 'Sistem (dilewati)').
    $namaTampil = ($tahap->diproses_oleh && $tahap->diproses_oleh !== 'Sistem (dilewati)') ? $tahap->diproses_oleh : $tahap->role;
@endphp
<div class="rounded-lg border {{ $style['border'] }} bg-app-surface p-3">
    <div class="flex items-start gap-2">
        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $style['dot'] }}"></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-text-dark">{{ $tahap->urutan }}. {{ $namaTampil }}</p>
            <p class="text-xs {{ $style['text'] }}">{{ $subtitle }}</p>
        </div>
    </div>
    @if ($tahap->catatan)
        <div class="ml-4.5 mt-2 rounded-lg bg-app-background p-2 text-xs leading-relaxed text-text-dark">{{ $tahap->catatan }}</div>
    @endif
</div>
