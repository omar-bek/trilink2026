<?php

namespace Tests\Unit;

use App\Services\EInvoice\PintAeMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for PintAeMapper::validate() — the structural
 * conformance gate that runs before any UBL document is handed to
 * the dispatcher. No DB / no Laravel kernel required: validate()
 * is a function from string → void / RuntimeException, so we test
 * it as such.
 *
 * Each test exercises one invariant the FTA validator rejects most
 * commonly. The "valid" baseline string is constructed once and
 * minimally mutated per case so the failure mode is unambiguous.
 */
class PintAeMapperValidationTest extends TestCase
{
    private function mapper(): PintAeMapper
    {
        return new PintAeMapper();
    }

    /**
     * Build a structurally-valid PINT-AE Invoice XML string. Each
     * test takes this baseline and breaks ONE field so the failure
     * is attributable to a specific invariant.
     */
    private function validInvoiceXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:peppol:pint:billing-1@ae-1</cbc:CustomizationID>
    <cbc:ProfileID>urn:peppol:bis:billing</cbc:ProfileID>
    <cbc:ID>INV-0001</cbc:ID>
    <cbc:IssueDate>2026-04-09</cbc:IssueDate>
    <cbc:InvoiceTypeCode>388</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>AED</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>AED</cbc:TaxCurrencyCode>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0235">100123456700003</cbc:EndpointID>
            <cac:PartyName><cbc:Name>Supplier Co</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>Sheikh Zayed Rd</cbc:StreetName>
                <cac:Country><cbc:IdentificationCode>AE</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cbc:EndpointID schemeID="0235">100987654300003</cbc:EndpointID>
            <cac:PartyName><cbc:Name>Buyer Co</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>Corniche Rd</cbc:StreetName>
                <cac:Country><cbc:IdentificationCode>AE</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="AED">100.00</cbc:LineExtensionAmount>
        <cbc:PayableAmount currencyID="AED">105.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
XML;
    }

    public function test_passes_for_a_well_formed_invoice(): void
    {
        $this->mapper()->validate($this->validInvoiceXml(), 'Invoice');
        $this->expectNotToPerformAssertions();
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not well-formed/');
        $this->mapper()->validate('<Invoice><unclosed>', 'Invoice');
    }

    public function test_rejects_missing_customization_id(): void
    {
        $xml = preg_replace(
            '#<cbc:CustomizationID>.*?</cbc:CustomizationID>#',
            '',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CustomizationID/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_empty_invoice_id(): void
    {
        $xml = str_replace('<cbc:ID>INV-0001</cbc:ID>', '<cbc:ID></cbc:ID>', $this->validInvoiceXml());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ID is empty/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_invalid_currency_code(): void
    {
        $xml = str_replace(
            '<cbc:DocumentCurrencyCode>AED</cbc:DocumentCurrencyCode>',
            '<cbc:DocumentCurrencyCode>DIRHAM</cbc:DocumentCurrencyCode>',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid currency/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_invalid_country_code(): void
    {
        // Replace the country code with a 4-letter string — the FTA
        // validator wants ISO 3166-1 alpha-2 only.
        $xml = str_replace(
            '<cbc:IdentificationCode>AE</cbc:IdentificationCode>',
            '<cbc:IdentificationCode>UAE</cbc:IdentificationCode>',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid country/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_negative_monetary_amount(): void
    {
        $xml = str_replace(
            '<cbc:PayableAmount currencyID="AED">105.00</cbc:PayableAmount>',
            '<cbc:PayableAmount currencyID="AED">-50.00</cbc:PayableAmount>',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/negative/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_non_numeric_monetary_amount(): void
    {
        $xml = str_replace(
            '<cbc:PayableAmount currencyID="AED">105.00</cbc:PayableAmount>',
            '<cbc:PayableAmount currencyID="AED">one hundred</cbc:PayableAmount>',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not a valid decimal/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_missing_endpoint_id(): void
    {
        $xml = preg_replace(
            '#<cbc:EndpointID schemeID="0235">[^<]*</cbc:EndpointID>#',
            '',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/EndpointID/');
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_endpoint_with_wrong_scheme_id(): void
    {
        // The scheme MUST be 0235 (FTA TRN). Anything else means the
        // ASP can't route the document on the Peppol network.
        $xml = str_replace('schemeID="0235"', 'schemeID="0088"', $this->validInvoiceXml());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches(
            "/schemeID must be '0235'/"
        );
        $this->mapper()->validate($xml, 'Invoice');
    }

    public function test_rejects_missing_supplier_party(): void
    {
        $xml = preg_replace(
            '#<cac:AccountingSupplierParty>.*?</cac:AccountingSupplierParty>#s',
            '',
            $this->validInvoiceXml()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/AccountingSupplierParty/');
        $this->mapper()->validate($xml, 'Invoice');
    }
}
