<?php

use App\Http\Middleware\CheckGymAccess;
use App\Http\Middleware\MyGuest;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            SetLocale::class
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'my_guest' => MyGuest::class,
            'setLocale' => \App\Http\Middleware\SetLocale::class,
            'check.gym' => CheckGymAccess::class,

        ]);
        $middleware->validateCsrfTokens(except: [
            'http://127.0.0.1:8000/*',
            'http://localhost:8000/*',
            env('APP_URL') . '/*',

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
