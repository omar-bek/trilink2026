<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EInvoiceSubmission;
use App\Services\EInvoice\EInvoiceDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 5 (UAE Compliance Roadmap) — async ASP webhook receiver.
 *
 * Most ASPs notify the platform of FTA clearance asynchronously: the
 * dispatcher's submit() call returns "submitted", and minutes (or
 * hours) later the ASP POSTs back with the FTA clearance id (or a
 * rejection). This controller is the receiver.
 *
 * Security model:
 *
 *   - The shared secret in config('einvoice.webhook_secret') is the
 *     gate. The ASP signs the request body with HMAC-SHA256 and puts
 *     the hex digest in the X-EInvoice-Signature header. We recompute
 *     the same HMAC and constant-time compare. Mismatch → 400.
 *
 *   - When the secret is unset (the deployment hasn't configured
 *     e-invoicing yet), every request is rejected with 503. Without
 *     this an attacker could flip submissions to "accepted" by
 *     guessing the URL.
 *
 *   - The {provider} URL parameter scopes the webhook so a leaked
 *     secret on one ASP can't be replayed against another.
 */
class EInvoiceWebhookController extends Controller
{
    public function __construct(
        private readonly EInvoiceDispatcher $dispatcher,
    ) {
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('einvoice.webhook_secret', '');
        if ($secret === '') {
            return response()->json([
                'error' => 'E-invoice webhook is not configured on this deployment.',
            ], 503);
        }

        $signature = (string) $request->header('X-EInvoice-Signature', '');
        $body      = $request->getContent();
        $expected  = hash_hmac('sha256', $body, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = $request->json()->all();

        $submissionId = $payload['submission_id'] ?? null;
        if (!$submissionId) {
            return response()->json(['error' => 'Missing submission_id'], 422);
        }

        // Look up the submission by the ASP-side id (what we stamped
        // when we sent the original request) — never trust an inbound
        // primary-key id.
        $submission = EInvoiceSubmission::where('asp_provider', $provider)
            ->where('asp_submission_id', $submissionId)
            ->first();

        if (!$submission) {
            return response()->json(['error' => 'Unknown submission'], 404);
        }

        $this->dispatcher->ackFromWebhook($submission, $payload);

        return response()->json(['received' => true]);
    }
}
