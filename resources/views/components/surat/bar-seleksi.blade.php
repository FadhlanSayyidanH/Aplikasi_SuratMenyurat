{{-- Bar "N surat dipilih" + tombol Hapus -- migrasi dari _BarSeleksi (surat_kategori_browser.dart). --}}
@props(['jumlah'])

@if ($jumlah > 0)
    <div class="mb-3 flex items-center gap-2 rounded-lg border border-primary-green/30 bg-primary-green/[0.08] px-3 py-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm-9 14-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8Z"/></svg>
        <span class="flex-1 text-xs font-semibold text-primary-green">{{ $jumlah }} surat dipilih</span>
        <button
            type="button"
            @click="confirm('Hapus {{ $jumlah }} surat terpilih beserta file PDF-nya? Tindakan ini tidak dapat dibatalkan.') && $wire.hapusTerpilih()"
            wire:loading.attr="disabled" wire:target="hapusTerpilih"
            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-app-error transition hover:bg-app-error/10 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="hapusTerpilih" class="flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Hapus
            </span>
            <span wire:loading wire:target="hapusTerpilih" class="flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Menghapus...
            </span>
        </button>
    </div>
@endif
