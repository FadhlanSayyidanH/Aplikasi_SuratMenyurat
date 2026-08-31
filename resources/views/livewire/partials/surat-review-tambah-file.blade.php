{{--
    Tombol "Tambah File" polos -- dipakai baik sebagai footer tetap di bawah
    daftar file (status 'menunggu') maupun digabung ke dalam panel
    "Ajukan Ulang" (status 'ditolak'). Begitu file dipilih langsung diunggah
    (lihat SuratReview::updatedNewFiles()), tidak ada tombol konfirmasi
    terpisah -- cermin _tambahFileBaru() di Flutter.
--}}
<div>
    <input type="file" wire:model="newFiles" multiple accept=".docx,.pptx,.xlsx,.pdf,.jpg,.jpeg,.png" class="hidden" id="surat-tambah-file-input">
    <label
        for="surat-tambah-file-input"
        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background"
    >
        <svg wire:loading wire:target="newFiles" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <svg wire:loading.remove wire:target="newFiles" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span wire:loading.remove wire:target="newFiles">Tambah File</span>
        <span wire:loading wire:target="newFiles">Mengunggah...</span>
    </label>
    @if ($errorFile)
        <p class="mt-2 text-xs text-app-error">{{ $errorFile }}</p>
    @endif
</div>
