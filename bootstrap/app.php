<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBrowserCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'no-cache' => PreventBrowserCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            return redirect()->route('login')->with('status', 'Your session has expired. Please sign in again.');
        });
    })->create();
