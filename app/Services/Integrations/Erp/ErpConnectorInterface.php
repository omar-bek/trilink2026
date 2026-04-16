<?php

namespace App\Services\Integrations\Erp;

use App\Models\Contract;
use App\Models\ErpConnector;
use App\Models\Payment;

/**
 * Phase 7 — contract every ERP connector adapter must implement. Adding
 * a new ERP (NetSuite, SAP, QuickBooks) means writing one class that
 * implements this interface and registering it in ErpConnectorFactory.
 *
 * Today we focus on outbound sync (TriLink → ERP) for contracts and
 * payments. Inbound sync (ERP → TriLink) lands in a follow-up sprint.
 */
interface ErpConnectorInterface
{
    /**
     * Stable identifier — 'odoo', 'netsuite', etc. Matches
     * `erp_connectors.type` so the factory can resolve adapters.
     */
    public function key(): string;

    /**
     * True when the connector has the credentials it needs for live
     * API calls. Stub mode otherwise.
     */
    public function isLive(): bool;

    /**
     * Push a contract into the ERP as a sales order (or whatever the
     * ERP's equivalent is). Returns:
     *
     *   ['success' => bool, 'external_id' => '...', 'mode' => 'live'|'stub']
     */
    public function pushContract(ErpConnector $connector, Contract $contract): array;

    /**
     * Push a payment milestone into the ERP. Used by listeners hooked
     * into PaymentProcessed.
     */
    public function pushPayment(ErpConnector $connector, Payment $payment): array;
}
