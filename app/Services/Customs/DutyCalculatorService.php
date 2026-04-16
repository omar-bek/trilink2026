<?php

namespace App\Services\Customs;

/**
 * Phase 8 (UAE Compliance Roadmap) — GCC Common Customs Tariff (CCT)
 * duty calculator.
 *
 * The GCC CCT applies a uniform 5% ad-valorem customs duty on goods
 * imported from outside the GCC, with three exceptions:
 *
 *   1. Intra-GCC origin → 0% (GCC Customs Union Agreement 2003)
 *   2. Exempted HS codes → 0% (foodstuffs, medicines, educational
 *      materials — Cabinet Decision 80/2023 Annex)
 *   3. Reduced-rate HS codes → variable (some goods at 100% — tobacco,
 *      alcohol — but those are unlikely in a B2B procurement platform)
 *
 * The calculator is PURE and OFFLINE — it uses the origin country +
 * the first 4 digits of the HS code (chapter + heading) to look up
 * the applicable rate. It does NOT call any customs API; the rates
 * are hardcoded from the published GCC CCT schedule and updated when
 * the FCA (Federal Customs Authority) publishes amendments.
 *
 * Returns the duty as a percentage (0, 5, 50, 100) — the caller
 * multiplies by the CIF value to get the duty amount.
 */
class DutyCalculatorService
{
    /**
     * GCC member state ISO codes. Origin in any of these = 0% duty
     * under the GCC Customs Union Agreement.
     */
    private const GCC_COUNTRIES = ['AE', 'SA', 'KW', 'QA', 'BH', 'OM'];

    /**
     * HS chapter headings (first 4 digits) that are EXEMPT from duty
     * regardless of origin. Source: Cabinet Decision 80/2023 Annex +
     * GCC CCT Schedule 1 (exempted goods).
     *
     * This is a representative subset — the full list has ~2,000
     * entries. Extend as needed when the FCA publishes amendments.
     */
    private const EXEMPT_HS_PREFIXES = [
        '0401', '0402',         // Milk and cream
        '1001', '1005', '1006', // Wheat, maize, rice
        '2501',                 // Salt
        '3001', '3002', '3003', '3004', // Pharmaceutical products
        '4901', '4902', '4903', // Books, newspapers, educational
        '2709',                 // Crude petroleum (pipeline imports)
    ];

    /**
     * HS prefixes subject to HIGHER duty (sin tax / protective tariff).
     * 2402 = cigars/cigarettes (100%), 2203 = beer (50%).
     */
    private const HIGH_RATE_HS = [
        '2402' => 100,
        '2403' => 100,
        '2203' => 50,
        '2204' => 50,
        '2208' => 50,
    ];

    /**
     * Calculate the applicable customs duty rate for a given origin
     * country and HS code.
     *
     * @return array{rate: float, basis: string}
     */
    public function calculate(string $originCountry, ?string $hsCode): array
    {
        $origin = strtoupper(trim($originCountry));

        // Rule 1: intra-GCC origin = 0%
        if (in_array($origin, self::GCC_COUNTRIES, true)) {
            return [
                'rate'  => 0.0,
                'basis' => 'GCC Customs Union Agreement — intra-GCC origin, 0% duty.',
            ];
        }

        $prefix = substr(preg_replace('/\D/', '', (string) $hsCode), 0, 4);

        // Rule 2: exempted HS codes = 0%
        if ($prefix && in_array($prefix, self::EXEMPT_HS_PREFIXES, true)) {
            return [
                'rate'  => 0.0,
                'basis' => "HS {$prefix} is exempt from customs duty under Cabinet Decision 80/2023.",
            ];
        }

        // Rule 3: higher-rate HS codes
        if ($prefix && isset(self::HIGH_RATE_HS[$prefix])) {
            $rate = (float) self::HIGH_RATE_HS[$prefix];
            return [
                'rate'  => $rate,
                'basis' => "HS {$prefix} is subject to a {$rate}% protective tariff under GCC CCT.",
            ];
        }

        // Default: standard 5% GCC CCT
        return [
            'rate'  => 5.0,
            'basis' => 'Standard GCC Common Customs Tariff — 5% ad-valorem on non-GCC origin goods.',
        ];
    }
}
