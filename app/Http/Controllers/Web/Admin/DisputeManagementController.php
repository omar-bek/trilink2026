<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DisputeManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = Dispute::with(['contract', 'company', 'againstCompany', 'raisedByUser', 'assignedTo'])
            ->orderByRaw("FIELD(status, 'escalated', 'open', 'under_review', 'resolved')")
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->query('overdue')) {
            $query->where('sla_due_date', '<', now())->where('status', '!=', 'resolved');
        }

        $disputes = $query->paginate(20)->withQueryString();

        $stats = [
            'total'     => Dispute::count(),
            'open'      => Dispute::where('status', 'open')->count(),
            'escalated' => Dispute::where('status', 'escalated')->count(),
            'overdue'   => Dispute::where('sla_due_date', '<', now())->where('status', '!=', 'resolved')->count(),
            'resolved'  => Dispute::where('status', 'resolved')->count(),
            'avg_resolution_days' => round((float) Dispute::where('status', 'resolved')
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(DATEDIFF(resolved_at, created_at)) as d')
                ->value('d'), 1),
        ];

        $admins = User::where('role', 'admin')->orWhere('role', 'government')->select('id', 'first_name', 'last_name')->get();

        return view('dashboard.admin.disputes.index', compact('disputes', 'stats', 'admins'));
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        Dispute::where('id', $id)->update([
            'assigned_to' => $data['assigned_to'],
            'status'      => 'under_review',
        ]);

        return back()->with('status', __('admin.disputes.assigned'));
    }
}
