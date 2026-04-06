<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['contract_id', 'status', 'per_page']);
        $user = auth()->user();

        if (!$user->isAdmin()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $payment = $this->service->find($id);
        return $payment ? $this->success($payment) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'recipient_company_id' => 'required|exists:companies,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'milestone' => 'nullable|string',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['buyer_id'] = auth()->id();

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'milestone' => 'nullable|string',
        ]);

        $payment = $this->service->update($id, $data);
        return $payment ? $this->success($payment) : $this->notFound();
    }

    public function approve(int $id): JsonResponse
    {
        $payment = $this->service->approve($id, auth()->id());
        return $payment ? $this->success($payment, 'Payment approved') : $this->error('Cannot approve this payment', 422);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string']);

        $payment = $this->service->reject($id, $data['reason']);
        return $payment ? $this->success($payment, 'Payment rejected') : $this->error('Cannot reject this payment', 422);
    }

    public function process(Request $request, int $id): JsonResponse
    {
        $gateway = $request->input('gateway', 'stripe');
        $result = $this->service->process($id, $gateway);

        if (is_string($result)) {
            return $this->error($result, 422);
        }

        return $this->success($result, 'Payment processing initiated');
    }
}
