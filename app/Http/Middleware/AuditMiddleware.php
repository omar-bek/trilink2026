<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    private const METHOD_ACTION_MAP = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
        'GET' => 'view',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*') && auth()->check()) {
            $this->logRequest($request, $response);
        }

        return $response;
    }

    private function logRequest(Request $request, Response $response): void
    {
        $action = self::METHOD_ACTION_MAP[$request->method()] ?? 'view';
        $segments = $request->segments();
        $resourceType = $segments[1] ?? 'unknown';
        $resourceId = $segments[2] ?? null;

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'company_id' => auth()->user()?->company_id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => is_numeric($resourceId) ? $resourceId : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => $response->isSuccessful() ? 'success' : 'failure',
            ]);
        } catch (\Throwable) {
            // Don't let audit failures break the request
        }
    }
}
