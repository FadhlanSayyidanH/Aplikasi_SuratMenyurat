{{-- $terakhir tidak lagi diperlukan untuk garis pemisah -- kontainer pemanggil sudah pakai divide-y, tetap
     diterima di sini supaya signature tetap mengikuti padanan _ResumeBaris di surat_form_screen.dart. --}}
@props(['label', 'nilai', 'terakhir' => false])

<div class="flex gap-4 py-3">
    <div class="w-32 shrink-0 text-xs text-text-muted sm:w-36">{{ $label }}</div>
    <div class="min-w-0 flex-1 whitespace-pre-line text-sm font-semibold text-text-dark">
        {{ ($nilai === null || $nilai === '') ? '-' : $nilai }}
    </div>
</div>
