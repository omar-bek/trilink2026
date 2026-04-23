<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Team activity log for the company manager. Scopes the shared audit_logs
 * table to the manager's own company so they can see what every member of
 * their team did on the platform — who changed which RFQ, who approved
 * which bid, who signed which contract, etc. Read-only; entries are
 * append-only and tamper-evident via the hash chain (same integrity
 * guarantees as the admin view).
 */
class TeamActivityController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) auth()->user()->company_id;
        abort_unless($companyId > 0, 403);

        $filters = $this->collectFilters($request);

        $logs = $this->baseQuery($companyId, $filters)
            ->with('user')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = AuditAction::cases();
        $resources = AuditLog::query()
            ->where('company_id', $companyId)
            ->select('resource_type')
            ->distinct()
            ->pluck('resource_type')
            ->filter()
            ->values();

        // Team roster for the user-filter dropdown. Scoped to this company
        // so a manager can't enumerate users from other companies via the
        // filter.
        $teamMembers = User::query()
            ->where('company_id', $companyId)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role']);

        return view('dashboard.team-activity.index', array_merge(
            compact('logs', 'actions', 'resources', 'teamMembers'),
            $filters,
        ));
    }

    public function show(int $id): View
    {
        $companyId = (int) auth()->user()->company_id;
        // Use findOrFail on the company-scoped query so a manager can
        // never pull up an audit row that belongs to a different
        // company by guessing an ID.
        $log = AuditLog::query()
            ->where('company_id', $companyId)
            ->with('user', 'company')
            ->findOrFail($id);

        return view('dashboard.team-activity.show', compact('log'));
    }

    /**
     * Streamed CSV of the filtered team activity. Same filters as the
     * index so the export always mirrors what's on screen.
     */
    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) auth()->user()->company_id;
        abort_unless($companyId > 0, 403);

        $filters = $this->collectFilters($request);

        $query = $this->baseQuery($companyId, $filters)
            ->with('user')
            ->latest();

        $filename = 'team-activity-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'When', 'User', 'Email', 'Role', 'Action',
                'Resource Type', 'Resource ID', 'IP Address', 'Status',
            ]);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at?->toDateTimeString() ?? '',
                        trim(($log->user?->first_name ?? '').' '.($log->user?->last_name ?? '')),
                        $log->user?->email ?? '',
                        $log->user?->role instanceof \BackedEnum ? $log->user->role->value : ($log->user?->role ?? ''),
                        $log->action instanceof \BackedEnum ? $log->action->value : $log->action,
                        $log->resource_type,
                        $log->resource_id,
                        $log->ip_address,
                        $log->status,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function collectFilters(Request $request): array
    {
        return [
            'action' => $request->query('action'),
            'resource' => $request->query('resource'),
            'userId' => $request->query('user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * @param  array<string,mixed>  $f
     */
    private function baseQuery(int $companyId, array $f)
    {
        return AuditLog::query()
            ->where('company_id', $companyId)
            ->when($f['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($f['resource'], fn ($q, $v) => $q->where('resource_type', $v))
            ->when($f['userId'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
