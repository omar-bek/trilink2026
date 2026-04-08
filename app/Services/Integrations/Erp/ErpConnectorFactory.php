<?php

namespace App\Services\Integrations\Erp;

use App\Models\ErpConnector;

/**
 * Phase 7 — resolves the right adapter class for an ErpConnector row.
 * Today only Odoo is implemented; NetSuite + SAP + QuickBooks land in
 * follow-up sprints. Unknown types fall through to the generic stub
 * adapter (CustomConnector) so the dashboard never crashes on a row
 * for an ERP we haven't built an adapter for yet.
 */
class ErpConnectorFactory
{
    public function for(ErpConnector $connector): ErpConnectorInterface
    {
        return match ($connector->type) {
            ErpConnector::TYPE_ODOO => new OdooConnector(),
            // Other ERPs ship as stubs for now — they record the call
            // but don't actually hit a remote API. Replace with real
            // adapters when the partnerships land.
            default => new CustomConnector($connector->type),
        };
    }
}
