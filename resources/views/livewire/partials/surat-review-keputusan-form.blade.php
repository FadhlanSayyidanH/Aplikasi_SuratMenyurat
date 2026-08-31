{{--
    Form Setujui/Tolak untuk tahap approval milik pejabat yang login --
    dipakai baik untuk rantai approval Surat Keluar maupun gerbang
    Turmin/Kasubdit pada Surat Masuk. Cermin _buildKeputusanForm() di
    surat_review_screen.dart persis, termasuk 3 kondisi kunci:
    $terkunci (surat sudah disetujui sepenuhnya, bukan pimpinan),
    $belumGiliran (tahap sebelumnya belum diputuskan -- perlu "Ambil Alih"),
    $revisiTerkunci (tahap milik sendiri sudah pernah diproses -- perlu
    "Ambil Alih untuk Revisi").
--}}
@php
    $isGateAkhir = $this->isGateAkhirDisposisi();
    $instruksiValid = config('suratapp.instruksi_disposisi_valid');
    $kunci = $terkunci || $belumGiliran || $revisiTerkunci;
@endphp

<div>
    @if ($terkunci)
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-gray-300 bg-gray-100 p-3 text-sm text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Surat ini sudah disetujui sepenuhnya -- hanya {{ $this->approverAkhirNama() ?? 'approver akhir' }} (approver terakhir pada surat ini) yang masih bisa mengubah keputusan.</span>
        </div>
    @endif

    @if ($belumGiliran)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3">
            <div class="flex items-start gap-2 text-sm text-amber-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="13 19 22 12 13 5 13 19"/><polygon points="2 19 11 12 2 5 2 19"/></svg>
                <span>
                    Belum giliran Anda -- tahap sebelumnya belum memutuskan surat ini, jadi form Keputusan &amp; Catatan
                    di bawah, tambah/ubah/hapus file lampiran, &amp; edit dokumen dulu dikunci. Tekan "Ambil Alih" untuk
                    melompati tahap-tahap tersebut (otomatis disetujui lebih dulu) &amp; membuka semuanya.
                </span>
            </div>
            <button
                type="button" wire:click="ambilAlih"
                wire:confirm="Tahap {{ $this->labelTahapSebelumnyaMenunggu() }} belum memutuskan surat ini. Kalau Anda mengambil alih proses ini sekarang, tahap-tahap tersebut akan otomatis DISETUJUI lebih dulu supaya rantai approval tetap berurutan. Yakin ingin melanjutkan?"
                wire:loading.attr="disabled" wire:target="ambilAlih"
                class="mt-3 inline-flex items-center gap-2 rounded-lg border border-amber-400 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 disabled:opacity-60"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="13 19 22 12 13 5 13 19"/><polygon points="2 19 11 12 2 5 2 19"/></svg>
                Ambil Alih
            </button>
        </div>
    @endif

    @if ($revisiTerkunci)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3">
            <div class="flex items-start gap-2 text-sm text-amber-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>
                    @if ($this->adaTahapSetelahnyaDiproses())
                        Tahap Anda sudah diproses -- form Keputusan &amp; Catatan, &amp; kelola file lampiran di bawah
                        dikunci dulu supaya tidak diam-diam berubah. Tahap setelah Anda sudah diproses juga, jadi
                        mengubah keputusan sekarang akan langsung memengaruhi status akhir surat. Tekan "Ambil Alih
                        untuk Revisi" kalau memang ingin mengubahnya.
                    @else
                        Tahap Anda sudah diproses -- form Keputusan &amp; Catatan, &amp; kelola file lampiran di bawah
                        dikunci dulu supaya tidak diam-diam berubah. Tekan "Ambil Alih untuk Revisi" kalau memang ingin
                        mengubah keputusan yang sudah dibuat.
                    @endif
                </span>
            </div>
            <button
                type="button" wire:click="bukaRevisi"
                @if ($this->adaTahapSetelahnyaDiproses())
                    wire:confirm="Tahap Anda akan direset ke &quot;menunggu&quot; supaya bisa mengubah keputusan. Tahap setelah Anda yang sudah diproses berdasarkan keputusan lama akan ikut direset ke &quot;menunggu&quot; dan harus diproses ulang. Yakin ingin melanjutkan?"
                @else
                    wire:confirm="Tahap Anda akan direset ke &quot;menunggu&quot; supaya bisa mengubah keputusan yang sudah dibuat. Yakin ingin melanjutkan?"
                @endif
                wire:loading.attr="disabled" wire:target="bukaRevisi"
                class="mt-3 inline-flex items-center gap-2 rounded-lg border border-amber-400 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 disabled:opacity-60"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-2"/></svg>
                Ambil Alih untuk Revisi
            </button>
        </div>
    @elseif ($this->isRevisiApproval())
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-gold/40 bg-gold-light/20 p-3 text-sm text-amber-900">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
            <span>
                @if ($this->adaTahapSetelahnyaDiproses())
                    Anda mengubah keputusan yang sudah pernah dibuat sebelumnya. Tahap setelah Anda sudah diproses --
                    perubahan ini akan langsung memengaruhi status akhir surat.
                @else
                    Anda mengubah keputusan yang sudah pernah dibuat sebelumnya.
                @endif
            </span>
        </div>
    @endif

    <p class="mb-2 text-sm font-semibold text-text-dark">{{ $isGateAkhir ? 'Instruksi Disposisi' : 'Keputusan' }}</p>

    @if ($isGateAkhir)
        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
            @foreach ($instruksiValid as $kode => $label)
                <label class="flex items-center gap-2 text-sm text-text-dark">
                    <input type="checkbox" wire:model="instruksiDisposisi" value="{{ $kode }}" class="rounded border-gray-300 text-primary-green focus:ring-primary-green">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    @else
        <div class="space-y-1 {{ $kunci ? 'pointer-events-none opacity-50' : '' }}">
            <label class="flex items-center gap-2 text-sm text-text-dark">
                <input type="radio" wire:model="keputusan" value="disetujui" @disabled($kunci) class="border-gray-300 text-primary-green focus:ring-primary-green">
                ACC
            </label>
            <label class="flex items-center gap-2 text-sm text-text-dark">
                <input type="radio" wire:model="keputusan" value="ditolak" @disabled($kunci) class="border-gray-300 text-primary-green focus:ring-primary-green">
                Revisi
            </label>
        </div>
    @endif

    <div class="mt-3">
        <textarea
            wire:model="catatan" rows="3" @disabled($kunci)
            placeholder="Catatan (opsional)"
            class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green disabled:opacity-60"
        ></textarea>
    </div>

    @if ($error)
        <p class="mt-2.5 text-sm text-app-error">{{ $error }}</p>
    @endif

    <div class="mt-4 flex gap-3">
        <a
            href="{{ route('dashboard') }}" wire:navigate
            class="flex-1 rounded-lg bg-app-error px-4 py-3 text-center text-sm font-semibold text-white hover:opacity-90"
        >Batalkan</a>

        @if ($kunci)
            <button type="button" disabled class="flex-1 cursor-not-allowed rounded-lg bg-gray-300 px-4 py-3 text-sm font-semibold text-white">Simpan</button>
        @elseif ($this->perluKonfirmasiUbahKeputusan())
            <button
                type="button" wire:click="konfirmasiDanSimpanRevisi"
                wire:confirm="Tahap setelah Anda sudah sempat diproses berdasarkan keputusan Anda sebelumnya. Mengubah keputusan sekarang akan langsung memengaruhi status akhir surat ini. Yakin ingin melanjutkan?"
                wire:loading.attr="disabled" wire:target="konfirmasiDanSimpanRevisi"
                class="flex-1 rounded-lg bg-secondary-green px-4 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >Simpan</button>
        @else
            <button
                type="button" wire:click="simpan"
                wire:loading.attr="disabled" wire:target="simpan"
                class="flex-1 rounded-lg bg-secondary-green px-4 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >Simpan</button>
        @endif
    </div>
</div>
