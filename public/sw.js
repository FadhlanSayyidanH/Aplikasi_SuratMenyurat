// Service worker khusus push notification (bar notifikasi HP/desktop,
// jalan meski tab/browser tertutup) -- TIDAK melakukan caching/offline
// apa pun, sengaja minimal supaya tidak mengganggu perilaku load halaman
// yang sudah ada. Payload dikirim dari App\Notifications\SuratPushNotification
// lewat App\Console\Commands\KirimWebPushNotifikasi (lihat WebPushMessage::toArray()).

// Langsung ambil alih tab yang sudah terbuka begitu versi sw.js baru
// ter-install -- tanpa ini, perubahan di file ini (mis. fix silent/vibrate
// di bawah) baru kepakai setelah SEMUA tab situs ini ditutup & dibuka lagi.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const url = payload.data && payload.data.url ? payload.data.url : '/';

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Surat Ditajenad', {
            body: payload.body,
            icon: payload.icon || '/images/logo_ajendam.png',
            badge: payload.badge,
            tag: payload.tag,
            data: { url },
            // Eksplisit false (bukan andalkan default browser) -- HP tetap
            // bisa senyap kalau notification channel situs ini di-set
            // "silent"/"tanpa bunyi" di pengaturan Android sendiri
            // (Chrome/Android yang pegang kendali suara, bukan kode ini),
            // tapi setidaknya kita tidak diam-diam minta silent.
            silent: false,
            vibrate: [200, 100, 200],
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
