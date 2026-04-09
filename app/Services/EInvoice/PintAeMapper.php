<?php

namespace App\Services\EInvoice;

use App\Models\TaxCreditNote;
use App\Models\TaxInvoice;
use DOMDocument;
use DOMElement;

/**
 * Phase 5 (UAE Compliance Roadmap) — convert a TaxInvoice into a UBL
 * 2.1 PINT-AE XML document.
 *
 * PINT-AE (Peppol International — Arab Emirates) is the FTA's
 * customisation of the Peppol BIS Billing 3.0 specification. It
 * narrows the open UBL 2.1 grammar down to the fields the FTA
 * actually validates: TRN, invoice number, dates, line items, tax
 * subtotals, parties, currency, totals.
 *
 * The full PINT-AE schema is hundreds of optional fields; the FTA
 * Phase 1 release validates roughly 30 of them. This mapper covers
 * those 30 — anything beyond is ASP-specific extension territory and
 * will be added when we sign with a real ASP.
 *
 * Important contract:
 *
 *   - Output is well-formed UBL 2.1. Schema-validating it against
 *     the official xsd is the responsibility of the test suite + the
 *     ASP itself; we don't ship the xsd in vendor/.
 *
 *   - The mapper is PURE — it doesn't touch the database, doesn't
 *     load relationships beyond what's already loaded, and doesn't
 *     mutate the TaxInvoice. Caller is responsible for eager-loading
 *     supplier/buyer/credit notes if those are needed (none are
 *     needed for the basic invoice case).
 *
 *   - Failures are exceptions. The mapper either produces valid XML
 *     or it throws — never returns half-built XML. The caller
 *     (EInvoiceDispatcher) catches and stamps the failure on the
 *     submission row.
 */
class PintAeMapper
{
    public const UBL_VERSION = '2.1';

    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * The PINT-AE customisation identifier the FTA validates against.
     * It's a fixed string published by the FTA in the Phase 1 spec —
     * if it changes, only this constant + the test fixture move.
     */
    private const CUSTOMIZATION_ID = 'urn:peppol:pint:billing-1@ae-1';
    private const PROFILE_ID       = 'urn:peppol:bis:billing';

    public function toUbl(TaxInvoice $invoice): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $doc->appendChild($root);

        // Required PINT-AE customisation header — the FTA's validator
        // looks for this exact pair before reading anything else.
        $this->cbc($doc, $root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->cbc($doc, $root, 'ProfileID', self::PROFILE_ID);

        $this->cbc($doc, $root, 'ID', $invoice->invoice_number);
        $this->cbc($doc, $root, 'IssueDate', $invoice->issue_date?->format('Y-m-d'));
        $this->cbc($doc, $root, 'DueDate', $invoice->supply_date?->format('Y-m-d'));
        // FTA invoice type code 388 = standard tax invoice (UN/CEFACT 1001).
        // Credit notes use 381 — see toCreditNoteUbl() below.
        $this->cbc($doc, $root, 'InvoiceTypeCode', '388');
        $this->cbc($doc, $root, 'DocumentCurrencyCode', $invoice->currency ?? 'AED');
        $this->cbc($doc, $root, 'TaxCurrencyCode', $invoice->currency ?? 'AED');

        $this->buildParty($doc, $root, 'AccountingSupplierParty', [
            'name'    => $invoice->supplier_name,
            'trn'     => $invoice->supplier_trn,
            'address' => $invoice->supplier_address,
            'country' => $invoice->supplier_country,
        ]);

        $this->buildParty($doc, $root, 'AccountingCustomerParty', [
            'name'    => $invoice->buyer_name,
            'trn'     => $invoice->buyer_trn,
            'address' => $invoice->buyer_address,
            'country' => $invoice->buyer_country,
        ]);

        $this->buildTaxTotal($doc, $root, $invoice);
        $this->buildLegalMonetaryTotal($doc, $root, $invoice);
        $this->buildLineItems($doc, $root, $invoice);

        return $doc->saveXML();
    }

