{{--
    Bar/bubble folder (Bulan atau Klasifikasi) -- migrasi dari
    lib/widgets/kategori_bar.dart. Checkbox tristate (true/false/null)
    mewakili status seleksi seluruh surat di dalam folder ini, dipakai fitur
    hapus massal (admin saja).

    Props:
    - label, jumlah: teks & jumlah surat di dalam folder.
    - adaBelumDirespon: tampilkan penanda titik merah di ikon + ikon peringatan.
    - showCheckbox/checkboxValue: seleksi massal.
    - ids: array id surat di dalam folder ini (dipakai checkbox toggle-group).
    - openAction: nama method Livewire dipanggil saat folder dibuka (mis. 'bukaBulan').
    - openArgs: argumen posisional untuk $openAction.
--}}
@props([
    'label',
    'jumlah',
    'icon' => 'folder', // 'folder' (bulan) | 'category' (klasifikasi)
    'adaBelumDirespon' => false,
    'showCheckbox' => false,
    'checkboxValue' => false,
    'ids' => [],
    'openAction',
    'openArgs' => [],
])

@php
    $argsJson = collect($openArgs)->map(fn ($a) => json_encode($a))->implode(',');
    $idsJson = json_encode(array_values($ids));
@endphp

<div
    wire:click="{{ $openAction }}({{ $argsJson }})"
    {{ $attributes->class(['flex cursor-pointer items-center gap-3 rounded-xl border border-gray-300 bg-app-surface px-4 py-3.5 transition hover:border-primary-green/40 hover:bg-primary-green/5']) }}
>
    @if ($showCheckbox)
        <input
            type="checkbox"
            wire:click="toggleSelectGroup({{ $idsJson }})"
            onclick="event.stopPropagation()"
            @checked($checkboxValue === true)
            x-data x-effect="$el.indeterminate = @js($checkboxValue === null)"
            class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-green focus:ring-primary-green"
        >
    @endif

    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-green/10 text-primary-green">
        @if ($adaBelumDirespon)
            <span class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-app-surface bg-app-error"></span>
        @endif
        @if ($icon === 'category')
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
        @endif
    </span>

    <span class="min-w-0 flex-1 truncate text-[15px] font-semibold text-text-dark">{{ $label }}</span>

    @if ($adaBelumDirespon)
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-app-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    @endif

    <span class="shrink-0 rounded-full bg-app-background px-2.5 py-1 text-[11px] font-medium text-text-dark">{{ $jumlah }} surat</span>

    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
</div>
