<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Buyer-facing shipping quote tool. Lets the buyer compare rates from
 * every configured carrier (Aramex, DHL, FedEx, UPS, Fetchr) before
 * picking one. The chosen carrier + service is stored on the Shipment
 * (or used to create one if none exists yet).
 *
 * The "Sync tracking" action below pulls the latest events from whichever
 * carrier owns the shipment and mirrors them into tracking_events so the
 * platform UI doesn't depend on the carrier portal.
 */
class ShippingQuoteController extends Controller
{
    public function __construct(private readonly ShippingService $service) {}

    public function form(): View
    {
        return view('dashboard.shipping.quotes', ['rates' => null, 'request' => null]);
    }

    public function quote(Request $request): View
    {
        $data = $request->validate([
            'origin_city' => ['required', 'string', 'max:100'],
            'origin_country' => ['required', 'string', 'size:2'],
            'destination_city' => ['required', 'string', 'max:100'],
            'destination_country' => ['required', 'string', 'size:2'],
            'weight_kg' => ['required', 'numeric', 'min:0.1'],
            'parcels' => ['required', 'integer', 'min:1'],
        ]);

        $payload = [
            'origin' => ['city' => $data['origin_city'], 'country' => strtoupper($data['origin_country'])],
            'destination' => ['city' => $data['destination_city'], 'country' => strtoupper($data['destination_country'])],
            'weight_kg' => (float) $data['weight_kg'],
            'parcels' => (int) $data['parcels'],
        ];

        $rates = $this->service->quoteAll($payload);

        return view('dashboard.shipping.quotes', [
            'rates' => $rates,
            'request' => $payload,
        ]);
    }

    /**
     * Sync the tracking timeline of an existing shipment from its carrier.
     * The user passes the carrier code; we update tracking_events and
     * redirect back to the shipment page so the new events appear inline.
     */
    public function syncTracking(Request $request, int $shipmentId): RedirectResponse
    {
        $shipment = Shipment::findOrFail($shipmentId);
        $user = $request->user();

        // Tenant guard: only the buyer or supplier on the underlying contract
        // can sync tracking. Avoids letting random users hit the carrier API.
        $allowed = $user
            && $user->company_id
            && ($user->company_id === $shipment->company_id
                || $user->company_id === $shipment->logistics_company_id);
        abort_unless($allowed, 403);

        $carrier = $request->validate([
            'carrier' => ['required', 'string', 'in:aramex,dhl,fedex,ups,fetchr'],
        ])['carrier'];

        $count = $this->service->syncTracking($shipment, $carrier);

        return back()->with('status', __('shipping.tracking_synced', ['count' => $count]));
    }
}
