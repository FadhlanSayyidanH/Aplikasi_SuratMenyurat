{{-- Kirim/cabut pengumuman ke seluruh akun -- lihat App\Livewire\PengumumanAdmin. --}}
<div>
    @if ($error)
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-4 py-2.5 text-sm text-app-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ $error }}
        </div>
    @endif

    <p class="mb-4 text-xs text-text-muted">
        Pesan akan tampil sebagai banner di dashboard SEMUA akun (semua role) sampai Anda cabut sendiri di sini --
        tidak otomatis hilang, dan tidak bisa ditutup permanen oleh user.
    </p>

    <form wire:submit="kirim" class="mb-6 rounded-xl bg-app-surface p-4 shadow-sm">
        <label class="mb-1.5 block text-sm font-medium text-text-dark">Pesan Pengumuman Baru</label>
        <textarea
            wire:model="pesan" rows="3" maxlength="1000" placeholder="Tulis pengumuman untuk seluruh akun..."
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
        ></textarea>
        <div class="mt-3 flex justify-end">
            <button
                type="submit" wire:loading.attr="disabled" wire:target="kirim"
                class="rounded-lg bg-primary-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-secondary-green disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="kirim">Kirim ke Semua Akun</span>
                <span wire:loading wire:target="kirim">Mengirim...</span>
            </button>
        </div>
    </form>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-text-muted">Pengumuman Aktif ({{ $this->daftar->count() }})</p>

    @if ($this->daftar->isEmpty())
        <p class="rounded-xl bg-app-surface p-8 text-center text-sm text-text-muted shadow-sm">Belum ada pengumuman aktif.</p>
    @else
        <div class="space-y-2.5">
            @foreach ($this->daftar as $item)
                <div wire:key="pengumuman-{{ $item->id }}" class="flex items-start gap-3 rounded-xl bg-app-surface p-4 shadow-sm">
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line text-sm text-text-dark">{{ $item->pesan }}</p>
                        <p class="mt-1.5 text-[11px] text-text-muted">{{ $item->dibuat_oleh }} &middot; {{ $item->created_at->translatedFormat('d M Y H:i') }}</p>
                    </div>
                    <button
                        type="button" wire:click="cabut({{ $item->id }})"
                        wire:confirm="Cabut pengumuman ini? Akan langsung hilang dari dashboard semua akun."
                        wire:loading.attr="disabled" wire:target="cabut({{ $item->id }})"
                        class="shrink-0 rounded-lg border border-app-error/30 px-3 py-1.5 text-xs font-medium text-app-error transition hover:bg-app-error/10 disabled:opacity-60"
                    >Cabut</button>
                </div>
            @endforeach
        </div>
    @endif
</div>
