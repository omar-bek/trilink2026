<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;

class AnalyticsService
{
    public function dashboard(?int $companyId = null): array
    {
        return [
            'total_users' => User::when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            'purchase_requests' => PurchaseRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            'active_rfqs' => Rfq::where('status', 'open')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            'active_contracts' => Contract::where('status', 'active')
                ->when($companyId, fn ($q) => $q->where('buyer_company_id', $companyId))->count(),
            'pending_payments' => Payment::where('status', 'pending_approval')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            'active_shipments' => Shipment::whereIn('status', ['in_transit', 'in_clearance'])
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
            'open_disputes' => Dispute::whereIn('status', ['open', 'under_review', 'escalated'])
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count(),
        ];
    }

    public function companyStats(int $companyId): array
    {
        return [
            'total_bids' => Bid::where('company_id', $companyId)->count(),
            'accepted_bids' => Bid::where('company_id', $companyId)->where('status', 'accepted')->count(),
            'total_contracts' => Contract::where('buyer_company_id', $companyId)->count(),
            'total_revenue' => Payment::where('recipient_company_id', $companyId)
                ->where('status', 'completed')->sum('total_amount'),
            'total_spent' => Payment::where('company_id', $companyId)
                ->where('status', 'completed')->sum('total_amount'),
        ];
    }

    public function paymentMetrics(?int $companyId = null): array
    {
        $query = Payment::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        return [
            'total_payments' => (clone $query)->count(),
            'completed_amount' => (clone $query)->where('status', 'completed')->sum('total_amount'),
            'pending_amount' => (clone $query)->where('status', 'pending_approval')->sum('total_amount'),
            'failed_count' => (clone $query)->where('status', 'failed')->count(),
            'by_gateway' => Payment::when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->whereNotNull('payment_gateway')
                ->selectRaw('payment_gateway, count(*) as count, sum(total_amount) as total')
                ->groupBy('payment_gateway')
                ->get(),
        ];
    }
}
