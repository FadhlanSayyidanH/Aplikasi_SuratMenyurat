{{-- Ruang kerja tinjau/approval satu surat. Cermin surat_review_screen.dart (proyek Flutter lama). --}}
<div wire:poll.10s="pollRefresh">
    @php
        $files = $this->fileRows();
        $tahapAktifHeader = $this->tahapAktif();
        $totalTahapHeader = $this->approvalRows()->count();
    @endphp

    <div class="mb-4 rounded-xl bg-app-surface p-4 shadow-sm">
        @unless ($sedangEditData)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-base font-bold leading-snug text-text-dark">{{ $surat->perihal }}</h2>
                    {{--
                        Tanggal ringkas di baris atas ikut Surat::tanggalUrut() --
                        tanggal_input_sistem untuk Surat Masuk (kapan surat itu
                        MASUK ke sistem), bukan tanggal terbit surat itu sendiri
                        (bisa jauh lebih lama dari saat baru diterima). Tanggal
                        terbit aslinya tetap ditampilkan terpisah di baris
                        "Tanggal Surat Diterbitkan" di bawah.
                    --}}
                    <p class="mt-1 text-sm text-text-muted">
                        @if ($surat->nomor_surat) No. {{ $surat->nomor_surat }} &bull; @endif
                        {{ $surat->klasifikasi ?? '-' }} &bull; {{ $surat->tanggalUrut()->format('d/m/Y') }}
                    </p>
                    @if ($surat->jenis === 'masuk')
                        <p class="text-sm text-text-muted">Tanggal Surat Diterbitkan: {{ optional($surat->tanggal)->format('d/m/Y') }}</p>
                    @endif
                    @if ($surat->nama_pengaju)
                        <p class="text-sm text-text-muted">Diajukan oleh: {{ $surat->nama_pengaju }}</p>
                    @endif
                    @if ($surat->keterangan)
                        <p class="text-sm text-text-muted">Catatan: {{ $surat->keterangan }}</p>
                    @endif

                    @if ($surat->jenis === 'keluar' && $totalTahapHeader > 0)
                        <span class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold
                            {{ match ($surat->status) {
                                'disetujui' => 'border border-secondary-green/40 bg-secondary-green/10 text-secondary-green',
                                'ditolak' => 'border border-app-error/40 bg-app-error/10 text-app-error',
                                default => 'border border-gold/40 bg-gold/10 text-gold',
                            } }}">
                            {{ match ($surat->status) {
                                'disetujui' => 'Disetujui sepenuhnya',
                                'ditolak' => 'Ditolak -- perlu direvisi',
                                default => $tahapAktifHeader ? "Tahap {$tahapAktifHeader->urutan}/{$totalTahapHeader} -- menunggu keputusan {$tahapAktifHeader->role}" : 'Menunggu keputusan pejabat',
                            } }}
                        </span>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-dark hover:bg-app-background">
                        &larr; Kembali
                    </a>
                    @if ($this->bisaEditDataAwal())
                        <button type="button" wire:click="mulaiEditData" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-dark hover:bg-app-background">
                            Edit Data Surat
                        </button>
                    @endif
                    <button type="button" wire:click="toggleRiwayat" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-text-dark hover:bg-app-background">
                        {{ $showRiwayat ? 'Sembunyikan Riwayat' : 'Riwayat' }}
                    </button>
                </div>
            </div>
        @else
            {{--
                Edit data awal (perbaiki salah ketik) -- lihat komentar
                App\Livewire\SuratReview::mulaiEditData() kenapa jenis &
                bagian tujuan SENGAJA tidak ditawarkan di sini.
            --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-text-dark">Edit Data Surat</h3>
                    <span class="rounded-full bg-app-background px-2.5 py-1 text-[11px] font-medium text-text-muted">
                        {{ $surat->jenis === 'masuk' ? 'Surat Masuk' : 'Surat Keluar' }}
                    </span>
                </div>

                @if ($errorEditData)
                    <div class="flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        {{ $errorEditData }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @if ($surat->jenis === 'masuk')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-text-dark">Nomor Surat</label>
                            <input type="text" wire:model="editNomorSurat" placeholder="Nomor surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-text-dark">Nama Pengirim Surat</label>
                            <input type="text" wire:model="editNamaPengaju" placeholder="Nama pengirim surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        </div>
                    @endif
                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Tanggal Surat</label>
                        <input type="date" wire:model="editTanggal"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Klasifikasi</label>
                        <select wire:model.live="editKlasifikasi"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                            @foreach (\App\Models\Surat::KLASIFIKASI_OPTIONS as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                            <option value="{{ \App\Models\Surat::KLASIFIKASI_LAINNYA }}">Lainnya (isi manual)</option>
                        </select>
                    </div>
                    @if ($editKlasifikasi === \App\Models\Surat::KLASIFIKASI_LAINNYA)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-text-dark">Klasifikasi (isi manual)</label>
                            <input type="text" wire:model="editKlasifikasiLainnya" maxlength="50" placeholder="Jenis surat"
                                class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-text-dark">Perihal</label>
                        <input type="text" wire:model="editPerihal" placeholder="Perihal surat"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-text-dark">Catatan Tambahan (opsional)</label>
                        <textarea wire:model="editKeterangan" rows="2" placeholder="Catatan tambahan"
                            class="w-full rounded-lg border border-gray-300 bg-app-background px-3.5 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"></textarea>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button" wire:click="simpanEditData" wire:loading.attr="disabled" wire:target="simpanEditData"
                        class="rounded-lg bg-primary-green px-4 py-2 text-xs font-semibold text-white hover:bg-secondary-green disabled:opacity-60"
                    >Simpan Perubahan</button>
                    <button type="button" wire:click="batalEditData" class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-text-dark hover:bg-app-background">
                        Batal
                    </button>
                </div>
            </div>
        @endunless
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:items-start">
        <div class="min-w-0 lg:col-span-3 space-y-4">
            <div class="overflow-hidden rounded-xl bg-app-surface shadow-sm">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-text-dark">Dokumen Lampiran</h3>
                </div>

                @if (empty($files))
                    <p class="p-6 text-center text-sm text-text-muted">Dokumen tidak tersedia</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($files as $file)
                            <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="file-{{ $file['id'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    @if ($this->isFoto($file['file_original_name']))
                                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                                    @elseif ($this->isPdf($file['file_original_name']))
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    @else
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/>
                                    @endif
                                </svg>

                                <div class="min-w-0 flex-1">
                                    @if ($editingFileId === $file['id'])
                                        <div class="flex items-center gap-2">
                                            <input type="text" wire:model="editingFileName" class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-primary-green focus:outline-none">
                                            <button type="button" wire:click="renameFile" class="shrink-0 text-xs font-semibold text-primary-green">Simpan</button>
                                            <button type="button" wire:click="cancelRenameFile" class="shrink-0 text-xs text-text-muted">Batal</button>
                                        </div>
                                    @else
                                        <p class="truncate text-sm text-text-dark">{{ $file['file_original_name'] }}</p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-1.5 text-xs">
                                    {{--
                                        "Buka" SELALU tampil (murni melihat, tidak menyentuh
                                        rantai approval sama sekali) -- kalau memang giliran
                                        pejabat ini untuk mengedit, OnlyOffice sendiri yang
                                        mengizinkan mengedit langsung di sini (izin dicek
                                        server-side per-request oleh OnlyOfficeController,
                                        bukan oleh tombol mana yang diklik). "Buka & Edit"
                                        cuma tampil TAMBAHAN saat mengedit sekarang akan
                                        mereset keputusan yang sudah ada -- supaya pejabat
                                        yang gilirannya sudah lewat tetap bisa MELIHAT dokumen
                                        tanpa terpaksa menarik kembali proses rantainya.
                                    --}}
                                    <a
                                        href="{{ route($this->isFoto($file['file_original_name']) ? 'surat-file.annotate' : 'surat-file.editor', $file['id']) }}"
                                        class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background"
                                    >Buka</a>

                                    @if ($this->fileNeedsResetConfirm())
                                        <button
                                            type="button" wire:click="editDocumentAndOpen({{ $file['id'] }})"
                                            wire:confirm="Surat ini sudah sempat diproses sebagian. Mengedit dokumennya sekarang akan mereset keputusan mulai dari tahap Anda -- semua pejabat mulai dari posisi Anda akan memproses ulang dokumen yang baru. Keputusan tahap-tahap SEBELUM Anda tidak berubah. Lanjutkan?"
                                            class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background"
                                        >Buka &amp; Edit</button>
                                    @endif

                                    <a href="{{ $file['file_url'] }}" target="_blank" class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background">Unduh</a>

                                    @if ($this->bisaKelolaFile())
                                        <button type="button" wire:click="startRenameFile({{ $file['id'] }}, @js($file['file_original_name']))" class="rounded-lg border border-gray-300 px-2.5 py-1.5 font-medium text-text-dark hover:bg-app-background">Ubah Nama</button>
                                        <button
                                            type="button" wire:click="deleteFile({{ $file['id'] }})"
                                            wire:confirm="&quot;{{ $file['file_original_name'] }}&quot; akan dihapus permanen dari surat ini. Lanjutkan?"
                                            wire:loading.attr="disabled" wire:target="deleteFile({{ $file['id'] }})"
                                            class="rounded-lg border border-app-error/40 px-2.5 py-1.5 font-medium text-app-error hover:bg-app-error/5"
                                        >Hapus</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($errorFile && !$this->tampilkanFooterTambahFile() && !$this->bisaAjukanUlang())
                    <p class="px-4 pb-3 text-xs text-app-error">{{ $errorFile }}</p>
                @endif

                @if ($this->tampilkanFooterTambahFile())
                    <div class="border-t border-gray-200 px-4 py-3">
                        @include('livewire.partials.surat-review-tambah-file')
                    </div>
                @endif
            </div>

            @if ($showRiwayat)
                <div class="rounded-xl bg-app-surface p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold text-text-dark">Riwayat Aktivitas</h3>
                    @if (empty($riwayat))
                        <p class="text-sm text-text-muted">Belum ada riwayat aktivitas.</p>
                    @else
                        <ul class="max-h-[28rem] space-y-3 overflow-y-auto">
                            @foreach (array_reverse($riwayat) as $item)
                                <li class="border-b border-gray-100 pb-2.5 last:border-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-sm font-medium text-text-dark">
                                            {{ match ($item->aksi) { 'create' => 'Dibuat', 'update' => 'Diproses', 'delete' => 'Dihapus', 'login' => 'Login', 'logout' => 'Logout', default => $item->aksi } }}
                                            oleh {{ $item->nama }}
                                        </span>
                                        <span class="shrink-0 text-xs text-text-muted">{{ \Illuminate\Support\Carbon::parse($item->waktu)->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if ($item->keterangan)
                                        <p class="mt-1 text-xs leading-relaxed text-text-muted">{{ $item->keterangan }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        <div class="min-w-0 lg:col-span-2">
            <div class="rounded-xl bg-app-surface p-4 shadow-sm">
                @if ($surat->jenis === 'keluar')
                    @include('livewire.partials.surat-review-form-keluar')
                @else
                    @include('livewire.partials.surat-review-form-masuk')
                @endif
            </div>
        </div>
    </div>
</div>
