<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\EnsureSingleWebSession;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TokenAuthMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Endpoint "gaya API lama" -- path .php persis nama file PHP lama, plus
// /uploads/*. SEMUA endpoint ini (dikonsumsi app Flutter) selalu dibalas
// JSON {"error": "..."}, meniru bentuk respons proyek PHP lama persis.
// Halaman web (Livewire, dikonsumsi browser manusia) di luar pola ini
// tetap dapat penanganan error HTML/redirect standar Laravel.
$isLegacyApiRequest = fn (Request $request): bool => str_ends_with($request->path(), '.php')
    || str_starts_with($request->path(), 'uploads/');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pakai CorsMiddleware kustom sendiri (meniru backend/config/cors.php
        // lama persis) -- bukan HandleCors bawaan Laravel. Didaftarkan
        // sebagai middleware GLOBAL (bukan cuma grup 'api') supaya tetap
        // jalan untuk request OPTIONS preflight -- Laravel membuat route
        // OPTIONS implisit di level router (AbstractRouteCollection) yang
        // tidak ikut middleware grup route mana pun.
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->prepend(CorsMiddleware::class);

        $middleware->alias([
            'auth.token' => TokenAuthMiddleware::class,
            'auth.admin' => AdminOnlyMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // Halaman web (Livewire) pakai session guard biasa -- belum login
        // diarahkan ke /login, bukan dilempar 401 JSON (itu khusus jalur API
        // legacy .php di atas).
        $middleware->redirectGuestsTo('/login');

        // Satu akun cuma satu sesi web aktif -- lihat
        // App\Http\Middleware\EnsureSingleWebSession. Ditambahkan di grup
        // 'web' (bukan cuma alias per-route) supaya berlaku otomatis di
        // SEMUA halaman terautentikasi TERMASUK polling AJAX Livewire
        // (POST /livewire/update), tanpa perlu menambah middleware ini satu
        // per satu di tiap route.
        $middleware->web(append: [EnsureSingleWebSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isLegacyApiRequest): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $isLegacyApiRequest($request) || $request->expectsJson(),
        );

        $exceptions->render(function (ApiException $e, Request $request) use ($isLegacyApiRequest) {
            if (!$isLegacyApiRequest($request)) {
                return null;
            }

            return response()->json(['error' => $e->getMessage()], $e->status());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isLegacyApiRequest) {
            if (!$isLegacyApiRequest($request)) {
                return null;
            }

            return response()->json(['error' => 'Belum login atau sesi sudah berakhir, silakan login ulang'], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isLegacyApiRequest) {
            if (!$isLegacyApiRequest($request)) {
                return null;
            }

            return response()->json(['error' => collect($e->errors())->flatten()->first() ?? $e->getMessage()], 400);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isLegacyApiRequest) {
            if (!$isLegacyApiRequest($request)) {
                return null;
            }

            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isLegacyApiRequest) {
            if (!$isLegacyApiRequest($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $default = match ($status) {
                404 => 'Endpoint tidak ditemukan',
                405 => 'Method not allowed',
                default => 'Terjadi kesalahan',
            };
            $message = $e->getMessage() !== '' ? $e->getMessage() : $default;

            return response()->json(['error' => $message], $status);
        });
    })->create();
