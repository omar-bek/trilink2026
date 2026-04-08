<?php

namespace App\Services\Logistics;

use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 6 — auto-generate the standard customs document set for a
 * shipment. Two documents are produced from the contract + shipment
 * data we already have:
 *
 *   1. Commercial invoice — line items, HS codes, currency, signatory
 *   2. Packing list — gross/net weight, dimensions, marks, package count
 *
 * Both render via DomPDF using the existing template pattern in
 * resources/views/dashboard/shipments/pdf/. Customers download the PDFs
 * directly from the shipment show page.
 *
 * The service is intentionally template-free at the data layer — it
 * normalises the shipment + contract into a flat array that the Blade
 * template binds to. Adding new doc types means adding one method here
 * + one Blade template.
 */
class CustomsDocumentService
{
    /**
     * Build a structured payload for the commercial invoice template.
     * Used directly by the controller for both PDF rendering and the
     * "preview" JSON API.
     */
    public function commercialInvoice(Shipment $shipment): array
    {
        $shipment->loadMissing(['contract.buyerCompany']);
        $contract = $shipment->contract;

        return [
            'document_type'  => 'commercial_invoice',
            'document_number'=> 'CI-' . $shipment->tracking_number,
            'issue_date'     => now()->toDateString(),
            'shipper'        => $this->shipperBlock($shipment),
            'consignee'      => $this->consigneeBlock($shipment),
            'shipment'       => [
                'tracking_number' => $shipment->tracking_number,
                'origin'          => $shipment->origin,
                'destination'     => $shipment->destination,
                'estimated'       => $shipment->estimated_delivery?->toDateString(),
                'incoterm'        => 'FOB',
            ],
            'lines'          => $this->buildLines($contract),
            'totals'         => [
                'subtotal'  => (float) ($contract?->amounts['subtotal'] ?? 0),
                'tax'       => (float) ($contract?->amounts['tax'] ?? 0),
                'total'     => (float) ($contract?->total_amount ?? 0),
                'currency'  => $contract?->currency ?? 'AED',
            ],
            'notes'          => 'Goods of preferential origin, customs clearance value as declared above.',
        ];
    }

    public function packingList(Shipment $shipment): array
    {
        $shipment->loadMissing(['contract.buyerCompany']);
        $contract = $shipment->contract;

        $lines    = $this->buildLines($contract);
        $totalQty = collect($lines)->sum('quantity');
        // Default 5kg per unit until we add a real weight column.
        $estimatedWeight = max(1, $totalQty) * 5;

        return [
            'document_type'   => 'packing_list',
            'document_number' => 'PL-' . $shipment->tracking_number,
            'issue_date'      => now()->toDateString(),
            'shipper'         => $this->shipperBlock($shipment),
            'consignee'       => $this->consigneeBlock($shipment),
            'shipment'        => [
                'tracking_number' => $shipment->tracking_number,
                'origin'          => $shipment->origin,
                'destination'     => $shipment->destination,
            ],
            'packages'        => [
                [
                    'package_no'  => 1,
                    'marks'       => 'TRILINK',
                    'description' => 'Mixed goods',
                    'quantity'    => $totalQty,
                    'gross_kg'    => $estimatedWeight + 5,
                    'net_kg'      => $estimatedWeight,
                    'dimensions'  => '120 x 80 x 100 cm',
                ],
            ],
            'totals'          => [
                'package_count' => 1,
                'gross_kg'      => $estimatedWeight + 5,
                'net_kg'        => $estimatedWeight,
            ],
        ];
    }

    /**
     * Render either document as a downloadable PDF using the shared Blade
     * template. The same template handles both doc types via a `type`
     * field in the data payload.
     */
    public function renderPdf(array $data): Response
    {
        $pdf = Pdf::loadView('dashboard.shipments.pdf.customs', ['doc' => $data]);
        return $pdf->download($data['document_number'] . '.pdf');
    }

    private function shipperBlock(Shipment $shipment): array
    {
        // The shipper is the company that opened the shipment row — that's
        // the supplier in our model.
        $supplier = $shipment->company;
        return [
            'name'    => $supplier?->name,
            'country' => $supplier?->country,
            'address' => $supplier?->address,
            'tax_no'  => $supplier?->tax_number,
        ];
    }

    private function consigneeBlock(Shipment $shipment): array
    {
        $buyer = $shipment->contract?->buyerCompany;
        return [
            'name'    => $buyer?->name,
            'country' => $buyer?->country,
            'address' => $buyer?->address,
            'tax_no'  => $buyer?->tax_number,
        ];
    }

    /**
     * Normalise the contract's `amounts.lines` array into a flat shape
     * the templates can render. For older contracts that don't have a
     * lines array we synthesize a single line from the total amount.
     */
    private function buildLines(?\App\Models\Contract $contract): array
    {
        if (!$contract) {
            return [];
        }

        $amounts = is_array($contract->amounts) ? $contract->amounts : [];
        $lines   = $amounts['lines'] ?? [];

        if (!empty($lines)) {
            return array_map(fn ($line) => [
                'description' => (string) ($line['name'] ?? 'Goods'),
                'hs_code'     => $this->lookupHsCode((int) ($line['product_id'] ?? 0)),
                'quantity'    => (int) ($line['quantity'] ?? 1),
                'unit_price'  => (float) ($line['unit_price'] ?? 0),
                'currency'    => (string) ($line['currency'] ?? $contract->currency ?? 'AED'),
                'line_total'  => (float) ($line['line_total'] ?? 0),
            ], $lines);
        }

        // Fallback for non-cart contracts — single synthesized line.
        return [[
            'description' => $contract->title,
            'hs_code'     => null,
            'quantity'    => 1,
            'unit_price'  => (float) $contract->total_amount,
            'currency'    => $contract->currency ?? 'AED',
            'line_total'  => (float) $contract->total_amount,
        ]];
    }

    /**
     * Lookup the HS code from the products table. Cheap because the
     * caller is already inside a single render — at most a handful of
     * lookups per document.
     */
    private function lookupHsCode(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }
        return \App\Models\Product::query()->where('id', $productId)->value('hs_code');
    }
}
