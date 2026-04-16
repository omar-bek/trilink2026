<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $service,
    ) {}

    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user->isAdmin() ? null : $user->company_id;

        return $this->success($this->service->dashboard($companyId));
    }

    public function companyStats(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id', auth()->user()->company_id);

        return $this->success($this->service->companyStats($companyId));
    }

    public function paymentMetrics(Request $request): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user->isAdmin() ? $request->input('company_id') : $user->company_id;

        return $this->success($this->service->paymentMetrics($companyId));
    }

    public function government(): JsonResponse
    {
        return $this->success($this->service->dashboard(null));
    }
}
