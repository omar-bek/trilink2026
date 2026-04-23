<?php

use App\Enums\CompanyStatus;
use App\Http\Middleware\AuditMiddleware;
use App\Http\Middleware\CheckOwnership;
use App\Http\Middleware\EnforceCompanySecurityPolicy;
use App\Http\Middleware\EnsureCompanyApproved;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            'jwt.auth' => JwtAuthenticate::class,
            'audit' => AuditMiddleware::class,
            'ownership' => CheckOwnership::class,
            'setlocale' => SetLocale::class,
            'web.role' => EnsureUserHasRole::class,
            'company.approved' => EnsureCompanyApproved::class,
            'company.security' => EnforceCompanySecurityPolicy::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // HMAC verifier for external partner webhooks. Usage:
            //   Route::post('/api/webhooks/escrow', ...)->middleware('webhook.hmac:escrow');
            'webhook.hmac' => VerifyWebhookSignature::class,
        ]);

        // Request-ID threading. Prepended BEFORE everything else so every
        // log line (including validation failures and the audit middleware)
        // carries the same correlation ID.
        $middleware->prepend(RequestId::class);

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        // When the `guest` middleware blocks an already-authenticated user,
        // pick a sensible destination instead of the framework default of
        // `/home` (which doesn't exist here). Pending company managers go to
        // the registration-success holding page; everyone else lands on
        // their normal post-login URL.
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            $company = $user?->company;

            if ($company && $company->status !== CompanyStatus::ACTIVE) {
                return route('register.success');
            }

            return match ($user?->role?->value) {
                'admin' => route('admin.index'),
                'government' => route('gov.index'),
                default => route('dashboard'),
            };
        });

        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            ThrottleRequests::class.':api',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Forward every reported exception to Sentry. The SDK auto-skips
        // when SENTRY_LARAVEL_DSN is empty, so local and CI runs are
        // unaffected — only production with a real DSN sends events.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Render a friendly 403 page for web requests instead of the default
        // Symfony error template — keeps the role-denial UX consistent.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null; // Let the JSON renderer handle it.
            }

            if ($e->getStatusCode() === 403) {
                return response()->view('errors.403', ['message' => $e->getMessage()], 403);
            }

            return null;
        });
    })->create();
