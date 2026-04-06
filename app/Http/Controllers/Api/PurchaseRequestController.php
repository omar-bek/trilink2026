<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category_id', 'per_page']);
        $user = auth()->user();

        if (!$user->isAdmin()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $pr = $this->service->find($id);
        return $pr ? $this->success($pr) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'required|string',
            'items.*.specifications' => 'nullable|string',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'delivery_location' => 'nullable|string',
            'required_date' => 'nullable|date|after:today',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['buyer_id'] = auth()->id();
        $data['status'] = 'draft';

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|string',
            'items' => 'sometimes|array|min:1',
            'budget' => 'nullable|numeric|min:0',
            'delivery_location' => 'nullable|string',
            'required_date' => 'nullable|date',
        ]);

        $pr = $this->service->update($id, $data);
        return $pr ? $this->success($pr) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'Purchase request deleted')
            : $this->notFound();
    }

    public function approve(int $id): JsonResponse
    {
        $pr = $this->service->approve($id, auth()->id());

        if (!$pr) {
            return $this->error('Cannot approve this purchase request', 422);
        }

        return $this->success($pr, 'Purchase request approved');
    }
}
