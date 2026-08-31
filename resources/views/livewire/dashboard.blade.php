{{--
    Shell utama setelah login -- migrasi dari lib/screens/dashboard_screen.dart,
    DIGABUNG dengan lib/screens/surat_kategori_browser.dart (folder
    Bulan -> Klasifikasi -> daftar surat, dirender langsung sebagai bagian
    dari komponen ini, lihat komentar di App\Livewire\Dashboard).

    CATATAN: menu folder Surat Masuk/Surat Keluar/Jajaran di SIDEBAR bukan
    lagi bagian dari view ini -- sebelumnya dirender lewat
    @section('sidebar-extra') tapi itu DI LUAR boundary komponen Livewire ini
    (wire:click jadi no-op) DAN cuma tampil saat komponen Dashboard aktif
    (hilang/berubah di halaman lain, mis. Tinjau Surat). Sekarang dirender
    SEKALI untuk SEMUA halaman langsung di layouts.app lewat
    App\Services\SidebarMenuService (lihat komentar di sana) -- App\Livewire\Dashboard
    delegasi ke service yang SAMA supaya menu & badge di sidebar dan di
    konten utama Dashboard ini selalu konsisten.
--}}

<div wire:poll.8s="pollLive">
    {{-- Pengumuman admin ke seluruh akun (App\Livewire\PengumumanAdmin) --
         beda dari notifikasi surat, ikut ter-refresh lewat wire:poll.8s di
         atas jadi otomatis hilang begitu admin cabut. --}}
    @if ($this->pengumumanAktif->isNotEmpty())
        <div class="mb-4 space-y-2">
            @foreach ($this->pengumumanAktif as $item)
                {{-- Pesan panjang di-truncate 3 baris (line-clamp) supaya tidak
                     memakan layar -- "Baca selengkapnya" cuma tampil kalau
                     pesannya memang lebih dari ~180 karakter, dicek sekali di
                     server (bukan diukur di browser) supaya tidak perlu JS
                     tambahan untuk deteksi overflow. --}}
                <div wire:key="pengumuman-{{ $item->id }}" x-data="{ expanded: false }" class="flex items-start gap-3 rounded-xl border border-gold/40 bg-gold/10 p-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <div class="min-w-0 flex-1">
                        @if (mb_strlen($item->pesan) > 180)
                            <p class="whitespace-pre-line text-sm font-medium text-text-dark" :class="expanded ? '' : 'line-clamp-3'">{{ $item->pesan }}</p>
                            <button type="button" @click="expanded = !expanded" class="mt-1 text-xs font-semibold text-primary-green hover:underline" x-text="expanded ? 'Sembunyikan' : 'Baca selengkapnya'"></button>
                        @else
                            <p class="whitespace-pre-line text-sm font-medium text-text-dark">{{ $item->pesan }}</p>
                        @endif
                        <p class="mt-1 text-[11px] text-text-muted">{{ $item->dibuat_oleh }} &middot; {{ $item->created_at->translatedFormat('d M Y H:i') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Baris aksi atas: indikator Live, lonceng notifikasi, muat ulang, tombol input surat --}}
    <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
        <span class="flex items-center gap-1.5 rounded-full bg-secondary-green/10 px-3 py-1.5 text-xs font-semibold text-secondary-green">
            <span class="h-2 w-2 rounded-full bg-secondary-green"></span> Live
        </span>

        <div class="relative" x-data="{ open: false }">
            <button
                type="button" @click="open = !open" @click.outside="open = false"
                class="relative flex items-center gap-1.5 rounded-full border border-gray-300 bg-app-surface px-3 py-1.5 text-xs font-semibold text-text-dark hover:bg-app-background"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Notifikasi
                @if ($this->jumlahNotifikasi() > 0)
                    <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-app-error px-1 text-[10px] font-bold text-white">{{ $this->jumlahNotifikasi() }}</span>
                @endif
            </button>

            <div x-show="open" x-cloak x-transition class="absolute right-0 z-40 mt-2 max-h-[28rem] w-[22rem] overflow-y-auto rounded-xl border border-gray-200 bg-app-surface p-3 shadow-2xl">
                @php
                    $sections = $this->isPejabat()
                        ? [
                            ['judul' => 'Surat Masuk Belum Direspon', 'daftar' => $this->notifikasiMasuk(), 'menu' => 'dashboard-masuk'],
                            ['judul' => 'Surat Keluar Menunggu Giliran Anda', 'daftar' => $this->notifikasiKeluar(), 'menu' => 'dashboard-keluar'],
                        ]
                        : [
                            ['judul' => 'Perlu Ditindaklanjuti', 'daftar' => $this->notifikasiAdmin(), 'menu' => 'ringkasan'],
                        ];
                @endphp

                @if (!$this->jumlahNotifikasi())
                    <p class="px-2 py-6 text-center text-xs text-text-muted">Tidak ada surat yang perlu ditindaklanjuti</p>
                @else
                    @foreach ($sections as $section)
                        @continue($section['daftar']->isEmpty())
                        <p class="mb-1.5 mt-3 px-1 text-[11px] font-bold uppercase tracking-wide text-text-muted first:mt-0">{{ $section['judul'] }}</p>
                        <div class="space-y-2">
                            @foreach ($section['daftar']->take(4) as $s)
                                <x-surat.bar :surat="$s" :tanggal-urut="$this->tanggalUrutUntuk($s)" :sudah-direspon="$this->statusDisposisiUntukBar($s)" class="!px-3 !py-2.5" />
                            @endforeach
                        </div>
                        @if ($section['daftar']->count() > 4)
                            <button type="button" wire:click="pilihMenu('{{ $section['menu'] }}')" class="mt-1 w-full rounded-lg px-2 py-1.5 text-left text-xs font-semibold text-primary-green hover:bg-primary-green/5">
                                +{{ $section['daftar']->count() - 4 }} lainnya, lihat semua
                            </button>
                        @endif
                    @endforeach

                    @if ($this->isPejabat() && $this->notifikasiArsipKeluar()->isNotEmpty())
                        <p class="mb-1.5 mt-3 px-1 text-[11px] font-bold uppercase tracking-wide text-text-muted">Surat Keluar Disetujui/Perlu Revisi</p>
                        <div class="space-y-2">
                            @foreach ($this->notifikasiArsipKeluar()->take(4) as $s)
                                <x-surat.bar :surat="$s" :tanggal-urut="$this->tanggalUrutUntuk($s)" class="!px-3 !py-2.5">
                                    <x-slot:trailing>
                                        <button
                                            type="button"
                                            @click.stop.prevent="confirm('{{ $s->status === 'ditolak' ? 'Surat ini tidak akan diperbaiki/diajukan ulang maupun dihapus, notifikasinya akan berhenti muncul.' : 'Tandai surat keluar ini sudah diterima dan diarsipkan?' }}') && $wire.konfirmasiSuratDiterima({{ $s->id }})"
                                            class="rounded-full p-1.5 {{ $s->status === 'ditolak' ? 'text-text-muted hover:bg-app-background' : 'text-secondary-green hover:bg-secondary-green/10' }}"
                                            title="{{ $s->status === 'ditolak' ? 'Abaikan' : 'Konfirmasi diterima' }}"
                                        >
                                            @if ($s->status === 'ditolak')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                            @endif
                                        </button>
                                    </x-slot:trailing>
                                </x-surat.bar>
                            @endforeach
                        </div>
                        @if ($this->notifikasiArsipKeluar()->count() > 4)
                            <button type="button" wire:click="pilihMenu('{{ \App\Livewire\Dashboard\MenuSurat::ArsipProgresKeluar->value }}')" class="mt-1 w-full rounded-lg px-2 py-1.5 text-left text-xs font-semibold text-primary-green hover:bg-primary-green/5">
                                +{{ $this->notifikasiArsipKeluar()->count() - 4 }} lainnya, lihat semua
                            </button>
                        @endif
                    @endif
                @endif
            </div>
        </div>

        <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="rounded-full border border-gray-300 bg-app-surface p-2 text-text-muted transition hover:bg-app-background disabled:opacity-50" title="Muat Ulang">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>

        @if ($this->bisaInputSurat())
            <a href="{{ route('surat.create') }}" wire:navigate class="flex items-center gap-1.5 rounded-full bg-primary-green px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-secondary-green">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Masukkan Surat
            </a>
        @endif
    </div>

    <div class="mx-auto max-w-3xl">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h2 class="truncate text-base font-semibold text-text-dark">
                @if ($this->sedangMencari())
                    Hasil Pencarian ({{ $this->hasilPencarian()->count() }})
                @else
                    {{ $this->judulMenuAktif() }} ({{ $this->daftarTerfilter()->count() }})
                @endif
            </h2>
        </div>

        <div class="relative mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
                type="text" wire:model.live.debounce.400ms="search"
                placeholder="Cari perihal, nomor surat, atau pengaju..."
                class="w-full rounded-lg border border-gray-300 bg-app-surface py-2.5 pl-9 pr-9 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
            >
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-dark">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            @endif
        </div>

        {{-- ================= Isi konten ================= --}}

        @if ($this->sedangMencari())
            @if ($this->hasilPencarian()->isEmpty())
                <p class="py-12 text-center text-sm text-text-muted">Tidak ada surat yang cocok dengan pencarian.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($this->hasilPencarian() as $s)
                        <x-surat.bar :surat="$s" :tanggal-urut="$this->tanggalUrutUntuk($s)" :sudah-direspon="$this->statusDisposisiUntukBar($s, true)" wire:key="cari-{{ $s->id }}" />
                    @endforeach
                </div>
            @endif
        @elseif ($this->isMenuDashboard())
            {{-- Dashboard gabungan/Dashboard Surat Masuk/Keluar: daftar flat, bukan folder --}}
            @if ($this->daftarTerfilter()->isEmpty())
                <p class="py-12 text-center text-sm text-text-muted">{{ $this->activeMenu()->pesanKosong() }}</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($this->daftarTerfilter() as $s)
                        <x-surat.bar :surat="$s" :tanggal-urut="$this->tanggalUrutUntuk($s)" :sudah-direspon="$this->statusDisposisiUntukBar($s)" wire:key="flat-{{ $s->id }}" />
                    @endforeach
                </div>
            @endif
        @else
            {{-- Folder browser: Bulan -> Klasifikasi -> daftar (lihat surat_kategori_browser.dart) --}}
            @include('livewire.partials.dashboard-kategori-browser')
        @endif
    </div>
</div>
