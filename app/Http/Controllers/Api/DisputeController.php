<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['contract_id', 'status', 'escalated', 'assigned_to', 'per_page']);
        $user = auth()->user();

        if (!$user->isAdmin() && !in_array($user->role->value, ['government'])) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $dispute = $this->service->find($id);
        return $dispute ? $this->success($dispute) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'against_company_id' => 'required|exists:companies,id',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $data['company_id'] = auth()->user()->company_id;
        $data['raised_by'] = auth()->id();

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|string',
            'assigned_to' => 'nullable|exists:users,id',
            'description' => 'sometimes|string',
        ]);

        $dispute = $this->service->update($id, $data);
        return $dispute ? $this->success($dispute) : $this->notFound();
    }

    public function escalate(int $id): JsonResponse
    {
        $dispute = $this->service->escalate($id);
        return $dispute
            ? $this->success($dispute, 'Dispute escalated to government')
            : $this->error('Cannot escalate this dispute', 422);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['resolution' => 'required|string']);

        $dispute = $this->service->resolve($id, $data['resolution']);
        return $dispute ? $this->success($dispute, 'Dispute resolved') : $this->notFound();
    }
}
