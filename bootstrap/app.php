<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'active.user' => EnsureUserIsActive::class,
            'device' => AuthenticateDevice::class,
        ]);

        $middleware->validateCsrfTokens(except: ['api/*']);
        // EnsureUserIsActive TIDAK diappend ke global web stack karena sudah
        // dipakai sebagai alias 'active.user' di route group authenticated.
        // Menambahkannya di sini juga akan membuatnya berjalan dua kali.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
