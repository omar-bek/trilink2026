<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Services\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    public function __construct(
        private readonly RfqService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'status', 'target_role', 'category_id', 'per_page']);
        $user = auth()->user();

        if (! $user->isAdmin() && ! $user->isGovernment()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function available(Request $request): JsonResponse
    {
        $user = auth()->user();

        $rfqs = Rfq::where('status', 'open')
            ->where('company_id', '!=', $user->company_id)
            ->where(function ($q) use ($user) {
                $q->whereNull('target_role')
                    ->orWhere('target_role', $user->role->value);
            })
            ->where(function ($q) use ($user) {
                $q->whereNull('target_company_ids')
                    ->orWhereJsonContains('target_company_ids', $user->company_id);
            })
            ->where(function ($q) {
                $q->whereNull('deadline')
                    ->orWhere('deadline', '>', now());
            })
            ->with(['company', 'category'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->success($rfqs);
    }

    public function show(int $id): JsonResponse
    {
        $rfq = $this->service->find($id);

        return $rfq ? $this->success($rfq) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'type' => 'required|string',
            'target_role' => 'nullable|string',
            'target_company_type' => 'nullable|string',
            'target_company_ids' => 'nullable|array',
            'items' => 'required|array|min:1',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'deadline' => 'nullable|date|after:now',
            'required_delivery_date' => 'nullable|date',
            'delivery_location' => 'nullable|string',
            'is_anonymous' => 'boolean',
            'category_id' => 'nullable|exists:categories,id',
            'attachments' => 'nullable|array',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['status'] = 'open';

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string',
            'target_role' => 'nullable|string',
            'target_company_type' => 'nullable|string',
            'status' => 'sometimes|string',
            'items' => 'sometimes|array|min:1',
            'budget' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
            'required_delivery_date' => 'nullable|date',
            'delivery_location' => 'nullable|string',
            'is_anonymous' => 'boolean',
            'attachments' => 'nullable|array',
        ]);

        $rfq = $this->service->update($id, $data);

        return $rfq ? $this->success($rfq) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'RFQ deleted')
            : $this->notFound();
    }

    public function getByPurchaseRequest(int $id): JsonResponse
    {
        return $this->success($this->service->getByPurchaseRequest($id));
    }

    public function compareBids(int $id): JsonResponse
    {
        $rfq = Rfq::with(['bids.company', 'bids.provider'])->find($id);
        if (! $rfq) {
            return $this->notFound();
        }

        $comparison = $rfq->bids->map(fn ($bid) => [
            'bid_id' => $bid->id,
            'company' => $bid->is_anonymous ? 'Anonymous' : $bid->company->name,
            'price' => $bid->price,
            'currency' => $bid->currency,
            'delivery_time_days' => $bid->delivery_time_days,
            'validity_date' => $bid->validity_date,
            'status' => $bid->status,
            'ai_score' => $bid->ai_score,
        ]);

        return $this->success($comparison);
    }

    public function enableAnonymity(int $id): JsonResponse
    {
        $rfq = Rfq::find($id);
        if (! $rfq) {
            return $this->notFound();
        }

        $rfq->update(['is_anonymous' => true]);

        return $this->success($rfq->fresh(), 'Anonymity enabled');
    }

    public function revealIdentity(int $id): JsonResponse
    {
        $rfq = Rfq::find($id);
        if (! $rfq) {
            return $this->notFound();
        }

        $rfq->update(['is_anonymous' => false]);

        return $this->success($rfq->fresh(), 'Identity revealed');
    }
}
