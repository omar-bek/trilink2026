<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $endpoints = WebhookEndpoint::with('company')
            ->withCount('deliveries')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total_endpoints' => WebhookEndpoint::count(),
            'active'          => WebhookEndpoint::where('is_active', true)->count(),
            'total_deliveries'=> WebhookDelivery::count(),
            'failed'          => WebhookDelivery::where('status', WebhookDelivery::STATUS_FAILED)->count(),
        ];

        return view('dashboard.admin.webhooks.index', compact('endpoints', 'stats'));
    }

    public function deliveries(Request $request, int $endpointId): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $endpoint = WebhookEndpoint::with('company')->findOrFail($endpointId);
        $deliveries = WebhookDelivery::where('webhook_endpoint_id', $endpointId)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('dashboard.admin.webhooks.deliveries', compact('endpoint', 'deliveries'));
    }
}
