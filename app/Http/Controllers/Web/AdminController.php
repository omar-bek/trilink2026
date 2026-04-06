<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\View\View;

/**
 * Admin landing page — system-wide rollups for the platform admin.
 *
 * Admins see ALL data (no company scoping). The data is summarized into
 * cards + tables; deeper management lives under existing dashboard routes.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users'      => User::count(),
            'companies'  => Company::count(),
            'pending_companies' => Company::where('status', 'pending')->count(),
            'prs'        => PurchaseRequest::count(),
            'contracts'  => Contract::count(),
            'payments_total' => (float) Payment::where('status', 'completed')->sum('total_amount'),
            'open_disputes'  => Dispute::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
        ];

        $recentUsers     = User::with('company')->latest()->limit(5)->get();
        $recentCompanies = Company::latest()->limit(5)->get();
        $recentAuditLogs = AuditLog::with('user')->latest()->limit(8)->get();

        return view('dashboard.admin.index', compact('stats', 'recentUsers', 'recentCompanies', 'recentAuditLogs'));
    }
}
