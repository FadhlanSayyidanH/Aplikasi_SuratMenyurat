{{-- Panel form kanan untuk Surat Keluar. Cermin _buildFormAreaKeluar(). --}}
@php
    $approval = $this->approvalRows();
    $tahapAktif = $this->tahapAktif();
@endphp

@if ($this->perluKonfirmasiKaur())
    <div class="mb-4 rounded-xl border border-gold/40 bg-gold-light/20 p-4">
        <p class="text-sm font-bold text-text-dark">
            {{ $surat->status === 'ditolak' ? 'Surat ini ditolak' : 'Surat ini sudah disetujui sepenuhnya' }}
        </p>
        <p class="mt-1 text-xs text-text-muted">
            @if ($surat->status === 'ditolak')
                Surat ini tidak akan diperbaiki/diajukan ulang maupun dihapus -- notifikasinya akan berhenti muncul,
                tapi suratnya sendiri TETAP tersimpan apa adanya.
            @else
                Surat keluar sudah diterima dan diarsipkan.
            @endif
        </p>
        <button
            type="button" wire:click="konfirmasiKaur"
            wire:confirm="{{ $surat->status === 'ditolak' ? 'Abaikan notifikasi surat ini?' : 'Konfirmasi penerimaan surat ini?' }}"
            wire:loading.attr="disabled" wire:target="konfirmasiKaur"
            class="mt-3 rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >{{ $surat->status === 'ditolak' ? 'Abaikan' : 'Konfirmasi' }}</button>
        @if ($error)
            <p class="mt-2 text-xs text-app-error">{{ $error }}</p>
        @endif
    </div>
@endif

@if ($surat->diproses_at)
    @php $disetujui = $surat->status === 'disetujui'; @endphp
    <div class="mb-4 rounded-lg border p-3 text-sm {{ $disetujui ? 'border-secondary-green/30 bg-secondary-green/5' : 'border-app-error/30 bg-app-error/5' }}">
        <p class="font-bold {{ $disetujui ? 'text-secondary-green' : 'text-app-error' }}">
            {{ $disetujui ? 'Disetujui' : 'Ditolak' }} pada {{ $surat->diproses_at->format('d/m/Y H:i') }}
        </p>
        @if ($surat->diproses_oleh)
            <p class="text-xs {{ $disetujui ? 'text-secondary-green' : 'text-app-error' }}">oleh {{ $surat->diproses_oleh }}</p>
        @endif
    </div>
@endif

@if ($surat->keterangan)
    <div class="mb-4">
        <p class="mb-1.5 text-sm font-semibold text-text-dark">Catatan Tambahan dari Pengirim</p>
        <div class="rounded-lg border border-gray-200 bg-app-background p-3 text-sm leading-relaxed text-text-dark">{{ $surat->keterangan }}</div>
    </div>
@endif

@if ($this->bisaAjukanUlang())
    @include('livewire.partials.surat-review-ajukan-ulang')
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

@if ($this->bisaEditRantai())
    <div class="mb-4">
        @if ($sedangEditRantai)
            @include('livewire.partials.surat-review-edit-rantai')
        @else
            <button type="button" wire:click="mulaiEditRantai" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-text-dark hover:bg-app-background">
                Ubah Rantai Proses
            </button>
        @endif
    </div>
@endif

@if ($this->terkunciSetelahDisetujui())
    @include('livewire.partials.surat-review-keputusan-form', ['terkunci' => true, 'belumGiliran' => false, 'revisiTerkunci' => false])
@elseif ($this->revisiPerluDibukaDulu())
    @include('livewire.partials.surat-review-keputusan-form', ['terkunci' => false, 'belumGiliran' => false, 'revisiTerkunci' => true])
@elseif ($this->bisaEditApproval())
    @include('livewire.partials.surat-review-keputusan-form', ['terkunci' => false, 'belumGiliran' => false, 'revisiTerkunci' => false])
@elseif ($this->bisaProsesBelumGiliran())
    @include('livewire.partials.surat-review-keputusan-form', ['terkunci' => false, 'belumGiliran' => true, 'revisiTerkunci' => false])
@else
    <div>
        <p class="mb-2 text-sm font-semibold text-text-dark">Status</p>
        <div class="rounded-lg border border-gray-200 bg-app-background p-3 text-sm text-text-dark">
            @if ($surat->status === 'disetujui')
                Disetujui
            @elseif ($surat->status === 'ditolak')
                Ditolak
            @else
                {{ $tahapAktif ? "Sedang Proses -- menunggu keputusan {$tahapAktif->role}" : 'Menunggu keputusan pejabat' }}
            @endif
        </div>
        @if ($surat->catatan_proses)
            <p class="mb-1.5 mt-3 text-sm font-semibold text-text-dark">Catatan</p>
            <div class="rounded-lg border border-gray-200 bg-app-background p-3 text-sm leading-relaxed text-text-dark">{{ $surat->catatan_proses }}</div>
        @endif
    </div>
@endif
