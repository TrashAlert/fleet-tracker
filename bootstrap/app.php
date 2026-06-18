<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
        'role'   => \App\Http\Middleware\RoleMiddleware::class,
        'active' => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
        $middleware->redirectGuestsTo('/login');

        // Behind Cloudflare Tunnel (cloudflared -> 127.0.0.1:8000): trust the
        // forwarded proto/host headers so Laravel knows the request is HTTPS
        // and generates https://fleettracker.com URLs (tracking links, assets).
        // Safe to trust all here because only cloudflared can reach the origin.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
