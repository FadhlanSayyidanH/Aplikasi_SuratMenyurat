{{-- Panel form kanan untuk Surat Masuk. Cermin _buildFormAreaMasuk(). --}}
@php
    $approval = $this->approvalRows();
    $tahapAktif = $this->tahapAktif();
    $gateLolos = $this->gateDisposisiLolos();
    $namaTujuan = $this->disposisiNamaMentah();
    sort($namaTujuan);
    $instruksiValid = config('suratapp.instruksi_disposisi_valid');
    $instruksiFinal = $this->instruksiDisposisiFinal();
@endphp

@if ($surat->keterangan)
    <div class="mb-4">
        <p class="mb-1.5 text-sm font-semibold text-text-dark">Catatan Tambahan dari Pengirim</p>
        <div class="rounded-lg border border-gray-200 bg-app-background p-3 text-sm leading-relaxed text-text-dark">{{ $surat->keterangan }}</div>
    </div>
@endif

@if (!empty($namaTujuan))
    <div class="mb-4">
        <p class="mb-2 text-sm font-semibold text-text-dark">Diteruskan ke</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($namaTujuan as $nama)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-app-background px-3 py-1 text-xs text-text-dark">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ $nama }}
                </span>
            @endforeach
        </div>
    </div>
@endif

@if ($approval->isNotEmpty())
    <div class="mb-4">
        <p class="mb-2 text-sm font-semibold text-text-dark">Tahapan Approval</p>
        <div class="space-y-2">
            @foreach ($approval as $tahap)
                @include('livewire.partials.surat-review-tahap-tile', ['tahap' => $tahap, 'aktif' => $tahapAktif && $tahap->urutan === $tahapAktif->urutan])
            @endforeach
        </div>
    </div>
@endif

@if ($this->bisaEditApproval() && $this->isGateAkhirDisposisi())
    <div class="mb-4">
        <p class="text-sm font-semibold text-text-dark">Diteruskan Kepada</p>
        <p class="mb-2 text-xs text-text-muted">Pilih Bag tujuan surat ini diteruskan (opsional). Seluruh anggota Bag yang dicentang otomatis ikut diteruskan.</p>
        <div class="rounded-lg border border-gray-300">
            @if ($sedangMuatBag)
                <p class="p-4 text-center text-sm text-text-muted">Memuat Bag...</p>
            @elseif ($errorMuatBag)
                <p class="p-4 text-sm text-app-error">{{ $errorMuatBag }}</p>
            @elseif (empty($bags))
                <p class="p-4 text-center text-sm text-text-muted">Tidak ada Bag terdaftar untuk akun ini.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($bags as $bag)
                        <label class="flex items-center gap-3 px-3 py-2.5 text-sm text-text-dark">
                            <input type="checkbox" wire:model="bagTujuanTerpilih" value="{{ $bag['id'] }}" class="rounded border-gray-300 text-primary-green focus:ring-primary-green">
                            <span class="flex-1">{{ $bag['nama'] }}</span>
                            <span class="text-xs text-text-muted">{{ count($bag['anggota_masuk']) }} anggota</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="mb-4">
        <p class="text-sm font-semibold text-text-dark">Tembusan Manual</p>
        <p class="mb-2 text-xs text-text-muted">Tambahkan akun lain di luar Bag di atas (opsional). Akun yang dipilih ikut menjadi tujuan disposisi dan bisa mengisi responsnya sendiri.</p>
        <div class="space-y-3">
            <div class="relative">
                <input type="text" wire:model.live.debounce.300ms="cariUserTembusan"
                    placeholder="Cari nama untuk ditambahkan..."
                    class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                @if (trim($cariUserTembusan) !== '')
                    <div class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                        @forelse ($this->opsiUserTembusan as $opsi)
                            <button type="button" wire:click="pilihUserTembusan({{ $opsi['id'] }})"
                                class="block w-full px-3.5 py-2 text-left text-sm text-text-dark transition hover:bg-primary-green/10">
                                {{ $opsi['nama'] }}
                            </button>
                        @empty
                            <p class="px-3.5 py-2 text-sm text-text-muted">Tidak ditemukan</p>
                        @endforelse
                    </div>
                @endif
            </div>

            @if ($tembusanManualTerpilih)
                <ol class="space-y-1.5">
                    @foreach ($tembusanManualTerpilih as $i => $t)
                        <li class="flex items-center gap-2 rounded-lg bg-app-background px-2.5 py-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span class="flex-1 truncate text-sm font-medium text-text-dark">{{ $t['nama'] }}</span>
                            <button type="button" wire:click="hapusDariTembusanManual({{ $i }})"
                                class="rounded p-1 text-app-error hover:bg-app-error/10" title="Hapus dari tembusan">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
@endif

@if ($this->bisaEditApproval())
    <div class="mb-4">
        @include('livewire.partials.surat-review-keputusan-form', ['terkunci' => false, 'belumGiliran' => false, 'revisiTerkunci' => false])
    </div>
@elseif (!$gateLolos)
    <div class="mb-4 rounded-lg border border-gray-200 bg-app-background p-3 text-sm text-text-dark">
        {{ $surat->status === 'ditolak'
            ? 'Surat ini ditolak pada tahap pemeriksaan, tidak diteruskan ke pejabat tujuan.'
            : 'Menunggu persetujuan Turmin/Kasubditbinum sebelum diteruskan ke pejabat tujuan.' }}
    </div>
