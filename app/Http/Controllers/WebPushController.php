<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Simpan/hapus subscription push notification browser milik user login --
 * dipanggil dari resources/js/app.js saat user klik toggle "Aktifkan
 * notifikasi HP" (lihat layouts.app). Endpoint & bentuk payload mengikuti
 * PushSubscription hasil `pushManager.subscribe()` Web Push API standar
 * (endpoint + keys.p256dh + keys.auth).
 */
class WebPushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            endpoint: $data['endpoint'],
            key: $data['keys']['p256dh'],
            token: $data['keys']['auth'],
        );

        return response()->noContent();
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->noContent();
    }
}
