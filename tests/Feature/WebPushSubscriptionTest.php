<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Cakupan test untuk App\Http\Controllers\WebPushController (simpan/hapus subscription push notification). */
class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(): User
    {
        return User::create([
            'username' => 'user_webpush',
            'nama' => 'User WebPush',
            'role' => 'user',
            'password' => Hash::make('Password123'),
        ]);
    }

    public function test_tamu_tidak_bisa_subscribe(): void
    {
        $this->postJson('/webpush/subscribe', [
            'endpoint' => 'https://fcm.example.test/abc',
            'keys' => ['p256dh' => 'key', 'auth' => 'token'],
        ])->assertUnauthorized();
    }

    public function test_user_login_bisa_subscribe_dan_tersimpan(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)->postJson('/webpush/subscribe', [
            'endpoint' => 'https://fcm.example.test/abc',
            'keys' => ['p256dh' => 'key-publik', 'auth' => 'token-auth'],
        ])->assertNoContent();

        $this->assertCount(1, $user->fresh()->pushSubscriptions);
        $this->assertSame('https://fcm.example.test/abc', $user->fresh()->pushSubscriptions->first()->endpoint);
    }

    public function test_subscribe_tanpa_keys_ditolak_validasi(): void
    {
        $user = $this->buatUser();

        $this->actingAs($user)->postJson('/webpush/subscribe', [
            'endpoint' => 'https://fcm.example.test/abc',
        ])->assertInvalid(['keys.p256dh', 'keys.auth']);
    }

    public function test_user_bisa_unsubscribe(): void
    {
        $user = $this->buatUser();
        $user->updatePushSubscription(endpoint: 'https://fcm.example.test/abc', key: 'k', token: 't');

        $this->actingAs($user)->postJson('/webpush/unsubscribe', [
            'endpoint' => 'https://fcm.example.test/abc',
        ])->assertNoContent();

        $this->assertCount(0, $user->fresh()->pushSubscriptions);
    }
}