@endif

@if ($gateLolos && !empty($instruksiFinal))
    <div class="mb-4">
        <p class="mb-2 text-sm font-semibold text-text-dark">Instruksi Disposisi</p>
        <div class="rounded-lg border border-gray-200 bg-app-background p-3 text-sm text-text-dark">
            {{ collect($instruksiFinal)->map(fn ($k) => $instruksiValid[$k] ?? $k)->implode(', ') }}
        </div>
    </div>
@endif

@if ($gateLolos)
    <div>
        <p class="mb-1 text-sm font-semibold text-text-dark">Isi Disposisi per Pejabat</p>
        <p class="mb-3 text-xs text-text-muted">Setiap pejabat tujuan mengisi responsnya masing-masing.</p>
        <div class="space-y-3">
            @foreach ($this->disposisiRows()->sortBy('role') as $d)
                @php
                    $sudahDirespon = filled($d->catatan);
                    $bisaEditKartu = auth()->user()->nama === $d->role;
                @endphp
                <div class="rounded-lg border border-gray-200 bg-app-surface p-3" wire:key="disposisi-{{ $d->role }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-text-dark">{{ $d->role }}</p>
                        <span class="shrink-0 text-xs font-semibold {{ $sudahDirespon ? 'text-secondary-green' : 'text-app-error' }}">
                            {{ $sudahDirespon ? 'Sudah Direspon' : 'Belum Direspon' }}
                        </span>
                    </div>
                    @if ($sudahDirespon && $d->diproses_at)
                        <p class="mt-0.5 text-xs text-text-muted">oleh {{ $d->diproses_oleh ?? '-' }} pada {{ \Illuminate\Support\Carbon::parse($d->diproses_at)->format('d/m/Y H:i') }}</p>
                    @endif
                    <div class="mt-2">
                        @if ($bisaEditKartu)
                            <textarea
                                wire:model="disposisiCatatan.{{ $d->role }}" rows="3"
                                placeholder="Tuliskan disposisi/instruksi tindak lanjut..."
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                            ></textarea>
                            @if ($errorRole[$d->role] ?? null)
                                <p class="mt-1 text-xs text-app-error">{{ $errorRole[$d->role] }}</p>
                            @endif

                            @php $kabagInfo = $this->kabagInfoUntuk($d->role); @endphp
                            @if ($kabagInfo)
                                <div class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="mb-1 text-xs font-semibold text-text-dark">Teruskan ke (opsional)</p>
                                    <p class="mb-2 text-[11px] text-text-muted">Pilih anggota Bag "{{ $kabagInfo['nama'] }}" yang dituju.</p>
                                    @if (empty($kabagInfo['anggota_masuk']))
                                        <p class="text-xs italic text-text-muted">Bag ini belum punya Penerima Disposisi terdaftar.</p>
                                    @else
                                        <div class="space-y-1 rounded-lg border border-gray-200 p-2">
                                            @foreach ($kabagInfo['anggota_masuk'] as $a)
                                                <label class="flex items-center gap-2.5 rounded px-1 py-1 text-sm text-text-dark hover:bg-app-background">
                                                    <input type="checkbox" wire:model="kabagAnggotaTerpilih.{{ $d->role }}" value="{{ $a['user_id'] }}" class="rounded border-gray-300 text-primary-green focus:ring-primary-green">
                                                    <span>{{ $a['nama'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @php $rekanInfo = $kabagInfo ? null : $this->rekanSebagUntuk($d->role); @endphp
                            @if ($rekanInfo && !empty($rekanInfo['anggota']))
                                <div class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="mb-1 text-xs font-semibold text-text-dark">Teruskan ke rekan sebag (opsional)</p>
                                    <p class="mb-2 text-[11px] text-text-muted">Rekan Penerima Disposisi di Bag "{{ $rekanInfo['bag'] }}". Anda hanya bisa membatalkan terusan yang Anda buat sendiri &amp; belum direspon.</p>
                                    <div class="space-y-1 rounded-lg border border-gray-200 p-2">
                                        @foreach ($rekanInfo['anggota'] as $a)
                                            @php $terkunci = $a['punya_baris'] && !$a['bisa_uncheck']; @endphp
                                            <label class="flex items-center gap-2.5 rounded px-1 py-1 text-sm text-text-dark hover:bg-app-background {{ $terkunci ? 'opacity-70' : '' }}">
                                                <input type="checkbox" wire:model="rekanSebagTerpilih.{{ $d->role }}" value="{{ $a['user_id'] }}"
                                                    @disabled($terkunci)
                                                    class="rounded border-gray-300 text-primary-green focus:ring-primary-green disabled:opacity-60">
                                                <span>{{ $a['nama'] }}
                                                    @if ($terkunci)
                                                        <span class="text-[11px] text-text-muted">(sudah menerima)</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <button
                                type="button" wire:click="simpanDisposisi('{{ $d->role }}')"
                                wire:loading.attr="disabled" wire:target="simpanDisposisi('{{ $d->role }}')"
                                class="mt-2 w-full rounded-lg bg-secondary-green px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
                            >Simpan</button>
                        @elseif ($sudahDirespon)
                            <div class="rounded-lg border border-gray-200 bg-app-background p-2.5 text-sm leading-relaxed text-text-dark">{{ $d->catatan }}</div>
                        @else
                            <p class="text-sm italic text-text-muted">Belum diisi oleh {{ $d->role }}.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
