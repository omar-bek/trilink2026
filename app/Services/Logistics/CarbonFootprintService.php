<?php

namespace App\Services\Logistics;

use App\Models\Shipment;

/**
 * Phase 6 — carbon footprint calculator for shipments. Uses simplified
 * DEFRA / GLEC emission factors per mode of transport (kg CO2e per
 * tonne-kilometer). The numbers are intentionally conservative — they're
 * good enough for ESG dashboards but not for regulatory reporting.
 *
 * Inputs:
 *   - mode (sea, air, road, rail)
 *   - distance_km
 *   - weight_kg
 *
 * Output:
 *   - co2e_kg: total kgs of CO2 equivalent
 *   - intensity: kg CO2e per kg of cargo
 *   - factor_used: which DEFRA factor we picked
 *
 * The factors below are 2024 DEFRA values, simplified to one number per
 * mode. A future revision can swap in cargo-type granularity (HFO ship vs
 * container ship vs reefer, etc.) without changing the public API.
 */
class CarbonFootprintService
{
    /**
     * kg CO2e per tonne-kilometer. Sources:
     *   sea  — DEFRA 2024 average container ship
     *   air  — DEFRA 2024 long-haul belly freight (worst common mode)
     *   road — DEFRA 2024 articulated HGV >33t
     *   rail — DEFRA 2024 freight train, diesel
     */
    private const FACTORS_KG_PER_TONNE_KM = [
        'sea' => 0.0162,
        'air' => 0.6020,
        'road' => 0.0823,
        'rail' => 0.0238,
    ];

    /**
     * Calculate the carbon footprint for arbitrary inputs. Returns null
     * for unsupported modes — the caller should fall back to displaying
     * "—" rather than guessing.
     */
    public function calculate(string $mode, float $distanceKm, float $weightKg): ?array
    {
        $mode = strtolower($mode);
        $factor = self::FACTORS_KG_PER_TONNE_KM[$mode] ?? null;
        if ($factor === null || $distanceKm <= 0 || $weightKg <= 0) {
            return null;
        }

        $tonnes = $weightKg / 1000.0;
        $co2e = round($factor * $tonnes * $distanceKm, 2);

        return [
            'mode' => $mode,
            'distance_km' => round($distanceKm, 1),
            'weight_kg' => round($weightKg, 1),
            'co2e_kg' => $co2e,
            'intensity' => round($co2e / max(1, $weightKg), 4),
            'factor_used' => $factor,
        ];
    }

    /**
     * Convenience wrapper for a Shipment row. Reads the shipment's mode
     * (defaults to road), distance (estimated from origin/destination
     * coordinates if available, otherwise null), and weight (sourced from
     * the contract's amounts JSON, falling back to a 100kg placeholder).
     */
    public function forShipment(Shipment $shipment): ?array
    {
        $mode = $this->detectMode($shipment);
        $distance = $this->estimateDistance($shipment);
        $weight = $this->estimateWeight($shipment);

        if ($distance === null || $weight === null) {
            return null;
        }

        return $this->calculate($mode, $distance, $weight);
    }

    /**
     * Heuristic mode detection. We don't have a `mode` column on shipments
     * yet (Phase 6+), so we infer from the carrier name in `notes`. The
     * common carriers in our shipping adapter all have a default mode.
     */
    private function detectMode(Shipment $shipment): string
    {
        $notes = strtolower((string) $shipment->notes);
        if (str_contains($notes, 'air') || str_contains($notes, 'fedex') || str_contains($notes, 'dhl express')) {
            return 'air';
        }
        if (str_contains($notes, 'sea') || str_contains($notes, 'maersk') || str_contains($notes, 'msc')) {
            return 'sea';
        }
        if (str_contains($notes, 'rail') || str_contains($notes, 'train')) {
            return 'rail';
        }

        return 'road';
    }

    /**
     * Estimate the distance between origin and destination using the
     * haversine formula on lat/lng coordinates if both points are
     * available. Otherwise return null — we never want to invent a
     * carbon number out of thin air.
     */
    private function estimateDistance(Shipment $shipment): ?float
    {
        $origin = $shipment->origin ?? [];
        $dest = $shipment->destination ?? [];

        if (! isset($origin['lat'], $origin['lng'], $dest['lat'], $dest['lng'])) {
            // Fall back to a city-pair lookup table for common GCC routes.
            // Keeps the dashboard useful when only city names are present.
            return $this->fallbackDistance(
                strtolower((string) ($origin['text'] ?? $origin['city'] ?? '')),
                strtolower((string) ($dest['text'] ?? $dest['city'] ?? '')),
            );
        }

        return $this->haversine(
            (float) $origin['lat'],
            (float) $origin['lng'],
            (float) $dest['lat'],
            (float) $dest['lng'],
        );
    }

    /**
     * Pull weight from the contract's amounts (catalog Buy-Now snapshots
     * carry quantity and we approximate kg/unit), falling back to a
     * 100kg placeholder so the dashboard renders something rather than
     * nothing.
     */
    private function estimateWeight(Shipment $shipment): float
    {
        $contract = $shipment->contract;
        if (! $contract) {
            return 100.0;
        }

        $amounts = is_array($contract->amounts) ? $contract->amounts : [];
        $lines = $amounts['lines'] ?? [];
        if (empty($lines)) {
            // Fall back to total amount → coarse $1k = 50kg ratio. It's
            // a placeholder, the user can override with a real weight
            // when we add a weight column to shipments later.
            return max(50.0, ((float) $contract->total_amount) / 20.0);
        }

        $total = 0.0;
        foreach ($lines as $line) {
            $qty = (int) ($line['quantity'] ?? 1);
            // Default 5kg per unit when nothing better is available.
            $total += $qty * 5.0;
        }

        return max(1.0, $total);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthKm * $c, 1);
    }

    /**
     * Tiny lookup table for common GCC city pairs. Used when full
     * coordinates aren't available. Returns null for unknown pairs.
     */
    private function fallbackDistance(string $origin, string $destination): ?float
    {
        $key = $this->normalisePair($origin, $destination);
        $table = [
            'dubai|abu dhabi' => 140.0,
            'dubai|sharjah' => 30.0,
            'dubai|riyadh' => 1090.0,
            'dubai|doha' => 380.0,
            'dubai|muscat' => 420.0,
            'dubai|kuwait' => 1100.0,
            'dubai|manama' => 480.0,
            'dubai|jeddah' => 1640.0,
            'riyadh|jeddah' => 950.0,
            'abu dhabi|riyadh' => 870.0,
            'dubai|cairo' => 2410.0,
            'dubai|mumbai' => 1925.0,
            'dubai|shanghai' => 6510.0,
            'dubai|frankfurt' => 4830.0,
            'dubai|london' => 5500.0,
        ];

        return $table[$key] ?? null;
    }

    private function normalisePair(string $a, string $b): string
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return '';
        }

        return $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
    }
}
