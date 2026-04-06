<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthenticate::class,
            'audit' => \App\Http\Middleware\AuditMiddleware::class,
            'ownership' => \App\Http\Middleware\CheckOwnership::class,
            'setlocale' => \App\Http\Middleware\SetLocale::class,
            'web.role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'company.approved' => \App\Http\Middleware\EnsureCompanyApproved::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Render a friendly 403 page for web requests instead of the default
        // Symfony error template — keeps the role-denial UX consistent.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // Let the JSON renderer handle it.
            }

            if ($e->getStatusCode() === 403) {
                return response()->view('errors.403', ['message' => $e->getMessage()], 403);
            }

            return null;
        });
    })->create();
