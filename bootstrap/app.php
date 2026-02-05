<?php

use App\Services\HttpResponseService;
use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

    $exceptions->renderable(function (Throwable $e, $request) {

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return null;
        }

        // Only handle server errors here
        $randomErrorCode = 'C'.rand(111111111, 99999999);
        logger()->error('Server Error', [
            'Code'    => $randomErrorCode,
            'Message' => $e->getMessage(),
            'File'    => $e->getFile(),
            'Line'    => $e->getLine(),
            'Trace'   => $e->getTraceAsString(),
        ]);

        return HttpResponseService::error(
            "An error {$randomErrorCode} occurred.",
            [],
            'fatal',
            500
        );
    });
})

    ->create();
