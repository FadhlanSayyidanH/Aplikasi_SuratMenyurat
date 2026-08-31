{{--
    Panel "Ubah Rantai Proses" -- struktur & properti SAMA PERSIS dengan
    Langkah 1 SuratForm (lihat App\Livewire\Concerns\MengelolaRantaiSuratKeluar
    dan resources/views/livewire/surat/form.blade.php), supaya perilaku
    otomatis/manualnya konsisten. Bedanya di sini menyimpan lewat
    simpanEditRantai() -- jalur otomatis cuma ganti tahap yang belum
    diproses, rantai MANUAL bebas menyusun ulang seluruh rantai (lihat
    App\Livewire\SuratReview::bisaEditRantai()).
--}}
<div class="space-y-3 rounded-xl border border-gray-200 bg-app-background p-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-text-dark">Ubah Rantai Proses</h3>
        @unless ($modeManual)
            <span class="rounded-full bg-app-surface px-2.5 py-1 text-[11px] font-medium text-text-muted">
                Mulai tahap {{ $this->cariTahapSaya()?->urutan }}
            </span>
        @endunless
    </div>
    <p class="text-xs text-text-muted">
        @if ($modeManual)
            Anda bebas menyusun ulang seluruh rantai -- geser, hapus, atau tambah siapa saja. Tahap yang urutannya (posisi & nama) PERSIS sama dengan rantai sekarang, keputusannya (disetujui/ditolak) tetap dipertahankan -- ditandai <span class="font-semibold text-primary-green">"Tetap"</span> di bawah. Begitu ada yang beda di satu posisi, posisi itu dan seterusnya jadi tahap baru yang menunggu lagi.
        @else
            Hanya tahap yang BELUM diproses (mulai giliran Anda) yang akan diganti -- tahap sebelumnya yang sudah disetujui tidak berubah.
        @endif
    </p>

    @if ($errorEditData)
        <div class="flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ $errorEditData }}
        </div>
    @endif

    <div>
        <label class="mb-1 block text-xs font-medium text-text-dark">
            {{ $modeManual ? 'Rantai Proses Manual' : 'Kaur Penyiap Surat' }}
        </label>
        <p class="text-xs text-text-muted">
            {{ $modeManual
                ? 'Pilih sendiri siapa saja yang akan memproses surat ini, bebas urutannya.'
                : 'Pilih Kaur yang menyiapkan surat ini -- Bagian & Kasi yang membawahi otomatis mengikuti.' }}
        </p>
    </div>

    @if ($modeManual)
        <div class="space-y-3">
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="cariUserManual"
                    placeholder="Cari nama untuk ditambahkan..."
                    class="w-full rounded-lg border border-gray-300 bg-app-surface px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                @if (trim($cariUserManual) !== '')
                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                        @forelse ($this->opsiUserManual as $opsi)
                            <button type="button" wire:click="pilihUserManual({{ $opsi['id'] }})"
                                class="block w-full px-3.5 py-2 text-left text-sm text-text-dark transition hover:bg-primary-green/10">
                                {{ $opsi['nama'] }}
                            </button>
                        @empty
                            <p class="px-3.5 py-2 text-sm text-text-muted">Tidak ditemukan</p>
                        @endforelse
                    </div>
                @endif
            </div>

            @if ($jalurError)
                <p class="text-xs text-app-error">Pilih minimal satu orang untuk rantai proses manual</p>
            @endif

            @if ($rantaiManual)
                <ol class="space-y-1.5">
                    @foreach ($rantaiManual as $i => $orang)
                        @php
                            $akanTetap = $i < $this->prefixRantaiManualSama
                                && ($rantaiManualAsal[$i]['status'] ?? null) === 'disetujui';
                        @endphp
                        <li class="flex items-center gap-2 rounded-lg bg-app-surface px-2.5 py-2">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-primary-green bg-white text-xs font-bold text-primary-green">{{ $i + 1 }}</span>
                            <span class="flex-1 truncate text-sm font-medium text-text-dark">{{ $orang['nama'] }}</span>
                            @if ($akanTetap)
                                <span class="shrink-0 rounded-full bg-primary-green/10 px-2 py-0.5 text-[10px] font-semibold text-primary-green" title="Sudah disetujui -- posisinya tidak berubah, jadi keputusannya tetap dipertahankan">
                                    Tetap
                                </span>
                            @endif
                            <button type="button" wire:click="pindahRantaiManual({{ $i }}, -1)" @disabled($i === 0)
                                class="rounded p-1 text-text-muted hover:bg-gray-200 hover:text-text-dark disabled:opacity-25 disabled:hover:bg-transparent" title="Naikkan urutan">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m18 15-6-6-6 6"/></svg>
                            </button>
                            <button type="button" wire:click="pindahRantaiManual({{ $i }}, 1)" @disabled($i === count($rantaiManual) - 1)
                                class="rounded p-1 text-text-muted hover:bg-gray-200 hover:text-text-dark disabled:opacity-25 disabled:hover:bg-transparent" title="Turunkan urutan">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <button type="button" wire:click="hapusDariRantaiManual({{ $i }})"
                                class="rounded p-1 text-app-error hover:bg-app-error/10" title="Hapus dari rantai">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @else
        @if ($jalurOtomatis)
            <div class="flex items-start gap-2.5 rounded-lg border border-primary-green/30 bg-primary-green/[.06] p-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2 3 6v6c0 5 3.5 9 9 10 5.5-1 9-5 9-10V6l-9-4Z"/><path d="m9 12 2 2 4-4"/></svg>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-text-muted">Terdeteksi otomatis dari akun Anda:</p>
                    <p class="text-sm font-semibold text-text-dark">{{ $this->bagOtomatisNama }}</p>
                    @if ($this->kaurOtomatisNama)
                        <p class="text-xs text-text-muted">Sebagai Kaur {{ $this->kaurOtomatisNama }}</p>
                    @endif

                    @if ($this->alurRuteBaru)
                        <div class="mt-2.5 border-t border-primary-green/20 pt-2.5">
                            <p class="text-xs font-semibold text-text-dark">Rantai Baru (mulai tahap {{ $this->cariTahapSaya()?->urutan }})</p>
                            <ol class="mt-1.5 space-y-1">
                                @foreach ($this->alurRuteBaru as $i => $nama)
                                    <li class="flex items-center gap-2 text-xs text-text-dark">
                                        <span class="flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-primary-green/15 text-[10px] font-bold text-primary-green">{{ $i + 1 }}</span>
                                        {{ $nama }}
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @unless ($this->penyiapSudahPasti)
            <select wire:change="pilihGabungan($event.target.value)"
                class="w-full rounded-lg border {{ $jalurError || $kaurError ? 'border-app-error' : 'border-gray-300' }} bg-app-surface px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                <option value="">-- Pilih Kaur Penyiap Surat --</option>
                @php $bagNamaTampil = null; @endphp
                @forelse ($this->opsiKaurPenyiap as $opsi)
                    @if ($opsi['bag_nama'] !== $bagNamaTampil)
                        @if ($bagNamaTampil !== null) </optgroup> @endif
                        <optgroup label="{{ $opsi['bag_nama'] }}">
                        @php $bagNamaTampil = $opsi['bag_nama']; @endphp
                    @endif
                    <option value="{{ $opsi['bag_id'] }}|{{ $opsi['kaur_id'] }}"
                        @selected($bagTerpilihId === $opsi['bag_id'] && $kaurMemberId === $opsi['kaur_id'])>
                        {{ $opsi['label'] }}
                    </option>
                @empty
                    <option value="" disabled>Akun ini belum terdaftar di Bag/Kasi/Kaur manapun -- hubungi admin.</option>
                @endforelse
                @if ($bagNamaTampil !== null) </optgroup> @endif
            </select>
            @if ($jalurError)
                <p class="text-xs text-app-error">Yang menerbitkan surat wajib dipilih</p>
            @elseif ($kaurError)
                <p class="text-xs text-app-error">Kaur penyiap surat wajib dipilih</p>
            @endif

            @if (!$jalurOtomatis && $this->alurRuteBaru)
                <div class="rounded-xl border-2 border-primary-green/20 bg-primary-green/[.03] p-4">
                    <p class="text-sm font-bold text-text-dark">Rantai Baru (mulai tahap {{ $this->cariTahapSaya()?->urutan }})</p>
                    <ol class="mt-2 space-y-1.5">
                        @foreach ($this->alurRuteBaru as $i => $nama)
                            <li class="flex items-center gap-3 rounded-lg bg-app-surface px-2.5 py-2 text-sm shadow-sm">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-primary-green bg-white text-xs font-bold text-primary-green">{{ $i + 1 }}</span>
                                <span class="font-medium text-text-dark">{{ $nama }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @endunless
    @endif

    <button type="button" wire:click="toggleModeManual"
        class="w-full rounded-lg border border-primary-green/40 px-4 py-2.5 text-sm font-semibold text-primary-green transition hover:bg-primary-green/5">
        {{ $modeManual ? 'Pakai Alur Default' : 'Pilih tembusan manual' }}
    </button>

    <div class="flex items-center gap-2">
        <button
            type="button" wire:click="simpanEditRantai" wire:loading.attr="disabled" wire:target="simpanEditRantai"
            class="rounded-lg bg-primary-green px-4 py-2 text-xs font-semibold text-white hover:bg-secondary-green disabled:opacity-60"
        >Simpan Rantai Baru</button>
        <button type="button" wire:click="batalEditRantai" class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-text-dark hover:bg-app-background">
            Batal
        </button>
    </div>
</div>
