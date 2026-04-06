<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnership
{
    public function handle(Request $request, Closure $next, string $model, string $companyField = 'company_id'): Response
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        $modelClass = "App\\Models\\{$model}";
        $routeParam = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $model));
        $resourceId = $request->route($routeParam) ?? $request->route('id');

        if ($resourceId) {
            $resource = $modelClass::find($resourceId);
            if (!$resource) {
                return response()->json(['message' => 'Resource not found'], 404);
            }

            if ($resource->{$companyField} !== $user->company_id) {
                return response()->json(['message' => 'Unauthorized access to this resource'], 403);
            }
        }

        return $next($request);
    }
}
