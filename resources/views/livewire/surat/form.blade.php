<div class="mx-auto max-w-2xl">
    @if (!$canMasuk && !$canKeluar)
        <div class="rounded-xl border border-app-error/30 bg-app-error/10 p-6 text-center">
            <p class="text-sm font-semibold text-app-error">Akun ini belum diberi izin menginput surat</p>
            <p class="mt-1 text-xs text-text-muted">Hubungi admin untuk diberi izin input Surat Masuk dan/atau Surat Keluar.</p>
        </div>
    @else
        {{-- Header langkah --}}
        <div class="rounded-xl bg-app-surface p-4 shadow-sm">
            <div class="flex items-start gap-1.5 sm:gap-2">
                @foreach ($this->stepTitles as $i => $title)
                    <button
                        type="button"
                        wire:click="goToStep({{ $i }})"
                        @if ($i >= $step) disabled @endif
                        class="min-w-0 flex-1 text-left {{ $i >= $step ? 'cursor-default' : 'cursor-pointer' }}"
                    >
                        <p class="text-[10px] font-bold tracking-wide {{ $i === $step ? 'text-gold' : 'text-text-muted' }}">
                            LANGKAH {{ $i + 1 }}
                        </p>
                        <div class="mt-0.5 flex items-center gap-1">
                            @if ($i < $step)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.6-4.2-4.2 1.4-1.4 2.8 2.8 6-6 1.4 1.4-7.4 7.4Z"/></svg>
                            @endif
                            <span class="truncate text-[11px] font-bold sm:text-xs {{ $i === $step ? 'text-primary-green' : ($i < $step ? 'text-text-dark' : 'text-text-muted') }}">
                                {{ $title }}
                            </span>
                        </div>
                        <div class="mt-2 h-[3px] rounded-full {{ $i <= $step ? 'bg-primary-green' : 'bg-gray-200' }}"></div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Konten langkah --}}
        <div class="mt-4 rounded-xl bg-app-surface p-5 shadow-sm sm:p-6">
            {{-- Langkah 0: Data Pengirim / Bagian Penerbit --}}
            @if ($step === 0)
                <div class="space-y-4">
                    @if ($canMasuk && $canKeluar)
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-text-dark">Jenis Surat</label>
                            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300">
                                <button type="button" wire:click="setJenis('masuk')"
                                    class="px-4 py-2 text-sm font-medium transition {{ $this->isMasuk ? 'bg-primary-green text-white' : 'bg-app-surface text-text-dark hover:bg-app-background' }}">
                                    Surat Masuk
                                </button>
                                <button type="button" wire:click="setJenis('keluar')"
                                    class="border-l border-gray-300 px-4 py-2 text-sm font-medium transition {{ !$this->isMasuk ? 'bg-primary-green text-white' : 'bg-app-surface text-text-dark hover:bg-app-background' }}">
                                    Surat Keluar
                                </button>
                            </div>
                        </div>
                    @endif

                    @if ($this->isMasuk)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-dark">Nomor Surat</label>
                            <input type="text" wire:model="nomorSurat" placeholder="Nomor surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @error('nomorSurat') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-dark">Nama Pengirim Surat</label>
                            <input type="text" wire:model="namaPengaju" placeholder="Nama pengirim surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @error('namaPengaju') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-dark">
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
                                        class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
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
                                            <li class="flex items-center gap-2 rounded-lg bg-app-background px-2.5 py-2">
                                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-primary-green bg-white text-xs font-bold text-primary-green">{{ $i + 1 }}</span>
                                                <span class="flex-1 truncate text-sm font-medium text-text-dark">{{ $orang['nama'] }}</span>
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

                                        @if ($this->alurRute)
                                            <div class="mt-2.5 border-t border-primary-green/20 pt-2.5">
                                                <p class="text-xs font-semibold text-text-dark">Alur Rute Persetujuan (default)</p>
                                                <ol class="mt-1.5 space-y-1">
                                                    @foreach ($this->alurRute as $i => $nama)
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
                                    class="w-full rounded-lg border {{ $jalurError || $kaurError ? 'border-app-error' : 'border-gray-300' }} bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
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
                            @endunless

                            @if (!$jalurOtomatis && $this->alurRute)
                                <div class="rounded-xl border-2 border-primary-green/20 bg-primary-green/[.03] p-4">
                                    <p class="text-sm font-bold text-text-dark">Alur Rute Persetujuan</p>
                                    <ol class="mt-2 space-y-1.5">
                                        @foreach ($this->alurRute as $i => $nama)
                                            <li class="flex items-center gap-3 rounded-lg bg-app-surface px-2.5 py-2 text-sm shadow-sm">
                                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-primary-green bg-white text-xs font-bold text-primary-green">{{ $i + 1 }}</span>
                                                <span class="font-medium text-text-dark">{{ $nama }}</span>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            @endif
                        @endif

                        <button type="button" wire:click="toggleModeManual"
                            class="w-full rounded-lg border border-primary-green/40 px-4 py-2.5 text-sm font-semibold text-primary-green transition hover:bg-primary-green/5">
                            {{ $modeManual ? 'Pakai Alur Default' : 'Pilih tembusan manual' }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- Langkah 1: Tanggal & Klasifikasi --}}
            @if ($step === 1)
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-dark">Tanggal Surat</label>
                        <input type="date" wire:model="tanggal"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        @error('tanggal') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-dark">Jenis Surat (Klasifikasi)</label>
                        <select wire:model.live="klasifikasi"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @foreach ($this->klasifikasiOptions as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                            <option value="{{ $this->klasifikasiLainnyaSentinel }}">Lainnya (isi manual)</option>
                        </select>
                    </div>
                    @if ($this->klasifikasiLainnyaAktif)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text-dark">Jenis Surat (isi manual)</label>
                            <input type="text" wire:model="klasifikasiLainnya" maxlength="50" placeholder="Jenis surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @error('klasifikasiLainnya') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            @endif

            {{-- Langkah 2: Perihal & Catatan --}}
            @if ($step === 2)
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-dark">Perihal</label>
                        <input type="text" wire:model="perihal" placeholder="Perihal surat"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        @error('perihal') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-dark">Catatan Tambahan (opsional)</label>
                        <textarea wire:model="keterangan" rows="3" placeholder="Catatan tambahan"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"></textarea>
                    </div>
                </div>
            @endif

            {{-- Langkah 3: Unggah File --}}
            @if ($step === 3)
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-text-dark">File Surat (DOCX/PPTX/XLSX/PDF/JPG/PNG)</label>
                        <p class="mt-0.5 text-xs text-text-muted">Bisa lebih dari satu file sekaligus. Nama tampilan tiap file bisa diganti bebas.</p>
                    </div>

                    @if (count($this->berkasTampilan) > 0)
                        <div class="rounded-lg border border-gray-300">
                            @foreach ($this->berkasTampilan as $b)
                                <div wire:key="berkas-{{ $b['index'] }}" class="flex items-center gap-2.5 border-b border-gray-100 px-3 py-2 last:border-b-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        @if (in_array($b['ekstensi'], ['jpg', 'jpeg', 'png']))
                                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                                        @elseif ($b['ekstensi'] === 'pdf')
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/>
                                        @else
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/>
                                        @endif
                                    </svg>
                                    <div class="min-w-0 flex-1">
                                        <input type="text" wire:model="displayNames.{{ $b['index'] }}" placeholder="Nama tampilan file"
                                            class="w-full border-0 border-b border-transparent bg-transparent p-0 text-sm text-text-dark focus:border-primary-green focus:outline-none focus:ring-0">
                                        <p class="text-[11px] text-text-muted">{{ $b['nama_asli'] }} &middot; {{ $b['ukuran'] }}</p>
                                    </div>
                                    <button type="button" wire:click="removeFile({{ $b['index'] }})" title="Hapus file ini"
                                        class="shrink-0 rounded-lg p-1.5 text-app-error hover:bg-app-error/10">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-primary-green px-4 py-2 text-sm font-medium text-primary-green hover:bg-primary-green/5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span wire:loading.remove wire:target="files">{{ count($this->berkasTampilan) === 0 ? 'Pilih File' : 'Tambah File Lagi' }}</span>
                        <span wire:loading wire:target="files">Mengunggah...</span>
                        <input type="file" wire:model="files" multiple class="hidden" accept=".docx,.pptx,.xlsx,.pdf,.jpg,.jpeg,.png">
                    </label>

                    @error('files') <p class="text-xs text-app-error">{{ $message }}</p> @enderror
                    @if ($fileError)
                        <p class="text-xs text-app-error">Minimal satu file DOCX/PPTX/XLSX/PDF/JPG/PNG surat wajib diunggah</p>
                    @endif
                </div>
            @endif

            {{-- Langkah 4: Resume --}}
            @if ($step === 4)
                <div class="space-y-4">
                    <p class="text-sm text-text-muted">Periksa kembali data di bawah sebelum menyimpan.</p>

                    <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-app-background px-4">
                        <x-surat.resume-baris label="Jenis Surat" :nilai="$this->isMasuk ? 'Surat Masuk' : 'Surat Keluar'" />
                        @if ($this->isMasuk)
                            <x-surat.resume-baris label="Nomor Surat" :nilai="$nomorSurat" />
                            <x-surat.resume-baris label="Nama Pengirim" :nilai="$namaPengaju" />
                        @else
                            <x-surat.resume-baris label="Yang Menerbitkan" :nilai="$modeManual ? 'Rantai manual' : ($this->bagTerpilih['nama'] ?? '-')" />
                            <x-surat.resume-baris
                                label="Alur Rute"
                                :nilai="collect($this->alurRute)->map(fn ($nama, $i) => ($i + 1).'. '.$nama)->implode(chr(10))"
                            />
                        @endif
                        <x-surat.resume-baris label="Tanggal Surat" :nilai="$tanggal" />
                        <x-surat.resume-baris label="Jenis/Klasifikasi" :nilai="$this->klasifikasiFinal" />
                        <x-surat.resume-baris label="Perihal" :nilai="$perihal" />
                        <x-surat.resume-baris label="Catatan" :nilai="$keterangan ?: '-'" />
                        <x-surat.resume-baris
                            :label="count($this->berkasTampilan) > 1 ? 'File ('.count($this->berkasTampilan).')' : 'File'"
                            :nilai="collect($this->berkasTampilan)->map(fn ($b) => $displayNames[$b['index']] !== '' ? $displayNames[$b['index']] : $b['nama_asli'])->implode(chr(10))"
                            :terakhir="true"
                        />
                    </div>

                    @if ($submitError)
                        <div class="flex items-start gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2.5 text-sm text-app-error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>{{ $submitError }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Navigasi bawah --}}
        <div class="mt-4 flex gap-3">
            @if ($step === 0)
                <a href="{{ route('dashboard') }}" wire:navigate
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-medium text-text-dark transition hover:bg-app-background">
                    Batal
                </a>
            @else
                <button type="button" wire:click="prevStep" @disabled($isSubmitting)
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark transition hover:bg-app-background disabled:opacity-60">
                    Kembali
                </button>
            @endif

            @if ($step < 4)
                <button type="button" wire:click="nextStep" @disabled($isSubmitting)
                    class="flex-1 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green disabled:opacity-70">
                    Lanjut
                </button>
            @else
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green disabled:opacity-70">
                    <svg wire:loading wire:target="submit" class="h-4.5 w-4.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="submit">Simpan Surat</span>
                </button>
            @endif
        </div>
    @endif
</div>
