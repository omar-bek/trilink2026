<?php

namespace App\Http\Requests\Bid;

use App\Models\Rfq;
use App\Models\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreBidRequest extends FormRequest
{
    /**
     * The legal Incoterms 2020 set. Used both for validation and to seed the
     * dropdown in the form.
     */
    public const INCOTERMS = ['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF'];

    /**
     * The three VAT treatments a supplier can declare. Anything else is
     * rejected by validation.
     */
    public const TAX_TREATMENTS = ['exclusive', 'inclusive', 'not_applicable'];

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->company_id !== null;
    }

    /**
     * Translate friendlier form inputs into the canonical column shape:
     *
     *   1. validity_days  → validity_date
     *   2. tax_treatment + price → subtotal_excl_tax + tax_amount + total_incl_tax,
     *      using TaxRate::resolveFor() at submit time as the snapshot.
     *   3. items[] from per-line pricing → recompute price as the sum so the
     *      headline number on the bid card always matches the line items.
     */
    protected function prepareForValidation(): void
    {
        // (1) Validity days → ISO date.
        if (!$this->filled('validity_date') && $this->filled('validity_days')) {
            $days = max(1, (int) $this->input('validity_days'));
            $this->merge([
                'validity_date' => now()->addDays($days)->toDateString(),
            ]);
        }

        // (2) Per-line pricing → headline price.
        // If the supplier filled in unit_price for each RFQ line item, the
        // headline `price` is the sum. We don't trust whatever the supplier
        // typed in the price box in this case — the line items are the
        // source of truth.
        $items = $this->input('items', []);
        if (is_array($items) && !empty($items)) {
            $sum = 0;
            $normalized = [];
            foreach ($items as $row) {
                $qty   = (float) ($row['qty'] ?? $row['quantity'] ?? 0);
                $unit  = (float) ($row['unit_price'] ?? 0);
                $total = round($qty * $unit, 2);
                $sum  += $total;
                $normalized[] = [
                    'name'       => (string) ($row['name'] ?? ''),
                    'qty'        => $qty,
                    'unit'       => (string) ($row['unit'] ?? ''),
                    'unit_price' => $unit,
                    'total'      => $total,
                    'spec'       => (string) ($row['spec'] ?? ''),
                ];
            }
            if ($sum > 0) {
                $this->merge([
                    'price' => $sum,
                    'items' => $normalized,
                ]);
            }
        }

        // (3) VAT calculation. Resolve the rate from the RFQ context (rfq
        // category + buyer country) so the snapshot is what was applicable
        // *at submit time*. A supplier who chooses "not_applicable" gets
        // rate = 0.
        $rfqId = $this->route('rfq');
        $rfq = $rfqId ? Rfq::with('company')->find($rfqId) : null;
        $treatment = $this->input('tax_treatment', 'exclusive');
        $price = (float) $this->input('price', 0);

        $rate = 0.0;
        if ($treatment !== 'not_applicable' && $rfq) {
            $rate = (float) TaxRate::resolveFor($rfq->category_id, $rfq->company?->country);
        }

        [$subtotal, $taxAmount, $total] = $this->splitVat($price, $treatment, $rate);

        $this->merge([
            'tax_rate_snapshot' => $rate,
            'subtotal_excl_tax' => $subtotal,
            'tax_amount'        => $taxAmount,
            'total_incl_tax'    => $total,
        ]);
    }

    /**
     * Resolve subtotal / tax / total for a given headline price + treatment.
     * Returns three already-rounded floats.
     *
     * exclusive    → headline price IS the subtotal; tax is added.
     * inclusive    → headline price IS the total; subtotal is back-derived.
     * not_applicable → headline price IS the total; tax = 0.
     */
    private function splitVat(float $price, string $treatment, float $rate): array
    {
        if ($price <= 0) {
            return [0.0, 0.0, 0.0];
        }
        return match ($treatment) {
            'inclusive' => (function () use ($price, $rate) {
                $subtotal = $rate > 0 ? round($price / (1 + $rate / 100), 2) : $price;
                $tax      = round($price - $subtotal, 2);
                return [$subtotal, $tax, $price];
            })(),
            'not_applicable' => [$price, 0.0, $price],
            default => (function () use ($price, $rate) {
                $tax   = round($price * $rate / 100, 2);
                $total = round($price + $tax, 2);
                return [$price, $tax, $total];
            })(),
        };
    }

    public function rules(): array
    {
        return [
            'price'              => ['required', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'size:3'],
            'delivery_time_days' => ['required', 'integer', 'min:1'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'validity_date'      => ['required', 'date', 'after:today'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'items'              => ['nullable', 'array'],
            'items.*.name'       => ['required_with:items', 'string', 'max:255'],
            'items.*.qty'        => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit'       => ['nullable', 'string', 'max:32'],
            'items.*.spec'       => ['nullable', 'string', 'max:500'],
            // Payment schedule: array of milestones with a name and a percent.
            // Percents must total 100; enforced in withValidator() below so the
            // error message reads clearly ("must add up to 100%").
            'payment_schedule'                => ['nullable', 'array'],
            'payment_schedule.*.milestone'    => ['required_with:payment_schedule', 'string', 'max:100'],
            'payment_schedule.*.percentage'   => ['required_with:payment_schedule', 'numeric', 'min:0', 'max:100'],
            // Attachments: supporting documents the supplier uploads with the bid.
            // Stored privately (local disk) since they can contain commercial secrets.
            'attachments'                     => ['nullable', 'array', 'max:10'],
            'attachments.*'                   => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            // Optional extra metadata fields the form uses.
            'tech_specs'                      => ['nullable', 'string', 'max:5000'],
            'delivery_terms'                  => ['nullable', 'string', 'max:1000'],
            'warranty_months'                 => ['nullable', 'integer', 'min:0', 'max:120'],

            // Phase 2 — trade fields. Incoterm + country + tax treatment are
            // declared by the supplier. Subtotal/tax/total are derived in
            // prepareForValidation() and stored verbatim so the contract
            // pipeline can rely on them later.
            'incoterm'             => ['required', 'string', 'in:' . implode(',', self::INCOTERMS)],
            'country_of_origin'    => ['required', 'string', 'size:2'],
            'hs_code'              => ['nullable', 'string', 'max:16'],
            'tax_treatment'        => ['required', 'string', 'in:' . implode(',', self::TAX_TREATMENTS)],
            'tax_exemption_reason' => ['nullable', 'string', 'max:64', 'required_if:tax_treatment,not_applicable'],
            'tax_rate_snapshot'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'subtotal_excl_tax'    => ['nullable', 'numeric', 'min:0'],
            'tax_amount'           => ['nullable', 'numeric', 'min:0'],
            'total_incl_tax'       => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $schedule = $this->input('payment_schedule', []);
            if (empty($schedule)) {
                return;
            }
            $sum = array_sum(array_map(
                fn ($row) => (float) ($row['percentage'] ?? 0),
                \is_array($schedule) ? $schedule : []
            ));
            if (abs($sum - 100.0) > 0.01) {
                $v->errors()->add('payment_schedule', 'Payment schedule percentages must add up to 100% (currently ' . rtrim(rtrim(number_format($sum, 2), '0'), '.') . '%).');
            }
        });
    }
}
