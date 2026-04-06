<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryRoutingController extends Controller
{
    public function __construct(
        private readonly CategoryRoutingService $service,
    ) {}

    public function match(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'company_type' => 'nullable|string',
        ]);

        $companies = $this->service->findMatchingCompanies(
            $request->category_id,
            $request->company_type
        );

        return $this->success($companies);
    }

    public function canView(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'category_id' => 'required|exists:categories,id',
        ]);

        $canView = $this->service->canCompanyViewPurchaseRequest(
            $request->company_id,
            $request->category_id
        );

        return $this->success(['can_view' => $canView]);
    }
}
