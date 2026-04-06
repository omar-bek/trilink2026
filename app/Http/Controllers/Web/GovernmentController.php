<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\Payment;
use Illuminate\View\View;

/**
 * Government console — escalated disputes queue + market intelligence rollups.
 *
 * Government users see ALL escalated disputes across companies (no tenancy
 * scope) and high-level market metrics. Resolution actions live in the
 * existing DisputeController::resolve guarded by `web.role:government,admin`.
 */
class GovernmentController extends Controller
{
    use FormatsForViews;

    public function index(): View
    {
        $stats = [
            'escalated'    => Dispute::where('escalated_to_government', true)->whereNotIn('status', ['resolved'])->count(),
            'resolved'     => Dispute::where('status', 'resolved')->count(),
            'companies'    => Company::count(),
            'gmv'          => (float) Contract::where('status', 'active')->sum('total_amount'),
            'vat_collected' => (float) Payment::where('status', 'completed')->sum('vat_amount'),
        ];

        $escalated = Dispute::with(['contract', 'company', 'againstCompany', 'raisedByUser'])
            ->where('escalated_to_government', true)
            ->whereNotIn('status', ['resolved'])
            ->latest()
            ->get();

        return view('dashboard.gov.index', compact('stats', 'escalated'));
    }
}
