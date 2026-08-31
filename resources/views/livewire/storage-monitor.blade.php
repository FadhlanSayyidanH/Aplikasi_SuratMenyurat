{{-- Pemantauan kapasitas disk VPS: total/terpakai/tersisa (real-time),
     plus breakdown ukuran lampiran surat & database. --}}
<div>
    @php
        $used = $this->diskUsedBytes();
        $percent = $this->diskUsedPercent();
        $barColor = match (true) {
            $percent >= 90 => 'bg-app-error',
            $percent >= 70 => 'bg-gold',
            default => 'bg-primary-green',
        };
    @endphp

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-2xl text-xs text-text-muted">
            Kapasitas disk dibaca langsung dari server (real-time). Ukuran lampiran surat di-cache 5 menit supaya
            tidak menghitung ulang seluruh folder setiap kali halaman ini dibuka.
        </p>
        <button
            type="button" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh"
            class="shrink-0 rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-text-dark hover:bg-app-background disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="refresh">Segarkan</span>
            <span wire:loading wire:target="refresh">Memuat...</span>
        </button>
    </div>

    <div class="rounded-xl bg-app-surface p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-semibold text-text-dark">Kapasitas Disk VPS</p>
            <p class="text-sm text-text-muted">
                {{ $this->formatBytes($used) }} / {{ $this->formatBytes($diskTotalBytes) }} ({{ $percent }}%)
            </p>
        </div>

        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full {{ $barColor }} transition-all" style="width: {{ min($percent, 100) }}%"></div>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-text-muted">
            <span>Total: <span class="font-medium text-text-dark">{{ $this->formatBytes($diskTotalBytes) }}</span></span>
            <span>Terpakai: <span class="font-medium text-text-dark">{{ $this->formatBytes($used) }}</span></span>
            <span>Tersisa: <span class="font-medium text-text-dark">{{ $this->formatBytes($diskFreeBytes) }}</span></span>
        </div>

        @if ($percent >= 90)
            <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-app-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 9v4"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 17h.01"/>
                </svg>
                Kapasitas disk hampir penuh -- segera bersihkan atau tambah storage.
            </p>
        @elseif ($percent >= 70)
            <p class="mt-3 text-xs font-medium text-gold">Kapasitas disk mulai menipis, pantau berkala.</p>
        @endif
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-app-surface p-5 shadow-sm">
            <p class="font-semibold text-text-dark">Lampiran Surat</p>
            <p class="mt-1 text-2xl font-bold text-primary-green">{{ $this->formatBytes($uploadsSizeBytes) }}</p>
            <p class="mt-1 text-xs text-text-muted">
                Seluruh berkas lampiran (dokumen &amp; foto) di storage/app/uploads.
                @if ($uploadsSizeUpdatedAt)
                    Dihitung pada {{ \Illuminate\Support\Carbon::parse($uploadsSizeUpdatedAt)->format('d/m/Y H:i') }}.
                @endif
            </p>
        </div>

        <div class="rounded-xl bg-app-surface p-5 shadow-sm">
            <p class="font-semibold text-text-dark">Database</p>
            <p class="mt-1 text-2xl font-bold text-primary-green">{{ $this->formatBytes($databaseSizeBytes) }}</p>
            <p class="mt-1 text-xs text-text-muted">Total ukuran data &amp; index seluruh tabel aplikasi.</p>
        </div>
    </div>
</div>
