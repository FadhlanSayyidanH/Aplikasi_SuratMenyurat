<div>
    @if ($show)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:key="change-password-backdrop">
            <div class="w-full max-w-sm rounded-2xl bg-app-surface p-6 shadow-xl" @click.outside="$wire.close()">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-bold text-text-dark">Ganti Password</h2>
                    <button type="button" wire:click="close" class="text-text-muted hover:text-text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <form wire:submit="submit" class="space-y-3">
                    @if ($errorMessage)
                        <div class="rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2 text-xs text-app-error">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Password Lama</label>
                        <input type="password" wire:model="passwordLama" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Password Baru</label>
                        <input type="password" wire:model="passwordBaru" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-text-dark">Konfirmasi Password Baru</label>
                        <input type="password" wire:model="passwordBaruKonfirmasi" class="w-full rounded-lg border border-gray-300 bg-app-background px-3 py-2.5 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green">
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" wire:click="close" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-text-dark hover:bg-app-background">Batal</button>
                        <button type="submit" class="flex-1 rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary-green">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