    /**
     * Phase 5.5 (UAE Compliance Roadmap — post-implementation hardening)
     * — convert a TaxCreditNote into a UBL 2.1 PINT-AE CreditNote
     * document.
     *
     * Differences from toUbl():
     *   - Root element is `CreditNote` (not `Invoice`)
     *   - InvoiceTypeCode is replaced by `CreditNoteTypeCode` 381
     *     (UN/CEFACT — credit note related to goods/services)
     *   - The credit note carries a `BillingReference` element pointing
     *     back at the original tax invoice — without it the FTA can't
     *     match the reversal to the original output VAT.
     *   - Party details are SNAPSHOTTED from the original invoice
     *     because the credit note row doesn't carry buyer/supplier
     *     fields (it inherits them from `originalInvoice`).
     *
     * The line items, totals and tax sections reuse the same shape so
     * the buildLineItems / buildTaxTotal / buildLegalMonetaryTotal
     * helpers can be reused via tiny adapter shims.
     */
    public function toCreditNoteUbl(TaxCreditNote $cn): string
    {
        $cn->loadMissing('originalInvoice');
        $original = $cn->originalInvoice;

        if (!$original) {
            throw new \RuntimeException(
                "Cannot map credit note {$cn->id} to UBL: original invoice no longer exists."
            );
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root element + namespace are different for credit notes.
        $rootNs = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
        $root = $doc->createElementNS($rootNs, 'CreditNote');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $doc->appendChild($root);

        $this->cbc($doc, $root, 'CustomizationID', self::CUSTOMIZATION_ID);
        $this->cbc($doc, $root, 'ProfileID', self::PROFILE_ID);

        $this->cbc($doc, $root, 'ID', $cn->credit_note_number);
        $this->cbc($doc, $root, 'IssueDate', $cn->issue_date?->format('Y-m-d'));
        $this->cbc($doc, $root, 'CreditNoteTypeCode', '381');
        $this->cbc($doc, $root, 'DocumentCurrencyCode', $cn->currency ?? 'AED');
        $this->cbc($doc, $root, 'TaxCurrencyCode', $cn->currency ?? 'AED');

        // BillingReference → original tax invoice. Without this the FTA
        // cannot match the credit to the output VAT it's reversing,
        // and the buyer cannot reverse the input VAT they previously
        // claimed (Cabinet Decision 52/2017 Article 60).
        $billingRef = $doc->createElementNS(self::NS_CAC, 'cac:BillingReference');
        $invoiceRef = $doc->createElementNS(self::NS_CAC, 'cac:InvoiceDocumentReference');
        $this->cbc($doc, $invoiceRef, 'ID', $original->invoice_number);
        $this->cbc($doc, $invoiceRef, 'IssueDate', $original->issue_date?->format('Y-m-d'));
        $billingRef->appendChild($invoiceRef);
        $root->appendChild($billingRef);

        // Parties — snapshot from the original invoice.
        $this->buildParty($doc, $root, 'AccountingSupplierParty', [
            'name'    => $original->supplier_name,
            'trn'     => $original->supplier_trn,
            'address' => $original->supplier_address,
            'country' => $original->supplier_country,
        ]);

        $this->buildParty($doc, $root, 'AccountingCustomerParty', [
            'name'    => $original->buyer_name,
            'trn'     => $original->buyer_trn,
            'address' => $original->buyer_address,
            'country' => $original->buyer_country,
        ]);

        // Reuse the shared total/line builders. They take a TaxInvoice
        // type hint but only touch the line_items / total_* fields,
        // which TaxCreditNote also exposes — we wrap the credit note
        // in a thin in-memory adapter so the helpers don't need to
        // change.
        $adapter = $this->creditNoteAsInvoiceShape($cn);
        $this->buildTaxTotal($doc, $root, $adapter);
        $this->buildLegalMonetaryTotal($doc, $root, $adapter);
        $this->buildLineItems($doc, $root, $adapter);

        return $doc->saveXML();
    }

    /**
     * Wrap a TaxCreditNote in an unsaved TaxInvoice instance whose
     * fields the shared total/line builders can read. We don't persist
     * the wrapper — it lives only inside the mapper call. This avoids
     * either (a) duplicating the build* helpers for credit notes or
     * (b) refactoring them to a common interface that PHP's static
     * type system can't easily express.
     */
    private function creditNoteAsInvoiceShape(TaxCreditNote $cn): TaxInvoice
    {
        $shim = new TaxInvoice();
        $shim->setRawAttributes([
            'currency'          => $cn->currency,
            'subtotal_excl_tax' => (string) $cn->subtotal_excl_tax,
            'total_discount'    => '0.00',
            'total_tax'         => (string) $cn->total_tax,
            'total_inclusive'   => (string) $cn->total_inclusive,
            'line_items'        => is_array($cn->line_items)
                ? json_encode($cn->line_items)
                : (string) $cn->line_items,
        ]);
        return $shim;
    }

    private function buildParty(DOMDocument $doc, DOMElement $root, string $partyTag, array $party): void
    {
        $partyEl = $doc->createElementNS(self::NS_CAC, "cac:{$partyTag}");
        $root->appendChild($partyEl);

        $inner = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        $partyEl->appendChild($inner);

        // Party name
        $partyName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $this->cbc($doc, $partyName, 'Name', $party['name'] ?? '—');
        $inner->appendChild($partyName);

        // Address
        $address = $doc->createElementNS(self::NS_CAC, 'cac:PostalAddress');
        $this->cbc($doc, $address, 'StreetName', $party['address'] ?? '');
        $country = $doc->createElementNS(self::NS_CAC, 'cac:Country');
        $this->cbc($doc, $country, 'IdentificationCode', $party['country'] ?? 'AE');
        $address->appendChild($country);
        $inner->appendChild($address);

        // Tax registration. The FTA expects scheme=VAT for the TRN.
        if (!empty($party['trn'])) {
            $taxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
            $this->cbc($doc, $taxScheme, 'CompanyID', $party['trn']);
            $scheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->cbc($doc, $scheme, 'ID', 'VAT');
            $taxScheme->appendChild($scheme);
            $inner->appendChild($taxScheme);
        }
    }

    private function buildTaxTotal(DOMDocument $doc, DOMElement $root, TaxInvoice $invoice): void
    {
        $currency = $invoice->currency ?? 'AED';

        $taxTotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $this->cbcAmount($doc, $taxTotal, 'TaxAmount', $invoice->total_tax, $currency);
        $root->appendChild($taxTotal);

        // One TaxSubtotal per line tax-rate bracket. Phase 1 uses a
        // single-line invoice so this is one row, but the loop is
        // already shaped for multi-line invoices.
        foreach ((array) $invoice->line_items as $line) {
            $subtotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
            $this->cbcAmount($doc, $subtotal, 'TaxableAmount', (float) ($line['taxable_amount'] ?? 0), $currency);
            $this->cbcAmount($doc, $subtotal, 'TaxAmount', (float) ($line['tax_amount'] ?? 0), $currency);

            $category = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
            // FTA category codes: S=Standard 5%, Z=Zero, E=Exempt,
            // O=Out of scope. We default to S — designated zone /
            // reverse charge will be added in a later phase.
            $this->cbc($doc, $category, 'ID', 'S');
            $this->cbc($doc, $category, 'Percent', (string) ((float) ($line['tax_rate'] ?? 5)));

            $scheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->cbc($doc, $scheme, 'ID', 'VAT');
            $category->appendChild($scheme);

            $subtotal->appendChild($category);
            $taxTotal->appendChild($subtotal);
        }
    }

    private function buildLegalMonetaryTotal(DOMDocument $doc, DOMElement $root, TaxInvoice $invoice): void
    {
        $currency = $invoice->currency ?? 'AED';

        $totals = $doc->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');
        $this->cbcAmount($doc, $totals, 'LineExtensionAmount', $invoice->subtotal_excl_tax, $currency);
        $this->cbcAmount($doc, $totals, 'TaxExclusiveAmount', $invoice->subtotal_excl_tax, $currency);
        $this->cbcAmount($doc, $totals, 'TaxInclusiveAmount', $invoice->total_inclusive, $currency);
        $this->cbcAmount($doc, $totals, 'PayableAmount', $invoice->total_inclusive, $currency);
        $root->appendChild($totals);
    }

    private function buildLineItems(DOMDocument $doc, DOMElement $root, TaxInvoice $invoice): void
    {
        $currency = $invoice->currency ?? 'AED';

        foreach ((array) $invoice->line_items as $i => $line) {
            $lineEl = $doc->createElementNS(self::NS_CAC, 'cac:InvoiceLine');
            $this->cbc($doc, $lineEl, 'ID', (string) ($i + 1));

            $qty = $doc->createElementNS(self::NS_CBC, 'cbc:InvoicedQuantity', (string) (float) ($line['quantity'] ?? 1));
            $qty->setAttribute('unitCode', $this->mapUnit($line['unit'] ?? null));
            $lineEl->appendChild($qty);

            $this->cbcAmount($doc, $lineEl, 'LineExtensionAmount', (float) ($line['taxable_amount'] ?? 0), $currency);

            $item = $doc->createElementNS(self::NS_CAC, 'cac:Item');
            $this->cbc($doc, $item, 'Name', (string) ($line['description'] ?? '—'));
            $lineEl->appendChild($item);

            $price = $doc->createElementNS(self::NS_CAC, 'cac:Price');
            $this->cbcAmount($doc, $price, 'PriceAmount', (float) ($line['unit_price'] ?? 0), $currency);
            $lineEl->appendChild($price);

            $root->appendChild($lineEl);
        }
    }

    private function cbc(DOMDocument $doc, DOMElement $parent, string $tag, ?string $value): void
    {
        $el = $doc->createElementNS(self::NS_CBC, "cbc:{$tag}", (string) $value);
        $parent->appendChild($el);
    }

    private function cbcAmount(DOMDocument $doc, DOMElement $parent, string $tag, $value, string $currency): void
    {
        $el = $doc->createElementNS(self::NS_CBC, "cbc:{$tag}", number_format((float) $value, 2, '.', ''));
        $el->setAttribute('currencyID', $currency);
        $parent->appendChild($el);
    }

    /**
     * Map a free-form unit string to a UN/ECE Recommendation 20 code.
     * The codes the FTA's validator accepts are listed in PINT-AE; for
     * the basic Phase 1 case the only one we hit is C62 (one).
     */
    private function mapUnit(?string $unit): string
    {
        return match (mb_strtolower((string) $unit)) {
            'each', 'pcs', 'piece', 'unit', '' => 'C62',
            'kg'                                => 'KGM',
            'litre', 'liter', 'l'               => 'LTR',
            'meter', 'metre', 'm'               => 'MTR',
            'hour', 'hr', 'h'                   => 'HUR',
            default                              => 'C62',
        };
    }
}
