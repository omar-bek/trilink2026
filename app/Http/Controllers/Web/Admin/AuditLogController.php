<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit log viewer. Read-only — log entries are append-only and can never
 * be edited or deleted from the UI; that protects the integrity of the
 * trail and is a hard rule for compliance.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->collectFilters($request);

        $logs = $this->baseQuery($filters)
            ->with('user')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = AuditAction::cases();
        $resources = AuditLog::query()->select('resource_type')->distinct()->pluck('resource_type')->filter()->values();

        return view('dashboard.admin.audit.index', array_merge(
            compact('logs', 'actions', 'resources'),
            $filters,
        ));
    }

    public function show(int $id): View
    {
        $log = AuditLog::with('user', 'company')->findOrFail($id);

        return view('dashboard.admin.audit.show', compact('log'));
    }

    /**
     * Streamed CSV export of the currently-filtered audit log rows. Uses
     * the same filter collection as index() so the export always matches
     * what the admin is looking at on screen.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->collectFilters($request);

        $query = $this->baseQuery($filters)
            ->with('user')
            ->latest();

        $filename = 'audit-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($out, [
                'ID',
                'When',
                'User ID',
                'User',
                'Email',
                'Action',
                'Resource Type',
                'Resource ID',
                'IP Address',
                'User Agent',
                'Status',
                'Before (JSON)',
                'After (JSON)',
            ]);

            // Stream in chunks so the export doesn't load the entire table
            // into memory for a year-long date range.
            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at?->toDateTimeString() ?? '',
                        $log->user_id,
                        trim(($log->user?->first_name ?? '').' '.($log->user?->last_name ?? '')),
                        $log->user?->email ?? '',
                        $log->action instanceof \BackedEnum ? $log->action->value : $log->action,
                        $log->resource_type,
                        $log->resource_id,
                        $log->ip_address,
                        $log->user_agent,
                        $log->status,
                        $log->before ? json_encode($log->before, JSON_UNESCAPED_UNICODE) : '',
                        $log->after ? json_encode($log->after, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Shared filter collection so both index() and export() end up applying
     * exactly the same predicates.
     *
     * @return array<string,mixed>
     */
    private function collectFilters(Request $request): array
    {
        return [
            'action' => $request->query('action'),
            'resource' => $request->query('resource'),
            'userId' => $request->query('user_id'),
            'ip' => trim((string) $request->query('ip', '')),
            'userAgent' => trim((string) $request->query('ua', '')),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * Build the base query with all filters applied.
     *
     * @param  array<string,mixed>  $f
     */
    private function baseQuery(array $f)
    {
        return AuditLog::query()
            ->when($f['action'], fn ($q, $v) => $q->where('action', $v))
            ->when($f['resource'], fn ($q, $v) => $q->where('resource_type', $v))
            ->when($f['userId'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['ip'] !== '', fn ($q) => $q->where('ip_address', 'like', '%'.$f['ip'].'%'))
            ->when($f['userAgent'] !== '', fn ($q) => $q->where('user_agent', 'like', '%'.$f['userAgent'].'%'))
            ->when($f['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
