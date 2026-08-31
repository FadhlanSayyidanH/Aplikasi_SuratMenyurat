{{-- Hak Akses input Surat Masuk/Keluar per akun. Cermin hak_akses_screen.dart (proyek Flutter lama). --}}
<div>
    @if ($error)
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-4 py-2.5 text-sm text-app-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ $error }}
        </div>
    @endif

    <p class="mb-4 text-xs text-text-muted">
        Atur akun mana saja yang boleh menginput Surat Masuk dan/atau Surat Keluar. Bag (Manajemen Bag
        Surat Masuk/Keluar) sekarang hanya menentukan rantai proses/disposisi, tidak lagi memberi izin input.
    </p>

    <div class="relative mb-4 w-full sm:max-w-xs">
        <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
            type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau username..."
            class="w-full rounded-lg border border-gray-300 bg-app-surface py-2.5 pl-9 pr-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
        >
    </div>

    @if ($this->users->isEmpty())
        <p class="rounded-xl bg-app-surface p-8 text-center text-sm text-text-muted shadow-sm">Tidak ada user yang cocok</p>
    @else
        <div class="space-y-2.5">
            @foreach ($this->users as $user)
                @php
                    $labelRole = match ($user->role) {
                        'pimpinan' => 'Pimpinan',
                        'turmin' => 'Turmin',
                        default => 'User',
                    };
                @endphp
                <div wire:key="akses-{{ $user->id }}" class="flex flex-wrap items-center gap-4 rounded-xl bg-app-surface p-4 shadow-sm">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-text-dark">{{ $user->nama }}</p>
                        <p class="text-xs text-text-muted">{{ $user->username }} &middot; {{ $labelRole }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <span class="text-[11px] text-text-muted">Surat Masuk</span>
                        <button
                            type="button"
                            wire:click="toggleMasuk({{ $user->id }}, {{ $user->boleh_input_masuk ? 'false' : 'true' }})"
                            wire:loading.attr="disabled" wire:target="toggleMasuk({{ $user->id }}, {{ $user->boleh_input_masuk ? 'false' : 'true' }})"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-60 {{ $user->boleh_input_masuk ? 'bg-primary-green' : 'bg-gray-300' }}"
                        >
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $user->boleh_input_masuk ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <span class="text-[11px] text-text-muted">Surat Keluar</span>
                        <button
                            type="button"
                            wire:click="toggleKeluar({{ $user->id }}, {{ $user->boleh_input_keluar ? 'false' : 'true' }})"
                            wire:loading.attr="disabled" wire:target="toggleKeluar({{ $user->id }}, {{ $user->boleh_input_keluar ? 'false' : 'true' }})"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-60 {{ $user->boleh_input_keluar ? 'bg-primary-green' : 'bg-gray-300' }}"
                        >
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $user->boleh_input_keluar ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
