{{-- Header "< Kembali  Judul  [dropdown urutan]" -- migrasi dari _HeaderKembali (surat_kategori_browser.dart). --}}
@props(['title', 'kembaliAction'])

<div class="mb-3 flex items-center gap-2">
    <button type="button" wire:click="{{ $kembaliAction }}" class="flex shrink-0 items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-medium text-primary-green hover:bg-primary-green/10">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Kembali
    </button>
    <h3 class="min-w-0 flex-1 truncate text-[15px] font-bold text-text-dark">{{ $title }}</h3>
    {{ $slot ?? '' }}
</div>
