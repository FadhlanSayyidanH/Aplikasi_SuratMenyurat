<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - Surat Ditajenad' : 'Surat Menyurat - Ditajenad TNI AD' }}</title>
    <link rel="icon" href="{{ asset('images/logo_ajendam.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    class="bg-app-background font-sans text-text-dark antialiased"
    x-data="{
        mobileNavOpen: false, toasts: [], notifPopups: [],
        webPushStatus: 'unsupported',
        webPushReminderVisible: false, webPushReminderStep: 'ask',
        async initWebPush() {
            if (!window.webPushDidukung || !window.webPushDidukung()) return;
            this.webPushStatus = await window.webPushStatus();
            @auth
            this.cekIngatkanWebPush();
            @endauth
        },
        cekIngatkanWebPush() {
            // Cuma ingatkan kalau belum pernah subscribe sama sekali --
            // status 'denied'/'unsupported' sudah tidak relevan diingatkan
            // lagi lewat popup ini (denied cuma bisa diaktifkan lewat
            // pengaturan browser, bukan tombol kita). 'Nanti Saja' menunda
            // 24 jam per akun (bukan per perangkat) lewat localStorage,
            // supaya di perangkat yang dipakai bergantian beberapa akun
            // (lihat catatan multi-akun-satu-perangkat di webPushStatus()),
            // snooze akun A tidak ikut membungkam pengingat untuk akun B.
            if (this.webPushStatus !== 'unsubscribed') return;
            const snoozeKey = 'webpush_reminder_snooze_{{ auth()->id() }}';
            if (Date.now() < Number(localStorage.getItem(snoozeKey) || 0)) return;
            this.webPushReminderStep = 'ask';
            this.webPushReminderVisible = true;
        },
        async toggleWebPush() {
            this.webPushStatus = this.webPushStatus === 'subscribed'
                ? await window.webPushMatikan()
                : await window.webPushAktifkan('{{ config('webpush.vapid.public_key') }}');
        },
        async aktifkanDariReminder() {
            this.webPushStatus = await window.webPushAktifkan('{{ config('webpush.vapid.public_key') }}');
            if (this.webPushStatus === 'subscribed') {
                this.webPushReminderStep = 'done';
            } else {
                this.webPushReminderVisible = false;
            }
        },
        nantiSajaReminder() {
            localStorage.setItem('webpush_reminder_snooze_{{ auth()->id() }}', String(Date.now() + 24 * 60 * 60 * 1000));
            this.webPushReminderVisible = false;
        },
    }"
    x-init="initWebPush()"
    @notifikasi-baru.window="notifPopups.push({ id: Date.now(), events: $event.detail.events }); new Audio('{{ asset('audio/notifikasi.wav') }}').play().catch(() => {})"
    @notify.window="toasts.push({ id: Date.now(), message: $event.detail.message }); setTimeout(() => toasts.shift(), 4000)"
