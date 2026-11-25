<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Production on the webspace has no process environment. Laravel would otherwise
// read `.env`, which is not deployed. Local Docker and PHPUnit set APP_ENV first.
$preset = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null;
if (($preset === null || $preset === 'production') && is_file(dirname(__DIR__).'/.env.production')) {
    $app->loadEnvironmentFrom('.env.production');
}

return $app;
