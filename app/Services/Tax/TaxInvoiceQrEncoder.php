<?php

namespace App\Services\Tax;

use App\Models\TaxInvoice;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Encodes a TaxInvoice into the FTA-style QR payload and renders it as a
 * base64 PNG data URL ready to drop into the PDF.
 *
 * The FTA's E-Invoicing Phase 1 (effective July 2026) requires every
 * compliant tax invoice to carry a QR code containing the seller TRN,
 * invoice number, issue timestamp, total amount, and total tax. The
 * exact encoding has not yet been finalised by the FTA at the time of
 * Phase 1 of the UAE Compliance Roadmap, so we follow the analogous and
 * very similar GAZT (Saudi ZATCA) Phase 1 spec — pipe-delimited tag-length
 * value triples — which is the closest published precedent in the GCC.
 *
 *   tag (1 byte) | length (1 byte) | UTF-8 value
 *
 *   tag 1 = seller name
 *   tag 2 = seller TRN
 *   tag 3 = ISO-8601 timestamp
 *   tag 4 = invoice total inclusive of VAT
 *   tag 5 = total VAT
 *
 * The whole TLV byte string is base64-encoded into the QR. Once the FTA
 * publishes its own TLV map (or switches the spec to a JSON Web Token
 * encoding, which is the rumour for Phase 2) only this class needs to
 * change — the PDF view, the model, and the rest of the pipeline are
 * untouched.
 */
class TaxInvoiceQrEncoder
{
    /**
     * Build the TLV byte string for a tax invoice.
     */
    public function buildPayload(TaxInvoice $invoice): string
    {
        $fields = [
            1 => (string) ($invoice->supplier_name ?? ''),
            2 => (string) ($invoice->supplier_trn ?? ''),
            3 => $invoice->issued_at?->toIso8601String() ?? $invoice->issue_date?->toIso8601String() ?? '',
            4 => number_format((float) $invoice->total_inclusive, 2, '.', ''),
            5 => number_format((float) $invoice->total_tax, 2, '.', ''),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            // chr() because tag and length are single bytes per the TLV
            // spec — values longer than 255 bytes need a different
            // encoding which we don't hit for invoice metadata.
            $bytes = (string) $value;
            $tlv .= chr($tag) . chr(strlen($bytes)) . $bytes;
        }

        return base64_encode($tlv);
    }

    /**
     * Render the QR code as a base64 PNG data URL ready for an <img> tag.
     * Returns null if the GD extension is not loaded — in that case the
     * PDF view should fall back to displaying the QR-equivalent text
     * block instead of breaking the whole document.
     */
    public function renderDataUri(TaxInvoice $invoice, int $sizePx = 180): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $payload = $this->buildPayload($invoice);

        // Margin = 1 keeps the quiet zone tight so the code reads cleanly
        // at the small print size used in the invoice header.
        $renderer = new GDLibRenderer(size: $sizePx, margin: 1, imageFormat: 'png');
        $writer   = new Writer($renderer);

        $png = $writer->writeString($payload);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
