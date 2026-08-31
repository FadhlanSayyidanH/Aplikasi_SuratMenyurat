{{--
    Folder browser Bulan -> Klasifikasi -> daftar surat -- migrasi dari
    lib/screens/surat_kategori_browser.dart (SuratKategoriBrowser), dirender
    langsung sebagai bagian dari App\Livewire\Dashboard (lihat komentar
    arsitektur di komponen tsb -- BUKAN route/komponen Livewire terpisah).
--}}
@php
    $daftarSurat = $this->daftarTerurut();
    $isMasuk = $this->isMasukList();
    $kelompokkanPerBulan = $this->kelompokkanPerBulan();
@endphp

@if ($daftarSurat->isEmpty())
    <p class="py-12 text-center text-sm text-text-muted">{{ $this->activeMenu()->pesanKosong() }}</p>
@elseif ($klasifikasi !== null)
    {{-- Level 3: daftar surat (leaf) di dalam satu folder klasifikasi --}}
    <x-surat.header-kembali
        :title="$kelompokkanPerBulan ? ($bulanLabel.' • '.$klasifikasi) : $klasifikasi"
        kembali-action="kembaliKlasifikasi"
    >
        @if ($isMasuk)
            <select wire:model.live="urutanFolder" class="shrink-0 rounded-lg border border-gray-300 bg-app-surface px-2.5 py-1.5 text-xs focus:border-primary-green focus:outline-none">
                <option value="awal">Default (Urutan Awal)</option>
                <option value="belum_direspon">Belum Direspon di Atas</option>
            </select>
        @endif
    </x-surat.header-kembali>

    <x-surat.bar-seleksi :jumlah="count($selectedIds)" />

    <div class="space-y-2.5">
        @foreach ($this->leafSurat() as $s)
            <x-surat.bar
                :surat="$s"
                :tanggal-urut="$this->tanggalUrutUntuk($s)"
                :sudah-direspon="$this->statusDisposisiUntukBrowser($s)"
                :selectable="$this->canDelete()"
                :selected="in_array($s->id, $selectedIds, true)"
                wire:key="leaf-{{ $s->id }}"
            />
        @endforeach
    </div>
@elseif (!$kelompokkanPerBulan || $bulanKey !== null)
    {{-- Level 2: folder klasifikasi (di dalam satu bulan, atau langsung level teratas kalau kelompokkanPerBulan=false) --}}
    @if ($kelompokkanPerBulan)
        <x-surat.header-kembali :title="$bulanLabel" kembali-action="kembaliBulan">
            @if ($isMasuk)
                <select wire:model.live="urutanFolder" class="shrink-0 rounded-lg border border-gray-300 bg-app-surface px-2.5 py-1.5 text-xs focus:border-primary-green focus:outline-none">
                    <option value="awal">Default (Urutan Awal)</option>
                    <option value="belum_direspon">Belum Direspon di Atas</option>
                </select>
            @endif
        </x-surat.header-kembali>
    @elseif ($isMasuk)
        <div class="mb-3 flex justify-end">
            <select wire:model.live="urutanFolder" class="rounded-lg border border-gray-300 bg-app-surface px-2.5 py-1.5 text-xs focus:border-primary-green focus:outline-none">
                <option value="awal">Default (Urutan Awal)</option>
                <option value="belum_direspon">Belum Direspon di Atas</option>
            </select>
        </div>
    @endif

    <x-surat.bar-seleksi :jumlah="count($selectedIds)" />

    <div class="space-y-2.5">
        @foreach ($this->klasifikasiGroups() as $group)
            <x-surat.kategori-bar
                icon="category"
                :label="$group['label']"
                :jumlah="$group['jumlah']"
                :ada-belum-direspon="$group['ada_belum_direspon']"
                :show-checkbox="$this->canDelete()"
                :checkbox-value="$this->statusSeleksiGroup($group['ids'])"
                :ids="$group['ids']"
                open-action="bukaKlasifikasi"
                :open-args="[$group['label']]"
                wire:key="klas-{{ $group['label'] }}"
            />
        @endforeach
    </div>
@else
    {{-- Level 1 (teratas): folder bulan --}}
    @if ($isMasuk)
        <div class="mb-3 flex justify-end">
            <select wire:model.live="urutanFolder" class="rounded-lg border border-gray-300 bg-app-surface px-2.5 py-1.5 text-xs focus:border-primary-green focus:outline-none">
                <option value="awal">Default (Urutan Awal)</option>
                <option value="belum_direspon">Belum Direspon di Atas</option>
            </select>
        </div>
    @endif

    <x-surat.bar-seleksi :jumlah="count($selectedIds)" />

    <div class="space-y-2.5">
        @foreach ($this->bulanGroups() as $group)
            <x-surat.kategori-bar
                icon="folder"
                :label="$group['label']"
                :jumlah="$group['jumlah']"
                :ada-belum-direspon="$group['ada_belum_direspon']"
                :show-checkbox="$this->canDelete()"
                :checkbox-value="$this->statusSeleksiGroup($group['ids'])"
                :ids="$group['ids']"
                open-action="bukaBulan"
                :open-args="[$group['key'], $group['label']]"
                wire:key="bulan-{{ $group['key'] }}"
            />
        @endforeach
    </div>
@endif
