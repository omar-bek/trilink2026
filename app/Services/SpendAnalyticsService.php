<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-side aggregations for the buyer Spend Analytics dashboard.
 *
 * Pulls from the contracts table (the source of truth for committed spend
 * once both parties sign) rather than payments — payments leak the schedule
 * across months, contracts pin spend to the moment of commitment which is
 * what CFOs care about for budget tracking.
 *
 * Every method is scoped to a single buyer company. Numbers shown to a
 * branch manager are further scoped by branch in the controller.
 */
class SpendAnalyticsService
{
    /**
     * High-level totals: spend in last 30 / 90 / 365 days, contract count,
     * average contract value, top supplier.
     */
    public function summary(int $companyId, ?int $branchId = null): array
    {
        $base = Contract::query()
            ->where('buyer_company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $now = Carbon::now();

        $total = (clone $base)->sum('total_amount');
        $last30 = (clone $base)->where('created_at', '>=', $now->copy()->subDays(30))->sum('total_amount');
        $last90 = (clone $base)->where('created_at', '>=', $now->copy()->subDays(90))->sum('total_amount');
        $last365 = (clone $base)->where('created_at', '>=', $now->copy()->subDays(365))->sum('total_amount');
        $count = (clone $base)->count();
        $avgValue = $count > 0 ? round((float) $total / $count, 2) : 0;

        return [
            'total_spend' => (float) $total,
            'spend_last_30_days' => (float) $last30,
            'spend_last_90_days' => (float) $last90,
            'spend_last_365_days' => (float) $last365,
            'contract_count' => (int) $count,
            'avg_contract_value' => $avgValue,
        ];
    }

    /**
     * Spend grouped by supplier, top N. Returns contract count + total
     * spent so the buyer can see concentration risk (e.g. 60% of spend on
     * one supplier is a red flag).
     */
    public function topSuppliers(int $companyId, int $limit = 10, ?int $branchId = null): Collection
    {
        return Contract::query()
            ->where('buyer_company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['parties', 'total_amount', 'currency'])
            ->flatMap(function (Contract $c) {
                $supplier = collect($c->parties ?? [])->firstWhere('role', 'supplier');
                if (! $supplier) {
                    return [];
                }

                return [[
                    'company_id' => $supplier['company_id'] ?? null,
                    'name' => $supplier['name'] ?? '—',
                    'amount' => (float) $c->total_amount,
                ]];
            })
            ->groupBy('company_id')
            ->map(function (Collection $rows, $supplierId) {
                return [
                    'company_id' => $supplierId,
                    'name' => $rows->first()['name'] ?? '—',
                    'count' => $rows->count(),
                    'total' => round($rows->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values();
    }

    /**
     * Monthly spend trend for the last 12 months. Returns 12 ordered rows
     * even when some months have zero spend so the chart renders flat lines
     * properly instead of skipping months.
     */
    public function monthlyTrend(int $companyId, ?int $branchId = null): array
    {
        $rows = Contract::query()
            ->where('buyer_company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->get(['created_at', 'total_amount'])
            ->groupBy(fn (Contract $c) => $c->created_at->format('Y-m'))
            ->map(fn (Collection $g) => round($g->sum('total_amount'), 2));

        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $trend[] = [
                'month' => $key,
                'label' => now()->subMonths($i)->format('M Y'),
                'total' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Spend grouped by category. Useful for "where am I spending most" and
     * eventually for category-level should-cost benchmarking.
     */
    public function spendByCategory(int $companyId, ?int $branchId = null): Collection
    {
        // Contracts don't carry category directly — they trace back through
        // the optional purchase_request. Contracts created via Buy-Now have
        // no PR, so we group "Other" for those.
        return Contract::query()
            ->where('buyer_company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('purchaseRequest.category')
            ->get(['id', 'purchase_request_id', 'total_amount'])
            ->groupBy(fn (Contract $c) => $c->purchaseRequest?->category?->name ?? __('analytics.uncategorized'))
            ->map(function (Collection $rows, $categoryName) {
                return [
                    'category' => $categoryName,
                    'count' => $rows->count(),
                    'total' => round($rows->sum('total_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
