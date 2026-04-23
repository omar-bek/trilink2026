<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\SwiftGpiStatusEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Persists the SWIFT gpi Tracker updates we receive for our outgoing
 * wires. The incoming webhook handler (routes/api.php → SwiftGpiWebhook)
 * calls `ingest()` for every pacs.028 status update; the UI then calls
 * `latestFor($uetr)` to render the timeline on a payment's detail page.
 *
 * Three status codes matter for the UX:
 *   ACSP — accepted, in progress (grey pill)
 *   ACSC — settlement completed (blue pill)
 *   ACCC — credited to beneficiary (green pill)
 * Anything starting with R is a rejection and is rendered in red.
 */
class SwiftGpiTracker
{
    /**
     * @param  array<string,mixed>  $payload  Normalised gpi event (we expect
     *                                        whoever fronts the webhook to
     *                                        have validated the signature
     *                                        and translated SWIFT field
     *                                        names into snake_case keys).
     */
    public function ingest(array $payload): SwiftGpiStatusEvent
    {
        $uetr = $payload['uetr'] ?? '';
        if (! $this->isValidUetr($uetr)) {
            throw new \InvalidArgumentException('Invalid UETR');
        }

        $payment = Payment::where('uetr', $uetr)->first();

        return SwiftGpiStatusEvent::create([
            'payment_id' => $payment?->id,
            'uetr' => $uetr,
            'status' => strtoupper($payload['status'] ?? 'ACSP'),
            'status_reason' => $payload['status_reason'] ?? null,
            'from_bic' => $payload['from_bic'] ?? '',
            'to_bic' => $payload['to_bic'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'charges_amount' => $payload['charges_amount'] ?? null,
            'charges_currency' => $payload['charges_currency'] ?? null,
            'fx_rate' => $payload['fx_rate'] ?? null,
            'originator_time' => isset($payload['originator_time'])
                ? Carbon::parse($payload['originator_time'])
                : now(),
            'raw_payload' => $payload,
        ]);
    }

    /**
     * Generate a fresh UETR when originating a new SWIFT payment. The
     * format is a lowercase UUID-v4; SWIFT mandates that exact shape
     * on MT103 field 121 / pacs.008 UETR element.
     */
    public function generateUetr(): string
    {
        return strtolower((string) Str::uuid());
    }

    /**
     * @return iterable<SwiftGpiStatusEvent>
     */
    public function timelineFor(string $uetr): iterable
    {
        return SwiftGpiStatusEvent::where('uetr', $uetr)
            ->orderBy('originator_time')
            ->get();
    }

    public function latestFor(string $uetr): ?SwiftGpiStatusEvent
    {
        return SwiftGpiStatusEvent::where('uetr', $uetr)
            ->latest('originator_time')
            ->first();
    }

    private function isValidUetr(string $uetr): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uetr);
    }
}
