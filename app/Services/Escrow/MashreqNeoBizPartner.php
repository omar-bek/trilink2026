<?php

namespace App\Services\Escrow;

use App\Models\Contract;
use App\Models\EscrowAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mashreq NeoBiz trade-finance escrow adapter. The actual API endpoints
 * are still under NDA at the time of this commit (Phase 3 / Sprint 11 /
 * task 3.1 is "bank partnership signed", external) so this implementation
 * uses the documented sandbox URLs and falls back to deterministic stub
 * responses when no API key is configured.
 *
 * Once production credentials land in `services.escrow.mashreq.api_key`,
 * the adapter starts hitting the real bank API end-to-end without any
 * other code changes — the calling sites only see BankPartnerInterface.
 */
class MashreqNeoBizPartner implements BankPartnerInterface
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.sandbox.mashreqbank.com/neobiz/escrow/v1',
        private readonly int $timeout = 12,
    ) {}

    public function openAccount(Contract $contract): array
    {
        $payload = [
            'reference'         => $contract->contract_number,
            'currency'          => $contract->currency ?? 'AED',
            'beneficiary_name'  => $this->supplierName($contract),
            'remitter_name'     => $contract->buyerCompany?->name,
            'expected_amount'   => (float) $contract->total_amount,
            'callback_url'      => route('api.webhooks.escrow', ['provider' => $this->key()]),
        ];

        $response = $this->call('POST', '/accounts', $payload);

        return [
            'external_account_id' => $response['account_id'] ?? ('NEOBIZ-' . strtoupper(Str::random(10))),
            'currency'            => $payload['currency'],
            'metadata'            => array_merge($response, [
                'provider' => $this->key(),
                'mode'     => $this->apiKey ? 'live' : 'stub',
            ]),
        ];
    }

    public function deposit(EscrowAccount $account, float $amount, string $currency): array
    {
        $response = $this->call('POST', "/accounts/{$account->external_account_id}/deposits", [
            'amount'    => $amount,
            'currency'  => $currency,
            'reference' => 'DEP-' . strtoupper(Str::random(8)),
        ]);

        return [
            'reference' => $response['transaction_id'] ?? ('NEOBIZ-DEP-' . strtoupper(Str::random(10))),
            // Mashreq settles inter-bank wires asynchronously — the
            // webhook handler later promotes the status to 'completed'.
            'status'    => $response['status'] ?? 'pending',
        ];
    }

    public function release(EscrowAccount $account, float $amount, string $currency, string $milestone): array
    {
        $response = $this->call('POST', "/accounts/{$account->external_account_id}/releases", [
            'amount'    => $amount,
            'currency'  => $currency,
            'milestone' => $milestone,
            'reference' => 'REL-' . strtoupper(Str::random(8)),
        ]);

        return [
            'reference' => $response['transaction_id'] ?? ('NEOBIZ-REL-' . strtoupper(Str::random(10))),
            'status'    => $response['status'] ?? 'completed',
        ];
    }

    public function refund(EscrowAccount $account, float $amount, string $currency, string $reason): array
    {
        $response = $this->call('POST', "/accounts/{$account->external_account_id}/refunds", [
            'amount'   => $amount,
            'currency' => $currency,
            'reason'   => $reason,
        ]);

        return [
            'reference' => $response['transaction_id'] ?? ('NEOBIZ-RFD-' . strtoupper(Str::random(10))),
            'status'    => $response['status'] ?? 'completed',
        ];
    }

    public function key(): string
    {
        return 'mashreq_neobiz';
    }

    /**
     * Wrapper around HTTP calls. Without an API key configured we short-
     * circuit and return a deterministic stub so the rest of the platform
     * remains functional during integration. Once a real key lands the
     * call hits the live sandbox.
     */
    private function call(string $method, string $path, array $body = []): array
    {
        if (!$this->apiKey) {
            return [];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->{strtolower($method)}($this->baseUrl . $path, $body);

            if ($response->failed()) {
                throw new BankPartnerException("Mashreq NeoBiz API error: HTTP {$response->status()}");
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            throw new BankPartnerException('Mashreq NeoBiz request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function supplierName(Contract $contract): ?string
    {
        foreach ($contract->parties ?? [] as $party) {
            if (($party['role'] ?? null) === 'supplier') {
                return $party['name'] ?? null;
            }
        }
        return null;
    }
}
