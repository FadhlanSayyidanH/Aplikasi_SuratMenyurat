{{-- Tinjau laporan gangguan/kendala dari user -- lihat App\Livewire\LaporanGangguanAdmin. --}}
<div>
    <p class="mb-4 text-xs text-text-muted">
        Laporan dikirim user lewat tombol mengambang "Laporkan Gangguan / Kendala" di setiap halaman.
        Tandai <span class="font-medium">Selesai</span> setelah ditangani; laporan yang masih
        <span class="font-medium">Baru</span> muncul sebagai angka merah di menu ini.
    </p>

    @php
        $tabs = ['baru' => 'Baru', 'selesai' => 'Selesai', 'semua' => 'Semua'];
    @endphp
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $kode => $label)
            <button
                type="button" wire:click="$set('filter', '{{ $kode }}')"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $filter === $kode ? 'bg-primary-green text-white' : 'bg-app-surface text-text-muted hover:bg-gray-100' }}"
            >
                {{ $label }}
                <span class="ml-1 text-xs opacity-80">{{ $this->jumlah[$kode] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    @if ($this->daftar->isEmpty())
        <p class="rounded-xl bg-app-surface p-8 text-center text-sm text-text-muted shadow-sm">
            Tidak ada laporan pada filter ini.
        </p>
    @else
        <div class="space-y-2.5">
            @foreach ($this->daftar as $item)
                @php
                    $kategoriLabel = \App\Models\LaporanGangguan::KATEGORI[$item->kategori] ?? $item->kategori;
                @endphp
                <div wire:key="laporan-{{ $item->id }}" class="rounded-xl bg-app-surface p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-primary-green/10 px-2 py-0.5 text-[11px] font-semibold text-primary-green">{{ $kategoriLabel }}</span>
                                @if ($item->status === 'selesai')
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-text-muted">Selesai</span>
                                @else
                                    <span class="rounded-full bg-app-error/10 px-2 py-0.5 text-[11px] font-semibold text-app-error">Baru</span>
                                @endif
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-text-dark">{{ $item->pesan }}</p>
                            <p class="mt-1.5 text-[11px] text-text-muted">
                                {{ $item->pelapor_nama }}
                                &middot; {{ $item->created_at->translatedFormat('d M Y H:i') }}
                                @if ($item->halaman)
                                    &middot; <span class="break-all">{{ $item->halaman }}</span>
                                @endif
                            </p>
                            @if ($item->status === 'selesai' && $item->ditangani_oleh)
                                <p class="mt-0.5 text-[11px] text-text-muted">
                                    Ditangani {{ $item->ditangani_oleh }}
                                    @if ($item->ditangani_pada)
                                        &middot; {{ $item->ditangani_pada->translatedFormat('d M Y H:i') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col gap-1.5">
                            @if ($item->status === 'baru')
                                <button
                                    type="button" wire:click="tandaiSelesai({{ $item->id }})"
                                    wire:loading.attr="disabled" wire:target="tandaiSelesai({{ $item->id }})"
                                    class="rounded-lg bg-primary-green px-3 py-1.5 text-xs font-medium text-white transition hover:bg-secondary-green disabled:opacity-60"
                                >Tandai Selesai</button>
                            @else
                                <button
                                    type="button" wire:click="tandaiBaru({{ $item->id }})"
                                    wire:loading.attr="disabled" wire:target="tandaiBaru({{ $item->id }})"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-muted transition hover:bg-gray-100 disabled:opacity-60"
                                >Buka Lagi</button>
                            @endif
                            <button
                                type="button" wire:click="hapus({{ $item->id }})"
                                wire:confirm="Hapus laporan ini permanen?"
                                wire:loading.attr="disabled" wire:target="hapus({{ $item->id }})"
                                class="rounded-lg border border-app-error/30 px-3 py-1.5 text-xs font-medium text-app-error transition hover:bg-app-error/10 disabled:opacity-60"
                            >Hapus</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
