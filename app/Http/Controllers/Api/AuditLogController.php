<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->resource_type, fn ($q, $v) => $q->where('resource_type', $v))
            ->when($request->from, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->with('user')
            ->latest()
            ->paginate($request->input('per_page', 50));

        return $this->success($logs);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => 'nullable|string',
            'filters' => 'nullable|array',
        ]);

        $logs = AuditLog::query()
            ->when($data['query'] ?? null, fn ($q, $v) => $q->search($v, ['resource_type', 'action']))
            ->when($data['filters']['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($data['filters']['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->with('user')
            ->latest()
            ->paginate(50);

        return $this->success($logs);
    }

    public function export(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->from, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->with('user')
            ->get();

        return $this->success($logs, 'Export data ready');
    }
}
