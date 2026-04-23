<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches a unique X-Request-ID to every incoming request + emits it on
 * the response. Threaded into log context so every structured log line
 * from this request carries the same ID — dramatically speeding up
 * triage when the same user hits multiple services via the request chain.
 *
 * If the caller already sends X-Request-ID (e.g. an API gateway upstream),
 * we honour it. Otherwise we generate a ULID-style token.
 */
class RequestId
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $id = trim((string) $request->header(self::HEADER));
        if ($id === '' || strlen($id) > 64) {
            $id = (string) Str::ulid();
        }

        // Stash on the request so controllers / listeners can read it.
        $request->headers->set(self::HEADER, $id);
        $request->attributes->set('request_id', $id);

        // Thread into every log call downstream.
        Log::withContext(['request_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
