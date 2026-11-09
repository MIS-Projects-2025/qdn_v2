<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The Authify SSO cookie is set by Authify / AuthMiddleware as a
        // plain (unencrypted) value, so Laravel must not try to decrypt it.
        $middleware->encryptCookies(except: [
            env('SSO_COOKIE_NAME', 'sso_token'),
        ]);

        // The close-beacon is fired by navigator.sendBeacon on tab close and
        // cannot carry a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'session/close-beacon',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
