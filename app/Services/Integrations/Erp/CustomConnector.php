<?php

namespace App\Services\Integrations\Erp;

use App\Models\Contract;
use App\Models\ErpConnector;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Phase 7 — generic stub adapter used for ERPs we don't have a real
 * implementation for yet (NetSuite, SAP, QuickBooks, custom). Returns
 * deterministic external ids so the dashboard renders something
 * meaningful and the customer can verify the connector flow before
 * we build the live adapter.
 */
class CustomConnector implements ErpConnectorInterface
{
    public function __construct(private readonly string $type) {}

    public function key(): string
    {
        return $this->type;
    }

    public function isLive(): bool
    {
        return false;
    }

    public function pushContract(ErpConnector $connector, Contract $contract): array
    {
        return [
            'success' => true,
            'external_id' => strtoupper($this->type).'-CONTRACT-'.strtoupper(Str::random(10)),
            'mode' => 'stub',
        ];
    }

    public function pushPayment(ErpConnector $connector, Payment $payment): array
    {
        return [
            'success' => true,
            'external_id' => strtoupper($this->type).'-PAYMENT-'.strtoupper(Str::random(10)),
            'mode' => 'stub',
        ];
    }
}
