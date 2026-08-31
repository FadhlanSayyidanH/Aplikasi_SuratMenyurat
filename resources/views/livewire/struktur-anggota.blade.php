{{-- Struktur Anggota (jajaran tiap akun Pimpinan). Cermin struktur_anggota_screen.dart (proyek Flutter lama). --}}
<div>
    @if ($error && !$showPickerModal)
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-4 py-2.5 text-sm text-app-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ $error }}
        </div>
    @endif

    @if ($this->pimpinanList->isEmpty())
        <div class="rounded-xl bg-app-surface p-8 text-center text-sm text-text-muted shadow-sm">
            Belum ada akun role Pimpinan. Buat dulu lewat menu "User".
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->pimpinanList as $pimpinan)
                <div wire:key="pimpinan-{{ $pimpinan->id }}" class="overflow-hidden rounded-xl bg-app-surface shadow-sm" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left">
                        <div class="flex min-w-0 items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 12 22l-1.5-4-1.5 4-3.5-8.5"/></svg>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-text-dark">{{ $pimpinan->nama }} ({{ $pimpinan->username }})</p>
                                <p class="text-xs text-text-muted">{{ $pimpinan->anggota->count() }} anggota jajaran</p>
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-text-muted transition" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    <div x-show="open" x-cloak x-transition>
                        <div class="border-t border-gray-100">
                            @forelse ($pimpinan->anggota as $a)
                                <div wire:key="anggota-{{ $a->id }}" class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-green/10 text-primary-green">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-text-dark">{{ $a->nama }}</p>
                                        <p class="truncate text-xs text-text-muted">{{ $a->username }}</p>
                                    </div>
                                    <button
                                        type="button" wire:click="hapusAnggota({{ $a->id }})"
                                        wire:confirm="{{ $a->nama }} ({{ $a->username }}) akan dikeluarkan dari jajaran ini. Lanjutkan?"
                                        title="Keluarkan dari jajaran" class="shrink-0 text-text-muted hover:text-app-error"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            @empty
                                <p class="px-4 py-3 text-xs text-text-muted">Belum ada anggota jajaran.</p>
                            @endforelse

                            <div class="px-4 py-2.5">
                                <button
                                    type="button" wire:click="openPicker({{ $pimpinan->id }}, @js($pimpinan->nama))"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-green hover:text-secondary-green"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
                                    Tambah Anggota
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Dialog Tambah Anggota --}}
    @if ($showPickerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-8" wire:key="picker-backdrop">
            <div class="w-full max-w-md rounded-2xl bg-app-surface p-6 shadow-xl" @click.outside="$wire.closePicker()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">Tambah Anggota &mdash; {{ $pickerPimpinanNama }}</h2>
                    <button type="button" wire:click="closePicker" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if ($error)
                    <div class="mb-3 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">
                        {{ $error }}
                    </div>
                @endif

                <div class="relative mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        type="text" wire:model.live.debounce.300ms="pickerSearch" autofocus placeholder="Cari nama atau username"
                        class="w-full rounded-lg border border-gray-300 bg-app-background py-2.5 pl-9 pr-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                    >
                </div>

                <div class="max-h-80 overflow-y-auto">
                    @if ($this->kandidat->isEmpty())
                        <p class="py-6 text-center text-sm text-text-muted">Tidak ada user yang cocok</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($this->kandidat as $u)
                                <li wire:key="kandidat-{{ $u->id }}">
                                    <button
                                        type="button" wire:click="tambahAnggota({{ $u->id }})"
                                        class="flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left hover:bg-app-background"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-text-dark">{{ $u->nama }}</p>
                                            <p class="truncate text-xs text-text-muted">{{ $u->username }}</p>
                                        </div>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <button type="button" wire:click="closePicker" class="mt-4 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
            </div>
        </div>
    @endif
</div>
