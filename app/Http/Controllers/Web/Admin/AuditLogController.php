<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Audit log viewer. Read-only — log entries are append-only and can never
 * be edited or deleted from the UI; that protects the integrity of the
 * trail and is a hard rule for compliance.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action   = $request->query('action');
        $resource = $request->query('resource');
        $userId   = $request->query('user_id');
        $from     = $request->query('from');
        $to       = $request->query('to');

        $logs = AuditLog::query()
            ->with('user')
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($resource, fn ($q) => $q->where('resource_type', $resource))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions   = AuditAction::cases();
        $resources = AuditLog::query()->select('resource_type')->distinct()->pluck('resource_type')->filter()->values();

        return view('dashboard.admin.audit.index', compact('logs', 'actions', 'resources', 'action', 'resource', 'userId', 'from', 'to'));
    }

    public function show(int $id): View
    {
        $log = AuditLog::with('user', 'company')->findOrFail($id);

        return view('dashboard.admin.audit.show', compact('log'));
    }
}
