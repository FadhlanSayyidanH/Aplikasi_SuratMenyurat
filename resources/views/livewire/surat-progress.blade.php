{{--
    Halaman publik (TANPA login) "Progres Surat" -- migrasi dari
    lib/screens/surat_progress_screen.dart (pencarian) +
    lib/screens/surat_progress_detail_screen.dart (detail, sengaja
    digabung jadi satu "layar" lewat $selectedId, lihat SuratProgress.php).
--}}
<div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-10 sm:px-6" wire:poll.8s>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('landing') }}" wire:navigate class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-app-surface text-primary-green shadow-sm hover:bg-app-background" title="Kembali ke beranda">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-lg font-bold text-text-dark">Progres Surat Keluar</h1>
            <p class="text-xs text-text-muted">Pelacakan publik, tidak perlu login</p>
        </div>
    </div>

    @php $item = $this->selected(); @endphp

    @if ($item)
        {{-- ================= Detail ================= --}}
        @php
            $tahapAktif = $this->tahapAktif($item);
            $tanggal = \Illuminate\Support\Carbon::parse($item['tanggal']);
        @endphp

        <button type="button" wire:click="kembali" class="mb-4 inline-flex w-fit items-center gap-1.5 text-sm font-medium text-primary-green hover:underline">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali ke hasil pencarian
        </button>

        <div class="overflow-hidden rounded-2xl bg-app-surface shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-bold leading-snug text-text-dark">{{ $item['perihal'] }}</h2>
                <p class="mt-1 text-xs text-text-muted">
                    @if ($item['nomor_surat']) No. {{ $item['nomor_surat'] }} &bull; @endif
                    {{ $item['klasifikasi'] ?? '-' }} &bull; {{ $tanggal->format('d/m/Y') }}
                </p>
            </div>

            <div class="space-y-5 px-5 py-5">
                <div class="flex items-start gap-2.5 rounded-lg border border-gray-200 bg-app-background p-3.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p class="text-xs text-text-muted">File PDF surat hanya bisa dilihat oleh petugas yang login.</p>
                </div>

                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Status</h3>
                    <div class="w-full rounded-lg border border-gray-200 bg-app-background p-3">
                        <p class="text-sm leading-relaxed text-text-dark">
                            {{ match ($item['status']) {
                                'disetujui' => 'Disetujui',
                                'ditolak' => 'Ditolak',
                                default => $tahapAktif ? 'Sedang Proses -- menunggu keputusan '.$tahapAktif['role'] : 'Sedang Proses',
                            } }}
                        </p>
                    </div>
                </div>

                @if (!empty($item['approval_detail']))
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Tahapan Approval</h3>
                        <div class="space-y-2">
                            @foreach ($item['approval_detail'] as $tahap)
                                @php
                                    $aktif = $tahapAktif && $tahap['urutan'] === $tahapAktif['urutan'];
                                    // Kelas literal (bukan disusun lewat interpolasi) supaya scanner JIT
                                    // Tailwind v4 menemukan dan men-generate class-nya saat build.
                                    [$borderClass, $textClass, $iconPath, $subtitle] = match (true) {
                                        $tahap['status'] === 'disetujui' => [
                                            'border-gray-200', 'text-secondary-green',
                                            '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                                            $tahap['diproses_at'] ? 'Disetujui pada '.\Illuminate\Support\Carbon::parse($tahap['diproses_at'])->format('d/m/Y H:i') : 'Disetujui',
                                        ],
                                        $tahap['status'] === 'ditolak' => [
                                            'border-gray-200', 'text-app-error',
                                            '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
                                            $tahap['diproses_at'] ? 'Ditolak pada '.\Illuminate\Support\Carbon::parse($tahap['diproses_at'])->format('d/m/Y H:i') : 'Ditolak',
                                        ],
                                        $aktif => [
                                            'border-gold/50', 'text-gold',
                                            '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                                            'Sedang menunggu keputusan',
                                        ],
                                        default => [
                                            'border-gray-200', 'text-text-muted',
                                            '<circle cx="12" cy="12" r="9"/>',
                                            'Belum diproses',
                                        ],
                                    };
                                    $catatan = trim((string) ($tahap['catatan'] ?? ''));
                                @endphp
                                <div class="rounded-lg border {{ $borderClass }} bg-app-surface px-3.5 py-3" wire:key="tahap-{{ $tahap['urutan'] }}">
                                    <div class="flex items-center gap-2.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $textClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $iconPath !!}</svg>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-text-dark">{{ $tahap['urutan'] }}. {{ $tahap['role'] }}</p>
                                            <p class="text-xs {{ $textClass }}">{{ $subtitle }}</p>
                                        </div>
                                    </div>
                                    @if ($catatan !== '')
                                        <div class="ml-7 mt-2 rounded-lg bg-app-background p-2.5">
                                            <p class="text-xs leading-relaxed text-text-dark">{{ $catatan }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- ================= Pencarian ================= --}}
        <div class="rounded-2xl border border-gray-200 bg-app-surface p-6 shadow-sm">
            <h2 class="text-base font-bold text-text-dark">Cek Progres Surat Keluar</h2>
            <p class="mt-1 text-xs text-text-muted">
                Ketik perihal surat keluar untuk melihat sudah sampai tahap mana surat tersebut diproses --
                hasil muncul otomatis sambil mengetik.
            </p>

            <div class="relative mt-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="perihal"
                    placeholder="Perihal surat..."
                    autocomplete="off"
                    class="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-9 text-sm text-text-dark focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >
                @if ($perihal !== '')
                    <button type="button" wire:click="$set('perihal', '')" title="Hapus pencarian" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        </div>

        @php
            $perihalTrim = trim($perihal);
            $sudahMencari = mb_strlen($perihalTrim) >= 3;
            $hasil = $this->results();
        @endphp

        <div class="mt-5" wire:loading.class="opacity-60" wire:target="perihal">
            @if ($perihalTrim !== '' && !$sudahMencari)
                <p class="py-2 text-center text-xs text-text-muted">Ketik minimal 3 karakter untuk mulai mencari.</p>
            @elseif ($sudahMencari)
                @if (empty($hasil))
                    <div class="flex flex-col items-center py-10 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/><path d="M8 8l6 6M14 8l-6 6"/></svg>
                        <p class="mt-3 text-sm text-text-muted">Surat dengan perihal tersebut tidak ditemukan.</p>
                    </div>
                @else
                    <div class="mb-2.5 flex items-center gap-1.5">
                        <span class="text-xs text-text-muted">{{ count($hasil) }} surat ditemukan</span>
                        <span class="h-1.5 w-1.5 rounded-full bg-secondary-green"></span>
                        <span class="text-xs font-semibold text-secondary-green">Live</span>
                    </div>

                    <div class="space-y-2.5">
                        @foreach ($hasil as $row)
                            @php
                                [$statusClass, $statusLabel] = match ($row['status']) {
                                    'disetujui' => ['bg-secondary-green/10 text-secondary-green', 'Disetujui'],
                                    'ditolak' => ['bg-app-error/10 text-app-error', 'Ditolak'],
                                    default => ['bg-gold/10 text-gold', 'Sedang Proses'],
                                };
                                $tanggalRow = \Illuminate\Support\Carbon::parse($row['tanggal']);
                            @endphp
                            <button
                                type="button" wire:click="pilih({{ $row['id'] }})" wire:key="surat-{{ $row['id'] }}"
                                class="flex w-full items-start gap-3 rounded-xl border border-gray-200 bg-app-surface px-4 py-3.5 text-left transition hover:border-primary-green/30"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-green/15 text-primary-green">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 17L17 7M17 7H8M17 7v9"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-2 block text-sm font-semibold text-text-dark">{{ $row['perihal'] }}</span>
                                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-text-muted">
                                        {{ $tanggalRow->format('d/m/Y') }}
                                        @if ($row['nomor_surat']) &bull; No. {{ $row['nomor_surat'] }} @endif
                                    </span>
                                    <span class="mt-2 flex flex-wrap gap-1.5">
                                        <span class="rounded-full bg-app-background px-2.5 py-1 text-[11px] font-medium text-text-dark">{{ $row['klasifikasi'] ?? '-' }}</span>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="mt-1.5 h-4.5 w-4.5 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
