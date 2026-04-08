<?php

namespace App\Http\Controllers\Web;

use App\Enums\BidStatus;
use App\Enums\CompanyStatus;
use App\Enums\ContractStatus;
use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\RfqStatus;
use App\Enums\ShipmentStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyInsurance;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\EscrowAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\View\View;

/**
 * Admin landing page — system-wide rollups for the platform admin.
 *
 * Admins see ALL data (no company scoping). Counts are summarised into a
 * comprehensive control-centre with stat cards, "needs attention" alerts,
 * recent activity feeds and quick links into every operational area of
 * the platform. Deeper management lives under the existing dashboard
 * routes; this page is the single jumping-off point.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        // ---- High-level counts ------------------------------------------------
        $stats = [
            // Identity
            'users'              => User::count(),
            'active_users'       => User::where('status', UserStatus::ACTIVE->value)->count(),
            'companies'          => Company::count(),
            'active_companies'   => Company::where('status', CompanyStatus::ACTIVE->value)->count(),
            'pending_companies'  => Company::where('status', CompanyStatus::PENDING->value)->count(),

            // Procurement workflow
            'purchase_requests'        => PurchaseRequest::count(),
            'pending_pr_approvals'     => PurchaseRequest::where('status', PurchaseRequestStatus::PENDING_APPROVAL->value)->count(),
            'rfqs'                     => Rfq::count(),
            'open_rfqs'                => Rfq::where('status', RfqStatus::OPEN->value)->count(),
            'bids'                     => Bid::count(),
            'submitted_bids'           => Bid::where('status', BidStatus::SUBMITTED->value)->count(),

            // Contracts & money
            'contracts'           => Contract::count(),
            'active_contracts'    => Contract::where('status', ContractStatus::ACTIVE->value)->count(),
            'pending_signatures'  => Contract::where('status', ContractStatus::PENDING_SIGNATURES->value)->count(),
            'contract_value'      => (float) Contract::whereIn('status', [
                ContractStatus::SIGNED->value,
                ContractStatus::ACTIVE->value,
                ContractStatus::COMPLETED->value,
            ])->sum('total_amount'),

            'payments'             => Payment::count(),
            'payments_completed'   => (float) Payment::where('status', PaymentStatus::COMPLETED->value)->sum('total_amount'),
            'payments_pending'     => Payment::where('status', PaymentStatus::PENDING_APPROVAL->value)->count(),

            // Trade finance
            'escrow_accounts'      => EscrowAccount::count(),
            'escrow_active'        => EscrowAccount::where('status', EscrowAccount::STATUS_ACTIVE)->count(),
            'escrow_balance'       => (float) EscrowAccount::where('status', EscrowAccount::STATUS_ACTIVE)
                ->selectRaw('COALESCE(SUM(total_deposited - total_released), 0) AS bal')
                ->value('bal'),

            // Logistics & catalog
            'shipments'            => Shipment::count(),
            'in_transit_shipments' => Shipment::whereIn('status', [
                ShipmentStatus::IN_TRANSIT->value,
                ShipmentStatus::IN_CLEARANCE->value,
                ShipmentStatus::READY_FOR_PICKUP->value,
            ])->count(),
            'products'             => Product::count(),
            'categories'           => Category::count(),

            // Risk
            'open_disputes'        => Dispute::whereIn('status', [
                DisputeStatus::OPEN->value,
                DisputeStatus::UNDER_REVIEW->value,
                DisputeStatus::ESCALATED->value,
            ])->count(),

            // Compliance backlog
            'pending_documents'    => CompanyDocument::where('status', CompanyDocument::STATUS_PENDING)->count(),
            'pending_insurances'   => CompanyInsurance::where('status', CompanyInsurance::STATUS_PENDING)->count(),
            'expiring_documents'   => CompanyDocument::where('status', CompanyDocument::STATUS_VERIFIED)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->count(),
        ];

        // ---- Recent activity feeds -------------------------------------------
        $recentUsers     = User::with('company')->latest()->limit(6)->get();
        $recentCompanies = Company::latest()->limit(6)->get();
        $recentAuditLogs = AuditLog::with('user')->latest()->limit(10)->get();

        // ---- "Needs attention" panel — links straight into the work queue ---
        // Each item carries its own SVG path so the queue chip reads as a real
        // category, not just a colored dot.
        $attention = [
            [
                'key'   => 'pending_companies',
                'count' => $stats['pending_companies'],
                'label' => __('admin.attention.pending_companies'),
                'route' => route('admin.companies.index', ['status' => CompanyStatus::PENDING->value]),
                'color' => 'orange',
                'icon'  => 'M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18',
            ],
            [
                'key'   => 'pending_documents',
                'count' => $stats['pending_documents'],
                'label' => __('admin.attention.pending_documents'),
                'route' => route('admin.verification.index'),
                'color' => 'blue',
                'icon'  => 'M9 12h6m-6 4h6M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            ],
            [
                'key'   => 'pending_insurances',
                'count' => $stats['pending_insurances'],
                'label' => __('admin.attention.pending_insurances'),
                'route' => route('admin.verification.index'),
                'color' => 'purple',
                'icon'  => 'M9 12l2 2 4-4M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
            ],
            [
                'key'   => 'expiring_documents',
                'count' => $stats['expiring_documents'],
                'label' => __('admin.attention.expiring_documents'),
                'route' => route('admin.verification.index'),
                'color' => 'orange',
                'icon'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'key'   => 'pending_signatures',
                'count' => $stats['pending_signatures'],
                'label' => __('admin.attention.pending_signatures'),
                'route' => route('admin.oversight.index', ['scope' => 'contracts']),
                'color' => 'blue',
                'icon'  => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            ],
            [
                'key'   => 'pending_payments',
                'count' => $stats['payments_pending'],
                'label' => __('admin.attention.pending_payments'),
                'route' => route('admin.oversight.index', ['scope' => 'payments']),
                'color' => 'teal',
                'icon'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            ],
            [
                'key'   => 'open_disputes',
                'count' => $stats['open_disputes'],
                'label' => __('admin.attention.open_disputes'),
                'route' => route('admin.oversight.index', ['scope' => 'disputes']),
                'color' => 'red',
                'icon'  => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z',
            ],
        ];

        // ---- Quick-link grid — every operational area of the platform -------
        $quickLinks = [
            ['key' => 'users',         'route' => route('admin.users.index'),        'color' => 'blue',   'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['key' => 'companies',     'route' => route('admin.companies.index'),    'color' => 'purple', 'icon' => 'M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18'],
            ['key' => 'verification',  'route' => route('admin.verification.index'), 'color' => 'orange', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['key' => 'categories',    'route' => route('admin.categories.index'),   'color' => 'teal',   'icon' => 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z'],
            ['key' => 'tax_rates',     'route' => route('admin.tax-rates.index'),    'color' => 'green',  'icon' => 'M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ['key' => 'settings',      'route' => route('admin.settings.index'),     'color' => 'slate',  'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z'],
            ['key' => 'audit',         'route' => route('admin.audit.index'),        'color' => 'red',    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['key' => 'oversight',     'route' => route('admin.oversight.index'),    'color' => 'blue',   'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        ];

        return view('dashboard.admin.index', compact(
            'stats',
            'recentUsers',
            'recentCompanies',
            'recentAuditLogs',
            'attention',
            'quickLinks',
        ));
    }
}
