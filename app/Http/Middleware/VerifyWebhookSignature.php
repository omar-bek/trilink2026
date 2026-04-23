<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic HMAC webhook signature verifier.
 *
 * Usage: Route::post('/api/webhooks/{provider}', ...)->middleware('webhook.hmac:escrow');
 *
 * Expects the payload body to be signed with a shared secret using
 * HMAC-SHA256 and sent in the `X-Signature` header as `sha256=<hex>`.
 * Provider-specific schemes (Stripe's `Stripe-Signature`, PayPal's cert
 * chain) plug in by branching on the $provider argument.
 *
 * Secrets live in config('services.webhooks.{provider}.secret') so each
 * partner can rotate independently.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next, string $provider = 'default'): Response
    {
        $secret = (string) config("services.webhooks.$provider.secret");
        if ($secret === '') {
            // Fail open in local/dev (so we can test without real partners),
            // fail closed in production.
            if (app()->environment('production')) {
                Log::warning('webhook secret missing in prod', ['provider' => $provider]);
                abort(503, 'Webhook verifier not configured.');
            }

            return $next($request);
        }

        $signature = (string) $request->header('X-Signature', '');
        if ($signature === '') {
            abort(401, 'Missing X-Signature header.');
        }

        // Strip the `sha256=` prefix if present (Stripe / GitHub style).
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('webhook signature mismatch', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);
            abort(401, 'Signature does not match.');
        }

        return $next($request);
    }
}
