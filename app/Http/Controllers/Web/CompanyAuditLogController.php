<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only view of the tenant's own audit trail. The admin namespace
 * has a global audit-log browser; this controller scopes the same data
 * to the manager's company_id so a tenant can see what their own team
 * has been up to (who changed what, approved what, invited whom) without
 * exposing other companies' activity.
 */
class CompanyAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $company = $user?->company;
        abort_unless($company && $user->can('viewAudit', $company), 403);

        $actorId = $request->integer('actor');
        $action = $request->string('action')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $query = AuditLog::query()
            ->where('company_id', $company->id)
            ->latest('created_at');

        if ($actorId) {
            $query->where('user_id', $actorId);
        }
        if ($action !== '') {
            $query->where('action', $action);
        }
        if ($from) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $logs = $query->with('user')->paginate(50)->withQueryString();

        return view('dashboard.settings.audit.index', [
            'logs' => $logs,
            'filters' => [
                'actor' => $actorId,
                'action' => $action,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }
}