>
    {{--
        Toast/pop-up notifikasi SENGAJA ditaruh di layout (bukan di dalam
        view Dashboard) supaya elemen & state Alpine-nya TIDAK ikut
        di-morph ulang setiap wire:poll.8s="pollLive" Dashboard menembak --
        sebelumnya x-data ini ada di root Dashboard sendiri, jadi TIAP poll
        (bukan cuma yang benar-benar berisi notifikasi baru) me-morph ulang
        elemen yang sama, kadang balapan tepat saat event 'notifikasi-baru'
        di-dispatch -- state Alpine (notifPopups) bisa keburu direset/lepas
        scope pas race itu (muncul di console sebagai "notifPopups is not
        defined"), jadi pop-up & suaranya senyap tidak pernah muncul. Di
        layout, elemen ini murni statis (tidak pernah dirender ulang server)
        sehingga tidak mungkin lagi balapan dengan morph Livewire manapun.
    --}}
    <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-[min(24rem,calc(100vw-3rem))] flex-col gap-2" x-cloak>
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto rounded-xl border border-gold/40 bg-app-surface p-4 shadow-xl">
                <p class="text-sm text-text-dark" x-text="toast.message"></p>
            </div>
        </template>
    </div>

    <div
        x-show="notifPopups.length > 0" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="notifPopups = []"
    >
        <div class="flex max-h-[85vh] w-full max-w-sm flex-col gap-3 overflow-y-auto">
            <template x-for="popup in notifPopups" :key="popup.id">
                <div class="rounded-2xl bg-app-surface p-5 text-center shadow-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9 text-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22a2.5 2.5 0 0 0 2.5-2.5h-5A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.83V3a1.5 1.5 0 0 0-3 0v1.17A7 7 0 0 0 5 11v5l-1.6 1.6a1 1 0 0 0 .7 1.7h15.8a1 1 0 0 0 .7-1.7L19 16Z"/></svg>
                    <p class="mt-2 text-base font-bold text-text-dark" x-text="popup.events.length === 1 ? popup.events[0].judul : 'Notifikasi Baru'"></p>

                    <div class="mt-4 space-y-1 text-left">
                        <template x-for="ev in popup.events" :key="ev.id">
                            <a :href="`{{ url('surat') }}/${ev.id}`" class="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-app-background">
                                <span
                                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                    :class="{ 'bg-gold': ev.warna === 'gold', 'bg-app-error': ev.warna === 'red', 'bg-secondary-green': ev.warna === 'green' }"
                                ></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-text-dark" x-text="ev.judul"></span>
                                    <span class="block truncate text-xs text-text-muted" x-text="ev.perihal"></span>
                                </span>
                            </a>
                        </template>
                    </div>

                    <button
                        type="button"
                        class="mt-4 w-full rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green"
                        @click="notifPopups = notifPopups.filter(p => p.id !== popup.id)"
                    >Tutup</button>
                </div>
            </template>
        </div>
    </div>

    {{--
        Floating reminder "Aktifkan Notifikasi HP" -- muncul otomatis kalau
        browser DIDUKUNG Push API tapi user belum pernah subscribe sama
        sekali (bukan tombol manual di sidebar, lihat cekIngatkanWebPush()
        di atas). "Nanti Saja" menunda 24 jam per akun (localStorage),
        bukan menyembunyikan permanen -- supaya user yang menunda tetap
        diingatkan lagi besok kalau masih belum aktif.
    --}}
    <div
        x-show="webPushReminderVisible" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
        <div class="w-full max-w-sm rounded-2xl bg-app-surface p-5 text-center shadow-2xl">
            <template x-if="webPushReminderStep === 'ask'">
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9 text-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22a2.5 2.5 0 0 0 2.5-2.5h-5A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.83V3a1.5 1.5 0 0 0-3 0v1.17A7 7 0 0 0 5 11v5l-1.6 1.6a1 1 0 0 0 .7 1.7h15.8a1 1 0 0 0 .7-1.7L19 16Z"/></svg>
                    <p class="mt-3 text-base font-bold text-text-dark">Aktifkan Notifikasi HP?</p>
                    <p class="mt-2 text-sm text-text-muted">Supaya Anda tidak ketinggalan surat yang perlu ditindak, disetujui, atau ditolak, aktifkan notifikasi HP/desktop. Notifikasi akan tetap masuk ke bar notifikasi meski aplikasi ini tidak sedang dibuka.</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <button
                            type="button" @click="aktifkanDariReminder()"
                            class="w-full rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green"
                        >Aktifkan Notifikasi</button>
                        <button
                            type="button" @click="nantiSajaReminder()"
                            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-text-muted transition hover:bg-app-background"
                        >Nanti Saja</button>
                    </div>
                </div>
            </template>
            <template x-if="webPushReminderStep === 'done'">
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9 text-secondary-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9.5"/><path d="m8 12.5 2.5 2.5L16 9.5"/></svg>
                    <p class="mt-3 text-base font-bold text-text-dark">Notifikasi HP Aktif</p>
                    <p class="mt-2 text-sm text-text-muted">Agar notifikasi tetap masuk, <b>jangan logout</b> -- cukup tutup aplikasi/browsernya saja. Notifikasi tetap akan sampai ke bar notifikasi HP/desktop meski aplikasi sedang tidak dibuka.</p>
                    <button
                        type="button" @click="webPushReminderVisible = false"
                        class="mt-4 w-full rounded-lg bg-primary-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary-green"
                    >Mengerti</button>
                </div>
            </template>
        </div>
    </div>

    <div class="flex min-h-screen">
        {{-- Overlay mobile --}}
        <div x-show="mobileNavOpen" x-cloak @click="mobileNavOpen = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full transform flex-col border-r border-gray-200 bg-primary-green text-white transition-transform duration-200 lg:static lg:translate-x-0"
            :class="mobileNavOpen && '!translate-x-0'"
        >
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <img src="{{ asset('images/logo_ajendam.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                <div class="leading-tight">
                    <p class="text-sm font-bold tracking-wide">CARAKA-BINUM</p>
                    <p class="text-[11px] text-white/60">Ditajenad TNI AD</p>
                </div>
            </div>

            @auth
                <div class="border-b border-white/10 p-4">
                    <div class="mb-3 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-bold">
                            {{ strtoupper(substr(auth()->user()->nama, 0, 1)) }}
                        </div>
                        <div class="min-w-0 leading-tight">
                            <p class="truncate text-sm font-medium">{{ auth()->user()->nama }}</p>
                            <p class="text-[11px] capitalize text-white/50">{{ auth()->user()->role }}</p>
                        </div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-xs font-medium text-white/80 transition hover:bg-white/5 hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Keluar
                        </button>
                    </form>

                    {{--
                        Toggle push notification (bar notifikasi HP/desktop, jalan
                        meski browser tertutup) -- lihat resources/js/app.js,
                        App\Http\Controllers\WebPushController, public/sw.js.
                        Disembunyikan kalau browser tidak dukung Push API sama
                        sekali (mis. Safari iOS di luar mode "Add to Home Screen").
                    --}}
                    <button
                        type="button"
                        x-show="webPushStatus !== 'unsupported'" x-cloak
                        @click="toggleWebPush()"
                        class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-xs font-medium text-white/80 transition hover:bg-white/5 hover:text-white"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22a2.5 2.5 0 0 0 2.5-2.5h-5A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.83V3a1.5 1.5 0 0 0-3 0v1.17A7 7 0 0 0 5 11v5l-1.6 1.6a1 1 0 0 0 .7 1.7h15.8a1 1 0 0 0 .7-1.7L19 16Z"/></svg>
                        <span x-text="webPushStatus === 'subscribed' ? 'Notifikasi HP Aktif' : (webPushStatus === 'denied' ? 'Notifikasi Diblokir Browser' : 'Aktifkan Notifikasi HP')"></span>
                    </button>
                </div>
            @endauth

            <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
                <a
                    href="{{ route('dashboard') }}" wire:navigate
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition {{ request()->routeIs('dashboard') && !request()->query('menu') ? 'bg-white/10 text-white' : 'text-white/80 hover:bg-white/5 hover:text-white' }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                    Dashboard
                </a>

                {{--
                    Menu folder Surat Masuk/Surat Keluar/Jajaran -- dirender di sini
                    (langsung di layout, BUKAN lewat @section dari view Dashboard)
                    supaya SELALU tampil sama persis di SEMUA halaman, bukan cuma
                    saat komponen Dashboard yang aktif (lihat komentar di
                    App\Services\SidebarMenuService & resources/views/livewire/dashboard.blade.php).
                    Aktif/tidaknya sebuah item dicek dari query string ?menu= URL
                    saat ini (bukan properti komponen Livewire manapun), karena
                    partial ini dirender di luar boundary komponen apa pun.
                --}}
                @auth
                    @php
                        $sidebarMenu = new \App\Services\SidebarMenuService(auth()->user());
                        $sidebarActiveMenu = request()->routeIs('dashboard') ? request()->query('menu') : null;
                        $sidebarActiveAnggota = request()->routeIs('dashboard') ? request()->query('anggota') : null;
                        $sidebarJajaran = $sidebarMenu->isPimpinan() ? $sidebarMenu->namaJajaranSaya() : [];
                    @endphp
                    <div class="mt-2">
                        <p class="mt-2 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Surat Masuk</p>
                        @foreach ($sidebarMenu->menuMasukList() as $item)
                            @php $badge = $sidebarMenu->badgeUntukMenu($item); @endphp
                            <a
                                href="{{ route('dashboard', ['menu' => $item->value]) }}" wire:navigate
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition {{ $sidebarActiveMenu === $item->value ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $item->labelSidebar() }}</span>
                                @if ($badge)
                                    <span class="shrink-0 rounded-full bg-app-error px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach

                        <p class="mt-5 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Surat Keluar</p>
                        @foreach ($sidebarMenu->menuKeluarList() as $item)
                            @php $badge = $sidebarMenu->badgeUntukMenu($item); @endphp
                            <a
                                href="{{ route('dashboard', ['menu' => $item->value]) }}" wire:navigate
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition {{ $sidebarActiveMenu === $item->value ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $item->labelSidebar() }}</span>
                                @if ($badge)
                                    <span class="shrink-0 rounded-full bg-app-error px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>
                                @endif
                            </a>
                        @endforeach

                        @if ($sidebarMenu->isPimpinan())
                            <p class="mt-5 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Surat Masuk Jajaran</p>
                            @forelse ($sidebarJajaran as $nama)
                                <a
                                    href="{{ route('dashboard', ['menu' => 'jajaran-anggota', 'anggota' => $nama]) }}" wire:navigate
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition {{ $sidebarActiveMenu === 'jajaran-anggota' && $sidebarActiveAnggota === $nama ? 'bg-white/10 font-medium text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                                >
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $nama }}</span>
                                </a>
                            @empty
                                <p class="px-3 text-xs text-white/40">Belum ada anggota jajaran diatur admin.</p>
                            @endforelse
                        @endif
                    </div>
                @endauth

                @auth
                    @if (auth()->user()->role === 'admin')
                        @php $jmlLaporanBaru = \App\Models\LaporanGangguan::where('status', 'baru')->count(); @endphp
                        <p class="mt-6 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Sistem</p>
                        @foreach ([
                            ['route' => 'activity-log.index', 'label' => 'Log Aktivitas'],
                            ['route' => 'storage.index', 'label' => 'Storage Server'],
                            ['route' => 'users.index', 'label' => 'User'],
                            ['route' => 'bag.masuk', 'label' => 'Bag Surat Masuk'],
                            ['route' => 'bag.keluar', 'label' => 'Bag Surat Keluar'],
                            ['route' => 'hak-akses.index', 'label' => 'Hak Akses'],
                            ['route' => 'struktur-anggota.index', 'label' => 'Struktur Anggota'],
                            ['route' => 'pengumuman.index', 'label' => 'Pengumuman'],
                            ['route' => 'laporan-gangguan.index', 'label' => 'Laporan Gangguan', 'badge' => $jmlLaporanBaru],
                        ] as $item)
                            <a
                                href="{{ route($item['route']) }}" wire:navigate
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition {{ request()->routeIs($item['route']) ? 'bg-white/10 text-white font-medium' : 'text-white/70 hover:bg-white/5 hover:text-white' }}"
                            >
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gold"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @if (!empty($item['badge']))
                                    <span class="shrink-0 rounded-full bg-app-error px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endif
                @endauth

                <p class="mt-6 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-white/40">Akun</p>
                <button
                    type="button" @click="$dispatch('open-change-password')"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-white/70 transition hover:bg-white/5 hover:text-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Ganti Password
                </button>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center gap-3 border-b border-gray-200 bg-app-surface px-4 py-3 lg:px-6">
                <button type="button" @click="mobileNavOpen = true" class="rounded-lg p-2 text-text-muted hover:bg-app-background lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 class="min-w-0 flex-1 truncate text-base font-semibold text-text-dark">{{ $title ?? 'Dashboard' }}</h1>
                {{ $topbarActions ?? '' }}
            </header>

            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @auth
        @livewire('auth.change-password-modal')
    @endauth

    @livewire('laporan-gangguan-widget')

    {{--
        App\Http\Middleware\EnsureSingleWebSession menendang sesi lama saat
        akun yang sama login ulang di tempat lain -- tapi request PERTAMA
        yang menyadarinya BIASANYA bukan navigasi halaman penuh yang
        dilihat user (dia sedang diam di halaman ini), melainkan
        wire:poll.8s Dashboard yang menembak POST /livewire/update di latar
        belakang. Livewire mengenali request itu sebagai permintaan JSON
        (header Accept), jadi middleware membalas 401 JSON (BUKAN redirect
        HTML + flash session) -- kalau dibiarkan begitu saja, user cuma
        diam di halaman yang sudah tidak nyambung sampai mereka klik
        sesuatu sendiri, TANPA pernah melihat pesan alasannya. Patch
        fetch() di sini (SEBELUM livewire.js dimuat di bawah, supaya
        Livewire otomatis memakai versi yang sudah di-patch ini) khusus
        menyimak response POST /livewire/update -- begitu 401, taruh
        pesannya di sessionStorage (BUKAN query string/redirect
        server-side -- terbukti tidak konsisten selamat lewat rangkaian
        redirect yang dipicu di tengah request AJAX latar belakang begini)
        lalu paksa pindah ke halaman login SUNGGUHAN. Halaman login (lihat
        resources/views/livewire/auth/login.blade.php) membaca
        sessionStorage ini murni lewat Alpine saat halaman dimuat.
    --}}
    <script>
        (function () {
            var originalFetch = window.fetch;
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url) || '';
                return originalFetch.apply(this, arguments).then(function (response) {
                    if (response.status === 401 && url.indexOf('/livewire/update') !== -1) {
                        sessionStorage.setItem('surat_kicked_message', 'Anda sudah keluar -- akun ini baru saja login di perangkat/browser lain.');
                        window.location.href = '/login';
                        return new Promise(function () {});
                    }

                    return response;
                });
            };
        })();
    </script>

    @livewireScripts

    {{--
        Pemicu toast pesan sukses SETELAH redirect PENUH (mis.
        App\Livewire\Surat\SuratForm::submit() -> Dashboard, redirect()
        TANPA navigate:true SENGAJA -- lihat komentar di sana). <script>
        polos, BUKAN x-init di <body> -- x-init TERNYATA tidak konsisten
        tereksekusi otomatis oleh Alpine di elemen <body> ini walau halaman
        dimuat penuh dari nol (Alpine.evaluate() manual dengan ekspresi
        PERSIS sama terbukti bekerja, jadi bukan soal sintaksnya).

        SENGAJA dibungkus DOMContentLoaded, bukan langsung dieksekusi --
        <script src="livewire.js"> di atas TIDAK punya defer/async (blocking
        normal), tapi Alpine.start() sendiri baru jalan saat DOMContentLoaded
        (bukan pas skrip itu SELESAI dieksekusi) -- skrip polos di sini
        dieksekusi browser SAAT PARSING HTML (sebelum DOMContentLoaded
        selesai), jadi dispatch yang langsung/tanpa dibungkus akan tembak
        SEBELUM listener @notify.window di <body> terpasang (sudah diuji
        langsung, dispatch-nya senyap tanpa listener). Membungkus dengan
        DOMContentLoaded menjamin Alpine SUDAH pasti bootstrap duluan.
    --}}
    @if (session('status'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('notify', { detail: { message: @js(session('status')) } }));
            });
        </script>
    @endif
</body>
</html>
