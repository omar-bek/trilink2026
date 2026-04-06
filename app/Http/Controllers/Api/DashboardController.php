<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user->isAdmin() ? null : $user->company_id;

        return $this->success($this->analyticsService->dashboard($companyId));
    }
}
