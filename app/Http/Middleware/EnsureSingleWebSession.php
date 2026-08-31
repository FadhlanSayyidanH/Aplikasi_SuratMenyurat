<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tegakkan "satu akun cuma satu sesi web aktif" -- App\Livewire\Auth\Login
 * menulis session ID sesi yang baru login ke users.web_session_id (kolom
 * ini cuma menyimpan SATU nilai, sama seperti kolom `token` API). Kalau
 * sesi yang sedang jalan (session()->getId()) TIDAK cocok lagi dengan nilai
 * itu, berarti akun ini sudah login ulang dari perangkat/browser lain --
 * sesi ini dianggap basi, langsung dipaksa logout.
 *
 * Global di grup middleware 'web' (lihat bootstrap/app.php) supaya berlaku
 * di SEMUA request terautentikasi, termasuk polling AJAX Livewire
 * (POST /livewire/update) -- bukan cuma saat navigasi halaman penuh.
 */
class EnsureSingleWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->web_session_id !== null && $user->web_session_id !== $request->session()->getId()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Akun ini baru saja login di perangkat/browser lain.'], 401);
            }

            return redirect()->route('login')
                ->with('status', 'Anda sudah keluar -- akun ini baru saja login di perangkat/browser lain.');
        }

        return $next($request);
    }
}
