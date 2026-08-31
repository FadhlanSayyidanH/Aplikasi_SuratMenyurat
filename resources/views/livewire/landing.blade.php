<div class="flex min-h-screen items-center justify-center px-6 py-12">
    <div class="w-full max-w-2xl text-center">
        <img src="{{ asset('images/logo_ajendam.png') }}" alt="Logo Ajudan Jenderal" class="mx-auto h-24 object-contain">
        <h1 class="mt-5 text-xl font-bold tracking-wide text-primary-green">DIREKTORAT AJUDAN JENDERAL</h1>
        <p class="text-base font-semibold tracking-widest text-text-muted">TNI ANGKATAN DARAT</p>
        <p class="mt-1.5 text-lg font-bold tracking-wide text-primary-green">CARAKA-BINUM</p>
        <p class="mt-1 text-sm text-text-muted">
            Catatan &amp; Aplikasi Rancangan Aktivitas Komunikasi Administrasi Subditbinum
        </p>

        <div class="mx-auto mt-10 max-w-md rounded-2xl border-t-4 border-primary-green bg-app-surface p-8 shadow-xl shadow-black/5">
            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-primary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h2 class="mt-4 text-lg font-bold text-text-dark">Login Pengguna</h2>
            <p class="mt-2 text-sm text-text-muted">
                Masuk sebagai petugas untuk mengelola dan memantau seluruh surat masuk &amp; keluar.
            </p>
            <a
                href="{{ route('login') }}" wire:navigate
                class="mt-6 block w-full rounded-lg bg-primary-green px-4 py-3.5 text-center text-sm font-semibold tracking-wide text-white transition hover:bg-secondary-green"
            >Login</a>
        </div>

        <p class="mt-8 text-xs text-text-muted">&copy; {{ date('Y') }} Ditajenad TNI AD</p>
    </div>
</div>
