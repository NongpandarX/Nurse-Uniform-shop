<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register route middleware aliases (§4 Middleware Chain)
        $middleware->alias([
            'active.user'   => \App\Http\Middleware\EnsureActiveUser::class,
            'role'          => \App\Http\Middleware\EnsureRole::class,
            'branch.scope'  => \App\Http\Middleware\BranchScope::class,
            'device.bound'  => \App\Http\Middleware\EnsureDeviceBound::class,
            'shop.open'     => \App\Http\Middleware\EnsureShopOpen::class,
            'geofence'      => \App\Http\Middleware\EnsureWithinGeofence::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
