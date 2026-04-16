<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ErpConnector;
use App\Models\WebhookEndpoint;
use App\Services\Integrations\Erp\ErpConnectorFactory;
use App\Services\Integrations\WebhookDispatcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Phase 7 — manager-facing dashboard for the new integration surfaces:
 *
 *   - Webhook endpoints (HTTPS event listeners)
 *   - ERP connectors (Odoo, NetSuite, etc.)
 *
 * Both are tenant-scoped (one company can never see another's endpoints
 * or connectors). All actions are gated by `integrations.manage`.
 */
class IntegrationsController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcherService $dispatcher,
        private readonly ErpConnectorFactory $erpFactory,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        $endpoints = WebhookEndpoint::query()
            ->where('company_id', $user->company_id)
            ->withCount(['deliveries as success_count' => fn ($q) => $q->where('status', 'success')])
            ->withCount(['deliveries as failure_count' => fn ($q) => $q->where('status', 'failed')])
            ->latest()
            ->get();

        $connectors = ErpConnector::query()
            ->where('company_id', $user->company_id)
            ->latest()
            ->get();

        return view('dashboard.integrations.index', compact('endpoints', 'connectors'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Webhook endpoints
    // ─────────────────────────────────────────────────────────────────────

    public function storeEndpoint(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'starts_with:https://', 'max:500'],
            'events' => ['nullable', 'string', 'max:500'],
        ]);

        $endpoint = WebhookEndpoint::create([
            'company_id' => $user->company_id,
            'label' => $data['label'],
            'url' => $data['url'],
            'events' => $data['events'] ?? '',
            'secret' => 'whsec_'.Str::random(48),
            'is_active' => true,
        ]);

        // Show the secret once on the next page so the manager can copy
        // it. After this we only have it in DB; the UI never re-displays.
        return redirect()
            ->route('dashboard.integrations.index')
            ->with('plain_secret', $endpoint->secret)
            ->with('plain_secret_id', $endpoint->id)
            ->with('status', __('integrations.webhook_created'));
    }

    public function destroyEndpoint(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        WebhookEndpoint::where('company_id', $user->company_id)->findOrFail($id)->delete();

        return back()->with('status', __('integrations.webhook_deleted'));
    }

    /**
     * Send a synthetic test event to the endpoint so the manager can
     * verify their receiver is wired correctly. Records the attempt in
     * webhook_deliveries just like a real event.
     */
    public function testEndpoint(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        $endpoint = WebhookEndpoint::where('company_id', $user->company_id)->findOrFail($id);
        $delivery = $this->dispatcher->deliver($endpoint, 'webhook.test', [
            'message' => 'TriLink test event',
            'timestamp' => now()->toIso8601String(),
        ]);

        return back()->with('status', __('integrations.webhook_test_sent', ['status' => $delivery->status]));
    }

    // ─────────────────────────────────────────────────────────────────────
    // ERP connectors
    // ─────────────────────────────────────────────────────────────────────

    public function storeConnector(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:odoo,netsuite,sap,quickbooks,custom'],
            'label' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url', 'max:500'],
            'credentials' => ['nullable', 'array'],
        ]);

        $connector = new ErpConnector([
            'company_id' => $user->company_id,
            'type' => $data['type'],
            'label' => $data['label'],
            'base_url' => $data['base_url'],
            'is_active' => true,
        ]);
        $connector->setCredentials($data['credentials'] ?? []);
        $connector->save();

        return redirect()
            ->route('dashboard.integrations.index')
            ->with('status', __('integrations.connector_created'));
    }

    public function destroyConnector(int $id): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        ErpConnector::where('company_id', $user->company_id)->findOrFail($id)->delete();

        return back()->with('status', __('integrations.connector_deleted'));
    }

    /**
     * Push a single contract to the connector and stamp the response on
     * the contract for the customer to verify. Used for one-off testing
     * before flipping the connector into auto-sync mode (which lives in
     * a follow-up sprint).
     */
    public function pushContract(Request $request, int $connectorId): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('integrations.manage'), 403);

        $request->validate(['contract_id' => ['required', 'integer', 'exists:contracts,id']]);

        $connector = ErpConnector::where('company_id', $user->company_id)->findOrFail($connectorId);
        $contract = Contract::where('buyer_company_id', $user->company_id)->findOrFail($request->input('contract_id'));

        $adapter = $this->erpFactory->for($connector);
        $result = $adapter->pushContract($connector, $contract);

        $connector->update(['last_sync_at' => now()]);

        return back()->with('status', __('integrations.contract_pushed', [
            'mode' => $result['mode'] ?? 'unknown',
            'id' => $result['external_id'] ?? '—',
        ]));
    }
}
