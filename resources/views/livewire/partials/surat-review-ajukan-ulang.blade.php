{{-- Panel merah "Surat ditolak" -- Tambah File + Ajukan Ulang + Hapus Surat. Cermin _buildAjukanUlangPanel() di Flutter. --}}
<div class="mb-4 rounded-xl border border-app-error/30 bg-app-error/5 p-4">
    <div class="flex items-center gap-2 text-sm font-bold text-app-error">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Surat ini ditolak -- perbaiki dokumennya lalu ajukan ulang</span>
    </div>
    <p class="mt-2.5 text-xs text-text-muted">
        Tambah file lampiran di sini (atau hapus/ubah nama lewat daftar file di atas), lalu tekan "Ajukan Ulang" kalau dokumen sudah siap diproses lagi.
    </p>

    <div class="mt-3">
        @include('livewire.partials.surat-review-tambah-file')
    </div>

    @if ($errorAjukanUlang)
        <p class="mt-3 text-sm text-app-error">{{ $errorAjukanUlang }}</p>
    @endif

    <button
        type="button" wire:click="ajukanUlang"
        wire:confirm="Rantai approval akan direset dari tahap pertama -- seluruh pejabat di jalur ini akan memproses surat ini lagi dari awal dengan dokumen yang sekarang. Pastikan dokumen sudah diperbaiki sebelum melanjutkan."
        wire:loading.attr="disabled" wire:target="ajukanUlang"
        class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-primary-green px-4 py-3 text-sm font-semibold text-white hover:bg-secondary-green disabled:opacity-60"
    >
        <svg wire:loading wire:target="ajukanUlang" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        Ajukan Ulang
    </button>
    <button
        type="button" wire:click="hapusSurat"
        wire:confirm="Surat yang ditolak ini beserta dokumennya akan dihapus PERMANEN dan tidak bisa dikembalikan. Gunakan ini kalau surat ini memang sudah tidak perlu diproses lagi -- kalau masih ingin diperbaiki, pakai &quot;Ajukan Ulang&quot; saja."
        wire:loading.attr="disabled" wire:target="hapusSurat"
        class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border border-app-error px-4 py-3 text-sm font-semibold text-app-error hover:bg-app-error/5 disabled:opacity-60"
    >
        Hapus Surat
    </button>
</div>
