{{--
    Manajemen Bag Surat Masuk & Surat Keluar (admin) -- migrasi dari
    lib/screens/bag_management_screen.dart (proyek Flutter lama). $mode
    ('masuk'/'keluar', lihat BagManagementBase) menentukan SELURUH tampilan
    di bawah -- field yang muncul (Turmin/Kasubdit/Penerima Disposisi vs
    Kelompok Kasi), daftar anggota yang dipakai, & badge "Jalur Keluar".

    Drag-and-drop reorder anggota (bag_member datar & bag_disposisi_anggota)
    diimplementasi murni dengan Alpine + native HTML5 drag events (tanpa
    library eksternal) -- lihat blok <ul x-data ...> di bawah. Reorder Kaur
    di dalam satu grup Kasi SENGAJA tidak didukung (sama seperti Flutter,
    lihat komentar _KasiGrupCard di file aslinya) -- urutannya cuma dipakai
    tampilan, tidak memengaruhi rantai approval.
--}}
<div>
    @php $khususKeluar = $mode === 'keluar'; @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-text-muted">
            {{ $khususKeluar
                ? 'Kelola Bag jalur approval Surat Keluar beserta anggota & Kelompok Kasi-nya.'
                : 'Kelola Bag gerbang disposisi Surat Masuk beserta Akun Turmin/Kasubdit & penerima disposisinya.' }}
        </p>
        <div class="flex shrink-0 items-center gap-2">
            <button
                type="button" wire:click="reload" wire:loading.attr="disabled" wire:target="reload"
                class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-dark hover:bg-app-background disabled:opacity-50"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Muat Ulang
            </button>
            <button
                type="button" wire:click="openTambahBag"
                class="flex items-center gap-1.5 rounded-lg bg-primary-green px-3 py-1.5 text-xs font-semibold text-white hover:bg-secondary-green"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Bag
            </button>
        </div>
    </div>

    @if ($error)
        <div class="mb-4 flex items-center justify-between gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-4 py-2.5 text-sm text-app-error">
            <span>{{ $error }}</span>
            <button type="button" wire:click="$set('error', null)" class="shrink-0 text-app-error/70 hover:text-app-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    @endif

    @if (empty($bags))
        <div class="rounded-xl bg-app-surface p-10 text-center shadow-sm">
            <p class="text-sm text-text-muted">
                {{ $khususKeluar
                    ? 'Belum ada Bag jalur Surat Keluar. Tekan "Tambah Bag" untuk membuat.'
                    : 'Belum ada Bag. Tekan "Tambah Bag" untuk membuat.' }}
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($bags as $bag)
                @php
                    $sumberAnggota = $khususKeluar ? $bag['anggota'] : $bag['anggota_masuk'];
                    $terurut = collect($sumberAnggota)->sortBy('urutan')->values()->all();
                    $hapusMethod = $khususKeluar ? 'hapusAnggota' : 'hapusAnggotaMasuk';
                    $reorderMethod = $khususKeluar ? 'reorderAnggota' : 'reorderAnggotaMasuk';
                    $tambahAction = $khususKeluar ? 'anggota' : 'anggota_masuk';
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-app-surface shadow-sm" wire:key="bag-{{ $bag['id'] }}" x-data="{ open: false }">
                    <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left" @click="open = !open">
                        <div class="flex min-w-0 items-center gap-2">
                            <p class="truncate text-sm font-semibold text-text-dark">{{ $bag['nama'] }}</p>
                            @if ($khususKeluar)
                                <span class="shrink-0 rounded-full bg-primary-green/10 px-2 py-0.5 text-[11px] font-semibold text-primary-green">Jalur Keluar</span>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-xs text-text-muted">{{ count($sumberAnggota) }} anggota</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-text-muted transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-gray-100 px-4 py-3">
                        @unless ($khususKeluar)
                            <div class="mb-3 space-y-2.5 border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] text-text-muted">Akun Turmin</p>
                                        <p class="truncate text-sm {{ $bag['turmin'] ? 'font-medium text-text-dark' : 'italic text-text-muted' }}">
                                            {{ $bag['turmin'] ? "{$bag['turmin']['nama']} ({$bag['turmin']['username']})" : 'Belum diatur' }}
                                        </p>
                                    </div>
                                    <button type="button" wire:click="openPosisi({{ $bag['id'] }}, 'turmin')" class="shrink-0 text-xs font-semibold text-primary-green hover:underline">Ubah</button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4.5 8-11V5l-8-3-8 3v6c0 6.5 8 11 8 11z"/><polyline points="9 12 11 14 15 10"/></svg>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] text-text-muted">Akun Kasubdit</p>
                                        <p class="truncate text-sm {{ $bag['kasubdit'] ? 'font-medium text-text-dark' : 'italic text-text-muted' }}">
                                            {{ $bag['kasubdit'] ? "{$bag['kasubdit']['nama']} ({$bag['kasubdit']['username']})" : 'Belum diatur' }}
                                        </p>
                                    </div>
                                    <button type="button" wire:click="openPosisi({{ $bag['id'] }}, 'kasubdit')" class="shrink-0 text-xs font-semibold text-primary-green hover:underline">Ubah</button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] text-text-muted">Akun Kabag</p>
                                        <p class="truncate text-sm {{ $bag['kabag'] ? 'font-medium text-text-dark' : 'italic text-text-muted' }}">
                                            {{ $bag['kabag'] ? "{$bag['kabag']['nama']} ({$bag['kabag']['username']})" : 'Belum diatur (opsional -- kalau kosong, disposisi Kasubdit langsung ke semua Penerima Disposisi di bawah)' }}
                                        </p>
                                    </div>
                                    <button type="button" wire:click="openPosisi({{ $bag['id'] }}, 'kabag')" class="shrink-0 text-xs font-semibold text-primary-green hover:underline">Ubah</button>
                                </div>
                                <p class="flex items-center gap-2 pt-1 text-[11px] font-semibold uppercase tracking-wide text-text-muted">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Penerima Disposisi
                                </p>
                            </div>
                        @endunless

                        @if (empty($terurut))
                            <p class="py-2 text-xs italic text-text-muted">Belum ada anggota.</p>
                        @else
                            <ul
                                x-data="{ dragEl: null }"
                                @dragstart="dragEl = $event.target.closest('[data-drag-item]')"
                                @dragover.prevent="
                                    if (dragEl) {
                                        const target = $event.target.closest('[data-drag-item]');
                                        if (target && target !== dragEl) {
                                            const rect = target.getBoundingClientRect();
                                            const before = ($event.clientY - rect.top) < rect.height / 2;
                                            target.parentNode.insertBefore(dragEl, before ? target : target.nextSibling);
                                        }
                                    }
                                "
                                @drop.prevent="
                                    const ids = Array.from($el.querySelectorAll('[data-drag-item]')).map(li => li.dataset.id);
                                    $wire.{{ $reorderMethod }}({{ $bag['id'] }}, ids);
                                    dragEl = null;
                                "
                                @dragend="dragEl = null"
                                class="-mx-1 divide-y divide-gray-100"
                            >
                                @foreach ($terurut as $i => $a)
                                    <li
                                        data-drag-item data-id="{{ $a['id'] }}" draggable="true"
                                        wire:key="anggota-{{ $mode }}-{{ $bag['id'] }}-{{ $a['id'] }}"
                                        class="flex cursor-grab select-none items-center gap-3 rounded-lg px-1 py-2 active:cursor-grabbing"
                                    >
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-green/10 text-xs font-semibold text-primary-green">{{ $i + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-text-dark">{{ $a['nama'] }}</p>
                                            <p class="truncate text-xs text-text-muted">{{ $a['username'] }}</p>
                                        </div>
                                        <button
                                            type="button" wire:click="{{ $hapusMethod }}({{ $a['id'] }})"
                                            wire:confirm="{{ $a['nama'] }} ({{ $a['username'] }}) akan {{ $khususKeluar ? 'dikeluarkan dari Bag ini' : 'berhenti menerima disposisi Surat Masuk Bag ini' }}. Lanjutkan?"
                                            title="Keluarkan dari Bag" class="shrink-0 rounded p-1.5 text-app-error hover:bg-app-error/10"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($khususKeluar)
                            <div class="mt-3 border-t border-gray-100 pt-3">
                                <p class="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-text-muted">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3v12a3 3 0 0 0 3 3h11M6 3H3M6 3h9M18 15v6M15 6h6"/></svg>
                                    Kelompok Kasi
                                </p>

                                @if (empty($bag['kasi_grup']))
                                    <p class="mb-2 text-xs italic text-text-muted">Belum ada grup Kasi -- Kaur/Kasi di atas masih rantai datar biasa.</p>
                                @else
                                    <div class="mb-2 space-y-2">
                                        @foreach ($bag['kasi_grup'] as $grup)
                                            <div class="rounded-lg border border-gray-200 bg-primary-green/[0.04]" wire:key="grup-{{ $grup['id'] }}">
                                                <div class="flex items-center gap-3 px-3 py-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-semibold text-text-dark">Kasi: {{ $grup['kasi']['nama'] }}</p>
                                                        <p class="truncate text-xs text-text-muted">{{ $grup['kasi']['username'] }}</p>
                                                    </div>
                                                    <button
                                                        type="button" wire:click="hapusKasiGrup({{ $grup['id'] }})"
                                                        wire:confirm="Grup Kasi {{ $grup['kasi']['nama'] }} ({{ $grup['kasi']['username'] }}) beserta {{ count($grup['kaur']) }} Kaur di bawahnya akan dihapus. Surat yang sudah pernah diproses lewat grup ini TIDAK terpengaruh. Lanjutkan?"
                                                        title="Hapus grup ini" class="shrink-0 rounded p-1.5 text-app-error hover:bg-app-error/10"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    </button>
                                                </div>

                                                @if (empty($grup['kaur']))
                                                    <p class="px-10 pb-2 text-xs text-text-muted">Belum ada Kaur di grup ini.</p>
                                                @else
                                                    <ul class="divide-y divide-gray-100 border-t border-gray-200">
                                                        @foreach ($grup['kaur'] as $kaur)
                                                            <li class="flex items-center gap-3 py-1.5 pl-10 pr-3" wire:key="kaur-{{ $kaur['id'] }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="truncate text-xs font-medium text-text-dark">{{ $kaur['nama'] }}</p>
                                                                    <p class="truncate text-[11px] text-text-muted">{{ $kaur['username'] }}</p>
                                                                </div>
                                                                <button
                                                                    type="button" wire:click="hapusAnggota({{ $kaur['id'] }})"
                                                                    wire:confirm="{{ $kaur['nama'] }} ({{ $kaur['username'] }}) akan dikeluarkan dari Bag ini. Lanjutkan?"
                                                                    title="Keluarkan dari grup" class="shrink-0 rounded p-1 text-app-error hover:bg-app-error/10"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                                </button>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                <div class="px-10 py-2">
                                                    <button type="button" wire:click="openPilihUser('kaur', {{ $bag['id'] }}, {{ $grup['id'] }})" class="text-xs font-semibold text-primary-green hover:underline">+ Tambah Kaur</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <button type="button" wire:click="openPilihUser('kasi_grup', {{ $bag['id'] }})" class="text-xs font-semibold text-primary-green hover:underline">+ Tambah Grup Kasi</button>
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                            <button type="button" wire:click="openPilihUser('{{ $tambahAction }}', {{ $bag['id'] }})" class="text-xs font-semibold text-primary-green hover:underline">
                                + {{ $khususKeluar ? 'Tambah Anggota' : 'Tambah Penerima Disposisi' }}
                            </button>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" wire:click="openEditBag({{ $bag['id'] }})" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-text-dark hover:bg-app-background">Ubah</button>
                                <button
                                    type="button" wire:click="deleteBag({{ $bag['id'] }})"
                                    wire:confirm="Bag &quot;{{ $bag['nama'] }}&quot; beserta {{ count($sumberAnggota) }} anggotanya akan dihapus. Surat yang sudah pernah diproses lewat Bag ini TIDAK terpengaruh. Lanjutkan?"
                                    class="rounded-lg border border-app-error/40 px-2.5 py-1.5 text-xs font-medium text-app-error hover:bg-app-error/5"
                                >Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal: Tambah/Ubah Bag --}}
    @if ($showBagForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:key="bag-form-backdrop">
            <div class="w-full max-w-sm rounded-2xl bg-app-surface p-6 shadow-xl" x-data @click.outside="$wire.closeBagForm()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">{{ $editingBagId ? 'Ubah Bag' : 'Tambah Bag' }}</h2>
                    <button type="button" wire:click="closeBagForm" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <form wire:submit="saveBag" class="space-y-3">
                    @if ($bagFormError)
                        <div class="rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">{{ $bagFormError }}</div>
                    @endif
                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Nama Bag</label>
                        <input type="text" wire:model="bagFormNama" autofocus class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" wire:click="closeBagForm" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
                        <button type="submit" class="flex-1 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary-green">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Pilih User (Tambah Anggota / Tambah Penerima Disposisi / Tambah Grup Kasi / Tambah Kaur) --}}
    @if ($showPilihUser)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:key="pilih-user-backdrop">
            <div class="flex w-full max-w-sm flex-col rounded-2xl bg-app-surface p-6 shadow-xl" x-data @click.outside="$wire.closePilihUser()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">{{ $pilihUserTitle }}</h2>
                    <button type="button" wire:click="closePilihUser" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if ($pilihUserError)
                    <div class="mb-3 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">{{ $pilihUserError }}</div>
                @endif

                <input
                    type="text" wire:model.live.debounce.300ms="pilihUserQuery" placeholder="Cari nama atau username" autofocus
                    class="mb-3 w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >

                <div class="max-h-80 overflow-y-auto">
                    @php $kandidat = $this->pilihUserKandidat(); @endphp
                    @if (empty($kandidat))
                        <p class="py-8 text-center text-sm text-text-muted">Tidak ada user yang cocok</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($kandidat as $u)
                                <li wire:key="kandidat-{{ $u['id'] }}">
                                    <button type="button" wire:click="pilihUser({{ $u['id'] }})" class="flex w-full flex-col items-start rounded-lg px-2 py-2.5 text-left hover:bg-app-background">
                                        <span class="text-sm font-medium text-text-dark">{{ $u['nama'] }}</span>
                                        <span class="text-xs text-text-muted">{{ $u['username'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="mt-3">
                    <button type="button" wire:click="closePilihUser" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Pilih Akun Posisi (Turmin/Kasubdit/Kabag) --}}
    @if ($showPilihPosisi)
        @php
            $bagPosisi = collect($bags)->firstWhere('id', $posisiBagId);
            $posisiKunci = match ($posisiKolom) {
                'turmin_user_id' => 'turmin',
                'kabag_user_id' => 'kabag',
                default => 'kasubdit',
            };
            $sudahDipilih = $bagPosisi[$posisiKunci] ?? null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:key="pilih-posisi-backdrop">
            <div class="flex w-full max-w-sm flex-col rounded-2xl bg-app-surface p-6 shadow-xl" x-data @click.outside="$wire.closePosisi()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">Akun {{ $posisiLabel }} &ndash; {{ $bagPosisi['nama'] ?? '' }}</h2>
                    <button type="button" wire:click="closePosisi" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                @if ($posisiError)
                    <div class="mb-3 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">{{ $posisiError }}</div>
                @endif

                <input
                    type="text" wire:model.live.debounce.300ms="posisiQuery" placeholder="Cari nama atau username" autofocus
                    class="mb-3 w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >

                <div class="max-h-80 overflow-y-auto">
                    @php $kandidatPosisi = $this->posisiKandidat(); @endphp
                    @if (empty($kandidatPosisi))
                        <p class="py-8 text-center text-sm text-text-muted">Tidak ada user yang cocok</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($kandidatPosisi as $u)
                                <li wire:key="posisi-kandidat-{{ $u['id'] }}">
                                    <button type="button" wire:click="pilihPosisi({{ $u['id'] }})" class="flex w-full flex-col items-start rounded-lg px-2 py-2.5 text-left hover:bg-app-background">
                                        <span class="text-sm font-medium text-text-dark">{{ $u['nama'] }}</span>
                                        <span class="text-xs text-text-muted">{{ $u['username'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="mt-3 flex gap-2">
                    @if ($sudahDipilih)
                        <button type="button" wire:click="pilihPosisi(null)" class="flex-1 rounded-lg border border-app-error/40 px-4 py-2.5 text-sm font-medium text-app-error hover:bg-app-error/5">Kosongkan</button>
                    @endif
                    <button type="button" wire:click="closePosisi" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
                </div>
            </div>
        </div>
    @endif
</div>
