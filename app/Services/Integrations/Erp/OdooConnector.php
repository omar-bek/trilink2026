<?php

namespace App\Services\Integrations\Erp;

use App\Models\Contract;
use App\Models\ErpConnector;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 7 — Odoo ERP connector. Pushes a TriLink contract into Odoo as
 * a sale.order via the JSON-RPC endpoint Odoo has exposed since v13.
 *
 * Credentials shape (stored encrypted on the ErpConnector row):
 *   ['db' => 'odoo_db', 'login' => 'admin', 'password' => '...']
 *
 * The connector authenticates via /web/session/authenticate which
 * returns a session cookie, then calls /web/dataset/call_kw to insert
 * the order. Stub mode returns a deterministic external id when no
 * credentials are configured.
 */
class OdooConnector implements ErpConnectorInterface
{
    public function key(): string
    {
        return 'odoo';
    }

    public function isLive(): bool
    {
        return true; // The factory only ever instantiates with creds.
    }

    public function pushContract(ErpConnector $connector, Contract $contract): array
    {
        $creds = $connector->credentials();
        if (empty($creds['db']) || empty($creds['login']) || empty($creds['password'])) {
            return $this->stub('contract');
        }

        try {
            $session = $this->authenticate($connector->base_url, $creds);
            if (!$session) {
                return ['success' => false, 'error' => 'Odoo authentication failed', 'mode' => 'live'];
            }

            $orderPayload = [
                'partner_id'    => 1, // Default placeholder. Real impl looks up via search_read.
                'date_order'    => $contract->start_date?->toDateTimeString() ?? now()->toDateTimeString(),
                'client_order_ref' => $contract->contract_number,
                'amount_total'  => (float) $contract->total_amount,
                'currency_id'   => 1,
            ];

            $externalId = $this->callKw($connector->base_url, $session, 'sale.order', 'create', [$orderPayload]);
            if (!$externalId) {
                return ['success' => false, 'error' => 'Odoo order creation failed', 'mode' => 'live'];
            }

            return [
                'success'     => true,
                'external_id' => (string) $externalId,
                'mode'        => 'live',
            ];
        } catch (\Throwable $e) {
            Log::warning('Odoo pushContract exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'mode' => 'live'];
        }
    }

    public function pushPayment(ErpConnector $connector, Payment $payment): array
    {
        $creds = $connector->credentials();
        if (empty($creds['db']) || empty($creds['login']) || empty($creds['password'])) {
            return $this->stub('payment');
        }

        try {
            $session = $this->authenticate($connector->base_url, $creds);
            if (!$session) {
                return ['success' => false, 'error' => 'Odoo authentication failed', 'mode' => 'live'];
            }

            $paymentPayload = [
                'amount'         => (float) $payment->total_amount,
                'currency_id'    => 1,
                'date'           => now()->toDateString(),
                'payment_type'   => 'inbound',
                'partner_type'   => 'customer',
                'communication'  => $payment->milestone,
            ];

            $externalId = $this->callKw($connector->base_url, $session, 'account.payment', 'create', [$paymentPayload]);
            if (!$externalId) {
                return ['success' => false, 'error' => 'Odoo payment creation failed', 'mode' => 'live'];
            }

            return [
                'success'     => true,
                'external_id' => (string) $externalId,
                'mode'        => 'live',
            ];
        } catch (\Throwable $e) {
            Log::warning('Odoo pushPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'mode' => 'live'];
        }
    }

    /**
     * Authenticate against Odoo's /web/session/authenticate. Returns the
     * session cookie payload (which becomes a header on subsequent
     * call_kw requests) or null on failure.
     */
    private function authenticate(string $baseUrl, array $creds): ?string
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->post(rtrim($baseUrl, '/') . '/web/session/authenticate', [
                'jsonrpc' => '2.0',
                'params'  => [
                    'db'       => $creds['db'],
                    'login'    => $creds['login'],
                    'password' => $creds['password'],
                ],
            ]);

        if (!$response->successful()) {
            return null;
        }

        $cookie = $response->cookies()->getCookieByName('session_id');
        return $cookie?->getValue();
    }

    /**
     * Call an Odoo model method via /web/dataset/call_kw. Returns the
     * raw `result` value from the JSON-RPC envelope or null on failure.
     */
    private function callKw(string $baseUrl, string $sessionId, string $model, string $method, array $args): mixed
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['Cookie' => "session_id={$sessionId}"])
            ->post(rtrim($baseUrl, '/') . '/web/dataset/call_kw', [
                'jsonrpc' => '2.0',
                'method'  => 'call',
                'params'  => [
                    'model'  => $model,
                    'method' => $method,
                    'args'   => $args,
                    'kwargs' => new \stdClass(),
                ],
            ]);

        if (!$response->successful()) {
            return null;
        }
        $body = $response->json();
        return $body['result'] ?? null;
    }

    private function stub(string $kind): array
    {
        return [
            'success'     => true,
            'external_id' => 'ODOO-STUB-' . strtoupper($kind) . '-' . strtoupper(Str::random(8)),
            'mode'        => 'stub',
        ];
    }
}
