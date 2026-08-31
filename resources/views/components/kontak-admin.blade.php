{{--
    Tombol mengambang "Kontak Admin" -- saluran bantuan langsung ke admin
    lewat WhatsApp kalau user menemui kendala/mau kasih masukan. Dipasang
    di kedua layout (app.blade.php utk halaman login/auth) SUPAYA tetap
    tersedia bahkan sebelum/tanpa login (mis. kendala saat login sendiri).
--}}
<div x-data="{ kontakAdminOpen: false }" class="fixed bottom-5 right-5 z-40">
    <div
        x-show="kontakAdminOpen" x-cloak
        x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
        @click.outside="kontakAdminOpen = false"
        class="absolute bottom-16 right-0 w-64 rounded-2xl border border-gray-200 bg-app-surface p-4 shadow-xl"
    >
        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Kontak Admin</p>
        <p class="mt-1 text-sm font-semibold text-text-dark">{{ config('suratapp.kontak_admin_nama') }}</p>
        <p class="mt-0.5 text-xs text-text-muted">Ada kendala atau masukan soal aplikasi ini? Hubungi langsung lewat WhatsApp.</p>
        <a
            href="https://wa.me/{{ config('suratapp.kontak_admin_wa') }}" target="_blank" rel="noopener"
            class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39a9.9 9.9 0 0 0 4.75 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm0 18.1h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.14.82.84-3.06-.2-.32a8.19 8.19 0 0 1-1.26-4.4c0-4.53 3.7-8.22 8.26-8.22 2.2 0 4.27.86 5.83 2.42a8.17 8.17 0 0 1 2.42 5.82c0 4.53-3.7 8.22-8.25 8.22Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.4-.12-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.04-.38-1.99-1.22-.73-.66-1.23-1.46-1.37-1.71-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.35-.77-1.84-.2-.49-.41-.42-.56-.42-.14 0-.31-.02-.48-.02-.17 0-.44.06-.67.31-.23.25-.87.85-.87 2.08 0 1.22.89 2.41 1.02 2.58.13.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.19.21-.58.21-1.08.15-1.19-.06-.11-.23-.17-.48-.29Z"/></svg>
            Chat via WhatsApp
        </a>
    </div>

    <button
        type="button" @click="kontakAdminOpen = !kontakAdminOpen"
        class="flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:brightness-95"
        title="Kontak Admin"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39a9.9 9.9 0 0 0 4.75 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm0 18.1h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.14.82.84-3.06-.2-.32a8.19 8.19 0 0 1-1.26-4.4c0-4.53 3.7-8.22 8.26-8.22 2.2 0 4.27.86 5.83 2.42a8.17 8.17 0 0 1 2.42 5.82c0 4.53-3.7 8.22-8.25 8.22Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.4-.12-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.04-.38-1.99-1.22-.73-.66-1.23-1.46-1.37-1.71-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.35-.77-1.84-.2-.49-.41-.42-.56-.42-.14 0-.31-.02-.48-.02-.17 0-.44.06-.67.31-.23.25-.87.85-.87 2.08 0 1.22.89 2.41 1.02 2.58.13.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.19.21-.58.21-1.08.15-1.19-.06-.11-.23-.17-.48-.29Z"/></svg>
    </button>
</div>
