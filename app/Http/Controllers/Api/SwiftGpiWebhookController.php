<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\SwiftGpiTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint the correspondent bank calls with every SWIFT gpi status
 * update for our outgoing wires. The signing secret is shared with the
 * bank at onboarding and rotated via the admin settings page; once the
 * HMAC check passes we hand the payload to the tracker.
 */
class SwiftGpiWebhookController extends Controller
{
    public function __construct(private readonly SwiftGpiTracker $tracker) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.swift_gpi.webhook_secret');
        $signature = $request->header('X-Gpi-Signature', '');
        $expected = $secret ? hash_hmac('sha256', $request->getContent(), $secret) : null;

        if ($secret && ! hash_equals((string) $expected, (string) $signature)) {
            Log::warning('swift_gpi_webhook_signature_mismatch');

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = $request->json()->all();
        try {
            $event = $this->tracker->ingest($payload);

            return response()->json(['id' => $event->id, 'status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('swift_gpi_webhook_failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
