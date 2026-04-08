<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Logistics\CarbonFootprintService;
use App\Services\Logistics\CustomsDocumentService;
use App\Services\Logistics\DubaiTradeAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 6 — single controller for the new logistics surfaces:
 *
 *   - Carbon footprint preview (JSON)
 *   - Customs documents (commercial invoice + packing list as PDFs)
 *   - Dubai Trade declaration submission
 *
 * Authorization rules: every action requires the user to be a party of
 * the shipment's underlying contract (buyer or supplier side). The web
 * permission is `shipment.view` — same gate as the existing shipment
 * detail page.
 */
class LogisticsController extends Controller
{
    public function __construct(
        private readonly CarbonFootprintService $footprint,
        private readonly CustomsDocumentService $customs,
        private readonly DubaiTradeAdapter $dubaiTrade,
    ) {}

    public function carbonFootprint(int $shipmentId): JsonResponse
    {
        $shipment = $this->authorisedShipment($shipmentId);
        $result   = $this->footprint->forShipment($shipment);

        return response()->json($result ?? ['error' => 'insufficient_data']);
    }

    public function commercialInvoice(int $shipmentId): Response
    {
        $shipment = $this->authorisedShipment($shipmentId);
        $payload  = $this->customs->commercialInvoice($shipment);
        return $this->customs->renderPdf($payload);
    }

    public function packingList(int $shipmentId): Response
    {
        $shipment = $this->authorisedShipment($shipmentId);
        $payload  = $this->customs->packingList($shipment);
        return $this->customs->renderPdf($payload);
    }

    /**
     * Submit a Dubai Trade declaration for the shipment. Stores the
     * resulting reference under `customs_documents.dubai_trade` so the
     * shipment show page can render it.
     */
    public function submitToDubaiTrade(int $shipmentId): RedirectResponse
    {
        $shipment = $this->authorisedShipment($shipmentId);
        $result   = $this->dubaiTrade->submitDeclaration($shipment);

        if (!($result['success'] ?? false)) {
            return back()->withErrors(['logistics' => $result['error'] ?? 'Dubai Trade submission failed']);
        }

        $existing = (array) ($shipment->customs_documents ?? []);
        $existing['dubai_trade'] = $result;
        $shipment->update(['customs_documents' => $existing]);

        return back()->with('status', __('logistics.dubai_trade_submitted'));
    }

    /**
     * Resolve a shipment by id and assert that the current user's company
     * is a party of the underlying contract. Reused by every method on
     * this controller because they share the same auth model.
     */
    private function authorisedShipment(int $shipmentId): Shipment
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('shipment.view'), 403);

        $shipment = Shipment::with(['contract.buyerCompany', 'company'])->findOrFail($shipmentId);

        $partyIds = collect($shipment->contract?->parties ?? [])
            ->pluck('company_id')
            ->push($shipment->contract?->buyer_company_id)
            ->push($shipment->company_id)
            ->filter()
            ->all();

        abort_unless(in_array($user->company_id, $partyIds, true), 403);

        return $shipment;
    }
}
