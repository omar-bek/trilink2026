<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Payment;
use App\Models\TaxInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxInvoice>
 */
class TaxInvoiceFactory extends Factory
{
    protected $model = TaxInvoice::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        $subtotal = fake()->numberBetween(1000, 100000);
        $tax = round($subtotal * 0.05, 2);
        $total = round($subtotal + $tax, 2);

        $supplier = Company::factory()->supplier();
        $buyer = Company::factory()->buyer();

        return [
            'invoice_number' => sprintf('INV-%d-%06d', date('Y'), $counter),
            'payment_id' => Payment::factory(),
            'issue_date' => now()->toDateString(),
            'supply_date' => now()->subDays(1)->toDateString(),
            'supplier_company_id' => $supplier,
            'supplier_name' => fake()->company(),
            'supplier_trn' => '1000'.fake()->unique()->numerify('##########'),
            'buyer_company_id' => $buyer,
            'buyer_name' => fake()->company(),
            'buyer_trn' => '1000'.fake()->unique()->numerify('##########'),
            'subtotal_excl_tax' => $subtotal,
            'total_tax' => $tax,
            'total_inclusive' => $total,
            'currency' => 'AED',
            'vat_treatment' => 'standard',
            'line_items' => [
                ['description' => 'Item', 'quantity' => 1, 'unit_price' => $subtotal, 'amount' => $subtotal, 'tax_rate' => 5.0, 'tax_amount' => $tax, 'total' => $total],
            ],
            'status' => 'issued',
            'issued_by' => User::factory(),
            'issued_at' => now(),
        ];
    }

    public function issued(): self
    {
        return $this->state(['status' => 'issued']);
    }

    public function voided(): self
    {
        return $this->state([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function withVatReturnPeriod(string $period): self
    {
        return $this->state(['vat_return_period' => $period]);
    }

    public function reverseCharge(): self
    {
        return $this->state([
            'vat_treatment' => 'reverse_charge',
            'total_tax' => 0,
        ]);
    }
}
