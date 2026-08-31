{{--
    Log Aktivitas (admin-only, read-only) -- cermin activity_log_screen.dart.
    Layar Flutter aslinya cuma daftar polos + Muat Ulang + Hapus Log (TIDAK
    ada filter apapun); di sini ditambah filter aksi/kata-kunci/rentang
    tanggal karena tabel web (bukan list mobile) -- lihat komentar di
    App\Livewire\ActivityLog\Index. Aturan bisnis "hapus log" tetap cermin
    persis ActivityLogController::clear().
--}}
<div>
    <div class="mx-auto max-w-3xl">
        {{-- Baris aksi atas: Muat Ulang, Hapus Log --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-text-dark">
                Log Aktivitas
                <span class="ml-1 font-normal text-text-muted">({{ $this->filteredLogs->count() }}{{ $this->filteredLogs->count() !== $this->logs->count() ? ' dari '.$this->logs->count() : '' }})</span>
            </h2>

            <div class="flex items-center gap-2">
                <button
                    type="button" wire:click="$refresh" wire:loading.attr="disabled" wire:target="$refresh"
                    class="flex items-center gap-1.5 rounded-full border border-gray-300 bg-app-surface px-3 py-1.5 text-xs font-semibold text-text-dark transition hover:bg-app-background disabled:opacity-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    Muat Ulang
                </button>

                <button
                    type="button" wire:click="clearLog"
                    wire:confirm="Seluruh catatan log aktivitas akan dihapus. Tindakan penghapusan ini sendiri akan tetap tercatat sebagai baris log baru setelahnya. Lanjutkan?"
                    wire:loading.attr="disabled" wire:target="clearLog"
                    @if ($this->logs->isEmpty()) disabled @endif
                    class="flex items-center gap-1.5 rounded-full border border-app-error/40 bg-app-surface px-3 py-1.5 text-xs font-semibold text-app-error transition hover:bg-app-error/5 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-.87 13.14A2 2 0 0 1 16.14 21H7.86a2 2 0 0 1-1.99-1.86L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    <span wire:loading.remove wire:target="clearLog">Hapus Log</span>
                    <span wire:loading wire:target="clearLog">Menghapus...</span>
                </button>
            </div>
        </div>

        {{-- Filter --}}
        <div class="mb-4 grid grid-cols-1 gap-2.5 rounded-xl bg-app-surface p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative lg:col-span-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input
                    type="text" wire:model.live.debounce.400ms="search"
                    placeholder="Cari nama, username, atau keterangan..."
                    class="w-full rounded-lg border border-gray-300 bg-app-surface py-2 pl-9 pr-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >
            </div>

            <select wire:model.live="aksiFilter" class="w-full rounded-lg border border-gray-300 bg-app-surface py-2 px-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                <option value="">Semua aksi</option>
                @foreach ($this->aksiTersedia as $aksi)
                    <option value="{{ $aksi }}">{{ \App\Livewire\ActivityLog\Index::AKSI_INFO[$aksi]['label'] ?? $aksi }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-1.5">
                <input type="date" wire:model.live="dateFrom" class="w-full min-w-0 rounded-lg border border-gray-300 bg-app-surface py-2 px-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                <span class="shrink-0 text-xs text-text-muted">s/d</span>
                <input type="date" wire:model.live="dateTo" class="w-full min-w-0 rounded-lg border border-gray-300 bg-app-surface py-2 px-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
            </div>

            @if ($search !== '' || $aksiFilter !== '' || $dateFrom !== '' || $dateTo !== '')
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="button" wire:click="resetFilter" class="text-xs font-semibold text-primary-green hover:underline">Reset filter</button>
                </div>
            @endif
        </div>

        {{-- Daftar --}}
        @if ($this->logs->isEmpty())
            <p class="py-12 text-center text-sm text-text-muted">Belum ada catatan log.</p>
        @elseif ($this->filteredLogs->isEmpty())
            <p class="py-12 text-center text-sm text-text-muted">Tidak ada catatan yang cocok dengan filter.</p>
        @else
            <div class="space-y-2">
                @foreach ($this->filteredLogs as $entry)
                    @php
                        $info = \App\Livewire\ActivityLog\Index::AKSI_INFO[$entry->aksi] ?? ['label' => $entry->aksi, 'text' => 'text-text-muted', 'bg' => 'bg-text-muted/12'];
                    @endphp
                    <div wire:key="log-{{ $entry->id }}" class="flex items-start gap-3 rounded-xl border border-gray-200 bg-app-surface p-3.5 shadow-sm">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $info['bg'] }} {{ $info['text'] }}">
                            @include('livewire.partials.activity-log-aksi-icon', ['aksi' => $entry->aksi])
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 truncate text-sm font-bold text-text-dark">{{ $entry->nama }}</p>
                                <span class="shrink-0 rounded-full {{ $info['bg'] }} {{ $info['text'] }} px-2 py-0.5 text-[11px] font-bold">{{ $info['label'] }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-text-muted">{{ $entry->waktu->format('d/m/Y H:i') }}</p>

                            @if ($entry->keterangan)
                                <p class="mt-1 text-[13px] text-text-dark">{{ $entry->keterangan }}</p>
                            @endif

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3.5 gap-y-1 text-xs text-text-muted">
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    {{ $entry->ip_address ?? 'IP tidak diketahui' }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                    {{ \App\Livewire\ActivityLog\Index::ringkasPerangkat($entry->user_agent) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
