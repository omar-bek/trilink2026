<?php

namespace App\Services;

use App\Models\Bid;

/**
 * Recalculates VAT for a counter-offer against the ORIGINAL bid's locked
 * tax treatment.
 *
 * The rule in UAE B2B procurement:
 *   Once the supplier has declared whether the quoted price is VAT
 *   exclusive / inclusive / not-applicable, that declaration is part of
 *   the commercial offer and cannot be silently changed by either party
 *   during negotiation. Counter-offers therefore inherit the original
 *   `tax_treatment` and `tax_rate_snapshot` and we recompute subtotal /
 *   VAT / total on the new amount — giving buyers and suppliers a live,
 *   honest picture of what they're actually agreeing to.
 *
 * Returns an array with:
 *   - subtotal_excl_tax
 *   - tax_amount
 *   - total_incl_tax
 * All three are always populated so downstream code can render them
 * uniformly regardless of the treatment. For `not_applicable` the tax
 * amount is 0 and subtotal == total.
 */
class NegotiationVatCalculator
{
    /**
     * @param  float  $amount  the counter-offer amount as keyed into the form
     *                         — interpreted the same way the original bid's
     *                         amount was interpreted (exclusive ⇒ subtotal,
     *                         inclusive ⇒ total).
     * @return array{subtotal_excl_tax: float, tax_amount: float, total_incl_tax: float, rate: float, treatment: string}
     */
    public function recalculate(Bid $bid, float $amount): array
    {
        $treatment = (string) ($bid->tax_treatment ?: 'exclusive');
        $rate = (float) ($bid->tax_rate_snapshot ?? 5.0); // UAE VAT default 5%.

        // Defensive: amounts must be >= 0; downstream routes already validate
        // this but we keep the math total-ordered either way.
        $amount = max(0.0, $amount);

        return match ($treatment) {
            'inclusive' => $this->fromInclusive($amount, $rate, $treatment),
            'not_applicable' => [
                'subtotal_excl_tax' => round($amount, 2),
                'tax_amount' => 0.0,
                'total_incl_tax' => round($amount, 2),
                'rate' => 0.0,
                'treatment' => $treatment,
            ],
            default => $this->fromExclusive($amount, $rate, 'exclusive'),
        };
    }

    private function fromExclusive(float $subtotal, float $rate, string $treatment): array
    {
        $tax = round($subtotal * ($rate / 100), 2);

        return [
            'subtotal_excl_tax' => round($subtotal, 2),
            'tax_amount' => $tax,
            'total_incl_tax' => round($subtotal + $tax, 2),
            'rate' => $rate,
            'treatment' => $treatment,
        ];
    }

    private function fromInclusive(float $total, float $rate, string $treatment): array
    {
        $denom = 1 + ($rate / 100);
        $subtotal = $denom > 0 ? round($total / $denom, 2) : round($total, 2);
        $tax = round($total - $subtotal, 2);

        return [
            'subtotal_excl_tax' => $subtotal,
            'tax_amount' => $tax,
            'total_incl_tax' => round($total, 2),
            'rate' => $rate,
            'treatment' => $treatment,
        ];
    }
}
