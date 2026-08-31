<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TokenAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Migrasi dari backend/public/auth_login.php, auth_logout.php,
 * auth_change_password.php (proyek PHP lama).
 */
class AuthController extends Controller
{
    public function __construct(private TokenAuthService $auth) {}

    public function login(Request $request)
    {
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            throw new ApiException('Username dan password wajib diisi', 400);
        }

        $maxAttempts = config('suratapp.login_max_attempts');
        $lockoutMinutes = config('suratapp.login_lockout_minutes');

        $user = User::query()->where('username', $username)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            throw new ApiException(
                'Terlalu banyak percobaan gagal. Coba lagi setelah '.$user->locked_until->format('H:i'),
                429,
            );
        }

        if (!$user || !Hash::check($password, $user->password)) {
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->failed_login_attempts = $attempts;
                if ($attempts >= $maxAttempts) {
                    $user->locked_until = now()->addMinutes($lockoutMinutes);
                }
                $user->save();
            }
            throw new ApiException('Username atau password salah', 401);
        }

        $token = $this->auth->generateAuthToken();
        $expiresAt = now()->addSeconds(config('suratapp.auth_token_ttl_seconds'));

        $user->token = $token;
        $user->token_expires_at = $expiresAt;
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();

        ActivityLogger::log($request, $user->username, $user->nama, 'login');

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
            'role' => $user->role,
            'token' => $token,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'bisa_input_masuk' => (bool) $user->boleh_input_masuk,
            'bisa_input_keluar' => (bool) $user->boleh_input_keluar,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log($request, $user->username, $user->nama, 'logout');

        return response()->json(['ok' => true]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $passwordLama = (string) $request->input('password_lama', '');
        $passwordBaru = (string) $request->input('password_baru', '');

        if ($passwordLama === '' || $passwordBaru === '') {
            throw new ApiException('Password lama dan password baru wajib diisi', 400);
        }
        if (strlen($passwordBaru) < 10) {
            throw new ApiException('Password baru minimal 10 karakter', 400);
        }

        if (!Hash::check($passwordLama, $user->password)) {
            throw new ApiException('Password lama salah', 400);
        }
        if (Hash::check($passwordBaru, $user->password)) {
            throw new ApiException('Password baru tidak boleh sama dengan password lama', 400);
        }

        $user->password = Hash::make($passwordBaru);
        $user->token = null;
        $user->token_expires_at = null;
        $user->save();

        ActivityLogger::log($request, $user->username, $user->nama, 'update', 'Mengganti password akun sendiri');

        return response()->json(['success' => true]);
    }
}
