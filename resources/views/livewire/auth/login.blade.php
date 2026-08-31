<div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
    <a href="/" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-text-muted transition hover:text-primary-green">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M12 19l-7-7 7-7" />
        </svg>
        Kembali ke Portal
    </a>

    <div class="w-full max-w-md rounded-2xl border-t-4 border-gold bg-app-surface p-8 pt-10 shadow-xl shadow-black/5 sm:p-10">
        <div class="flex flex-col items-center text-center">
            <img src="{{ asset('images/logo_ajendam.png') }}" alt="Logo Ajudan Jenderal" class="h-24 object-contain">
            <h1 class="mt-5 text-lg font-bold tracking-wide text-primary-green">DIREKTORAT AJUDAN JENDERAL</h1>
            <p class="text-sm font-semibold tracking-widest text-text-muted">TNI ANGKATAN DARAT</p>
            <p class="mt-1.5 text-base font-bold tracking-wide text-primary-green">CARAKA-BINUM</p>
            <p class="mt-0.5 text-sm text-text-muted">
                Catatan &amp; Aplikasi Rancangan Aktivitas Komunikasi Administrasi Subditbinum
            </p>
        </div>

        {{--
            Pesan "akun ini baru login di tempat lain" -- lihat komentar
            panjang soal patch fetch() di layouts.app kenapa ini murni
            client-side (sessionStorage), BUKAN lewat $errorMessage/session
            Laravel: kick paling sering ketahuan lewat wire:poll di latar
            belakang, jauh sebelum navigasi apa pun yang bisa bawa flash
            session/query string dengan andal.
        --}}
        <div
            x-data="{ kickedMessage: null }"
            x-init="kickedMessage = sessionStorage.getItem('surat_kicked_message'); sessionStorage.removeItem('surat_kicked_message')"
            x-show="kickedMessage" x-cloak
            class="mt-8 flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2.5 text-sm text-app-error"
            role="alert"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span x-text="kickedMessage"></span>
        </div>

        <form wire:submit="login" class="mt-8 space-y-4">
            @if ($errorMessage)
                <div class="flex items-center gap-2 rounded-lg border border-app-error/30 bg-app-error/10 px-3 py-2.5 text-sm text-app-error" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            <div>
                <label for="username" class="mb-1 block text-sm font-medium text-text-dark">Username</label>
                <input
                    type="text" id="username" wire:model="username" autofocus autocomplete="username"
                    class="w-full rounded-lg border border-gray-300 bg-app-background px-4 py-3 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                >
                @error('username') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ show: false }">
                <label for="password" class="mb-1 block text-sm font-medium text-text-dark">Password</label>
                <div class="relative">
                    <input
                        :type="show ? 'text' : 'password'" id="password" wire:model="password" autocomplete="current-password"
                        class="w-full rounded-lg border border-gray-300 bg-app-background px-4 py-3 pr-11 text-sm focus:border-primary-green focus:outline-none focus:ring-1 focus:ring-primary-green"
                    >
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-text-muted hover:text-text-dark" tabindex="-1">
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.34M1 1l22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                    </button>
                </div>
                @error('password') <p class="mt-1 text-xs text-app-error">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-green px-4 py-3.5 text-sm font-semibold tracking-wide text-white transition hover:bg-secondary-green disabled:opacity-70"
                wire:loading.attr="disabled" wire:target="login"
            >
                <svg wire:loading wire:target="login" class="h-4.5 w-4.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove wire:target="login">MASUK</span>
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-text-muted">&copy; {{ date('Y') }} Ditajenad TNI AD</p>
    </div>
</div>
