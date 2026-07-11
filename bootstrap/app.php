<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        $trustedProxies = $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));

        // Trust localhost by default for local servers and Cloudflare Tunnel clients.
        $middleware->trustProxies(at: $trustedProxies ?: ['127.0.0.1', '::1']);

        // Exclude PayMongo webhook from CSRF protection
        $middleware->validateCsrfTokens(except: [
            '/api/webhooks/paymongo',
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin*')
            ? url('/admin/login')
            : route('guest.account.login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
