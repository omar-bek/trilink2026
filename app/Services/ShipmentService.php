<?php

namespace App\Services;

use App\Events\ShipmentLocationUpdated;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ShipmentService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Shipment::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['contract_id'] ?? null, fn ($q, $v) => $q->where('contract_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['logistics_company_id'] ?? null, fn ($q, $v) => $q->where('logistics_company_id', $v))
            ->with(['contract', 'company', 'logisticsCompany'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?Shipment
    {
        return Shipment::with(['contract', 'company', 'logisticsCompany', 'trackingEvents'])->find($id);
    }

    public function create(array $data): Shipment
    {
        return Shipment::create($data)->load(['contract', 'company']);
    }

    public function update(int $id, array $data): ?Shipment
    {
        $shipment = Shipment::find($id);
        if (!$shipment) return null;

        $shipment->update($data);
        return $shipment->fresh(['contract', 'company']);
    }

    public function addTrackingEvent(int $shipmentId, array $data): ?TrackingEvent
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) return null;

        $event = TrackingEvent::create([
            'shipment_id' => $shipmentId,
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'event_at' => $data['event_at'] ?? now(),
        ]);

        $shipment->update([
            'status' => $data['status'],
            'current_location' => $data['location'] ?? $shipment->current_location,
        ]);

        // Real-time broadcast for live tracking maps subscribed via Reverb.
        ShipmentLocationUpdated::dispatch($shipment->fresh());

        return $event;
    }

    public function getTrackingEvents(int $shipmentId): Collection
    {
        return TrackingEvent::where('shipment_id', $shipmentId)
            ->orderByDesc('event_at')
            ->get();
    }
}
