<?php

namespace Tests\Unit\Services\Payments;

use App\Models\Contract;
use App\Models\Payment;
use App\Services\Payments\CorporateTaxService;
use PHPUnit\Framework\TestCase;

class CorporateTaxServiceTest extends TestCase
{
    private CorporateTaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CorporateTaxService();
    }

    public function test_contract_without_ct_flag_produces_zero(): void
    {
        $payment = $this->make(contractCtApplicable: false);

        $this->service->apply($payment);

        $this->assertFalse((bool) $payment->corporate_tax_applicable);
        $this->assertSame(0, (int) $payment->corporate_tax_rate);
        $this->assertSame(0, (int) $payment->corporate_tax_amount);
    }

    public function test_default_standard_rate_is_nine_percent(): void
    {
        $this->assertSame(9.0, CorporateTaxService::RATE_DEFAULT);
        $this->assertSame(0.0, CorporateTaxService::RATE_QFZP);
        $this->assertSame(375000, CorporateTaxService::STANDARD_THRESHOLD_AED);
    }

    public function test_wht_is_deducted_from_ct_base(): void
    {
        // 10,000 base × 9% = 900, but if 500 WHT already withheld,
        // CT base should be 9,500 × 9% = 855.
        $payment = $this->make(contractCtApplicable: true);
        $payment->amount = 10000;
        $payment->wht_amount = 500;

        $this->service->apply($payment);

        $this->assertSame(9.0, (float) $payment->corporate_tax_rate);
        $this->assertSame(855.0, (float) $payment->corporate_tax_amount);
    }

    private function make(bool $contractCtApplicable): Payment
    {
        $contract = new Contract();
        $contract->corporate_tax_applicable = $contractCtApplicable;

        $payment = new Payment();
        $payment->amount = 10000;
        $payment->recipient_company_id = null;
        $payment->setRelation('contract', $contract);

        return $payment;
    }
}
