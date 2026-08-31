// Web Push (notifikasi bar HP/desktop, jalan meski browser tertutup) --
// lihat toggle "Aktifkan notifikasi HP" di layouts.app,
// App\Http\Controllers\WebPushController, public/sw.js.

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

async function kirimKeServer(url, subscription) {
    await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(subscription),
    });
}

window.webPushDidukung = function webPushDidukung() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
};

window.webPushStatus = async function webPushStatus() {
    if (!window.webPushDidukung()) {
        return 'unsupported';
    }
    const reg = await navigator.serviceWorker.register('/sw.js');
    const sub = await reg.pushManager.getSubscription();
    if (!sub) {
        return 'unsubscribed';
    }

    // Subscription push itu MILIK BROWSER/PERANGKAT, bukan milik akun --
    // kalau perangkat ini sebelumnya sudah "Aktifkan Notifikasi HP" saat
    // login sebagai akun lain (perangkat/laptop dipakai bergantian oleh
    // beberapa akun test/pejabat), endpoint yang sama masih akan
    // terdeteksi ada di sini walau BELUM PERNAH didaftarkan untuk akun
    // yang SEDANG login. Selaraskan ulang kepemilikannya ke akun yang
    // sedang login tiap kali status dicek (WebPushController::subscribe()
    // -> updatePushSubscription() memindah kepemilikan endpoint yang sama
    // ke user baru) -- supaya push berikutnya terkirim ke akun yang benar,
    // bukan diam-diam masih ke akun sebelumnya di perangkat ini.
    await kirimKeServer('/webpush/subscribe', sub.toJSON());
    return 'subscribed';
};

window.webPushAktifkan = async function webPushAktifkan(vapidPublicKey) {
    const reg = await navigator.serviceWorker.register('/sw.js');
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return 'denied';
    }

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });
    }

    await kirimKeServer('/webpush/subscribe', sub.toJSON());
    return 'subscribed';
};

window.webPushMatikan = async function webPushMatikan() {
    const reg = await navigator.serviceWorker.getRegistration('/sw.js');
    const sub = reg && (await reg.pushManager.getSubscription());
    if (sub) {
        await kirimKeServer('/webpush/unsubscribe', { endpoint: sub.endpoint });
        await sub.unsubscribe();
    }
    return 'unsubscribed';
};
