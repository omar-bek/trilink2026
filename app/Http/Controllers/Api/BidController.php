<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Services\BidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function __construct(
        private readonly BidService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['rfq_id', 'status', 'per_page']);
        $user = auth()->user();

        if (!$user->isAdmin()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $bid = $this->service->find($id);
        return $bid ? $this->success($bid) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rfq_id' => 'required|exists:rfqs,id',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'delivery_time_days' => 'nullable|integer|min:1',
            'payment_terms' => 'nullable|string',
            'payment_schedule' => 'nullable|array',
            'items' => 'nullable|array',
            'validity_date' => 'nullable|date|after:now',
            'is_anonymous' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['provider_id'] = auth()->id();
        $data['status'] = 'submitted';

        $result = $this->service->create($data);

        if (is_string($result)) {
            return $this->error($result, 422);
        }

        return $this->created($result);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'price' => 'sometimes|numeric|min:0',
            'delivery_time_days' => 'nullable|integer|min:1',
            'payment_terms' => 'nullable|string',
            'payment_schedule' => 'nullable|array',
            'items' => 'nullable|array',
            'validity_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $bid = $this->service->update($id, $data);
        return $bid ? $this->success($bid) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'Bid deleted')
            : $this->notFound();
    }

    public function evaluate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'price_score' => 'required|numeric|min:0|max:100',
            'delivery_score' => 'required|numeric|min:0|max:100',
            'terms_score' => 'required|numeric|min:0|max:100',
            'history_score' => 'required|numeric|min:0|max:100',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'risk_level' => 'nullable|string',
        ]);

        $bid = $this->service->evaluate($id, $data);
        return $bid ? $this->success($bid, 'Bid evaluated') : $this->notFound();
    }

    public function withdraw(int $id): JsonResponse
    {
        $bid = $this->service->withdraw($id);
        return $bid ? $this->success($bid, 'Bid withdrawn') : $this->error('Cannot withdraw this bid', 422);
    }

    public function enableAnonymity(int $id): JsonResponse
    {
        $bid = Bid::find($id);
        if (!$bid) return $this->notFound();

        $bid->update(['is_anonymous' => true]);
        return $this->success($bid->fresh(), 'Anonymity enabled');
    }

    public function revealIdentity(int $id): JsonResponse
    {
        $bid = Bid::find($id);
        if (!$bid) return $this->notFound();

        $bid->update(['is_anonymous' => false]);
        return $this->success($bid->fresh(), 'Identity revealed');
    }
}
