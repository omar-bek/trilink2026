<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['contract_id', 'status', 'logistics_company_id', 'per_page']);
        $user = auth()->user();

        if (!$user->isAdmin()) {
            $filters['company_id'] = $user->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $shipment = $this->service->find($id);
        return $shipment ? $this->success($shipment) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'logistics_company_id' => 'nullable|exists:companies,id',
            'origin' => 'nullable|array',
            'destination' => 'nullable|array',
            'estimated_delivery' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $data['company_id'] = auth()->user()->company_id;

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|string',
            'logistics_company_id' => 'nullable|exists:companies,id',
            'current_location' => 'nullable|array',
            'inspection_status' => 'nullable|string',
            'customs_clearance_status' => 'nullable|string',
            'customs_documents' => 'nullable|array',
            'estimated_delivery' => 'nullable|date',
            'actual_delivery' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $shipment = $this->service->update($id, $data);
        return $shipment ? $this->success($shipment) : $this->notFound();
    }

    public function track(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string',
            'description' => 'nullable|string',
            'location' => 'nullable|array',
            'event_at' => 'nullable|date',
        ]);

        $event = $this->service->addTrackingEvent($id, $data);
        return $event ? $this->success($event, 'Tracking event added') : $this->notFound();
    }
}

