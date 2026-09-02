{{--
    Tombol mengambang "Laporkan Gangguan / Kendala" -- lihat
    App\Livewire\LaporanGangguanWidget. Menggantikan tombol WhatsApp
    "Kontak Admin" lama. Dipasang di layouts.app (halaman ber-login) saja.

    Ikon FAB di bawah bebas diganti -- saat ini pakai chat-bubble +
    tanda seru (lapor masalah).
--}}
<div x-data="{ open: false }" class="fixed bottom-5 right-5 z-40">
    <div
        x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
        @click.outside="open = false"
        class="absolute bottom-16 right-0 w-72 rounded-2xl border border-gray-200 bg-app-surface p-4 shadow-xl"
    >
        @if ($terkirim)
            <div class="py-2 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-primary-green/10 text-primary-green">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <p class="mt-2 text-sm font-semibold text-text-dark">Laporan terkirim</p>
                <p class="mt-0.5 text-xs text-text-muted">Terima kasih. Laporan Anda akan ditinjau admin.</p>
                <button
                    type="button" wire:click="laporLagi"
                    class="mt-3 text-xs font-semibold text-primary-green hover:underline"
                >Kirim laporan lain</button>
            </div>
        @else
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Laporkan Gangguan / Kendala</p>
            <p class="mt-1 text-xs text-text-muted">Menemui error, kendala pemakaian, atau punya saran soal aplikasi ini? Sampaikan di sini.</p>

            @if ($error)
                <p class="mt-2 rounded-lg bg-app-error/10 px-2.5 py-1.5 text-xs text-app-error">{{ $error }}</p>
            @endif

            <form wire:submit="kirim" class="mt-3 space-y-2.5">
                <select
                    wire:model="kategori"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >
                    @foreach (\App\Models\LaporanGangguan::KATEGORI as $kode => $label)
                        <option value="{{ $kode }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea
                    wire:model="pesan" rows="3" maxlength="1000"
                    placeholder="Jelaskan gangguan/kendala yang dialami..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                ></textarea>
                <button
                    type="submit" wire:loading.attr="disabled" wire:target="kirim"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="kirim">Kirim Laporan</span>
                    <span wire:loading wire:target="kirim">Mengirim...</span>
                </button>
            </form>
        @endif
    </div>

    <button
        type="button" @click="open = !open"
        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-green text-white shadow-lg transition hover:bg-secondary-green"
        title="Laporkan Gangguan / Kendala"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            <line x1="12" y1="7" x2="12" y2="11"/>
            <line x1="12" y1="15" x2="12.01" y2="15"/>
        </svg>
    </button>
</div>
