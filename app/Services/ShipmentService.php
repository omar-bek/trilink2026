<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Events\ShipmentDelivered;
use App\Events\ShipmentLocationUpdated;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Notifications\ShipmentStatusNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

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

        $oldStatus = $shipment->status?->value;
        $shipment->update($data);
        $fresh = $shipment->fresh(['contract.buyerCompany', 'company']);

        // Status transitions trigger a notification to every party of the
        // underlying contract so buyers + suppliers stay in the loop.
        $newStatus = $fresh?->status?->value;
        if ($fresh && $newStatus && $newStatus !== $oldStatus) {
            $this->notifyContractParties($fresh, $newStatus);
            $this->maybeFireDelivered($fresh, $oldStatus, $newStatus);
        }

        return $fresh;
    }

    public function addTrackingEvent(int $shipmentId, array $data): ?TrackingEvent
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) return null;

        $oldStatus = $shipment->status?->value;

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
        $fresh = $shipment->fresh(['contract.buyerCompany', 'company']);
        ShipmentLocationUpdated::dispatch($fresh);

        // Notify all contract parties on real status changes (not on
        // same-status progress pings like new GPS points).
        if ($fresh && $data['status'] !== $oldStatus) {
            $this->notifyContractParties($fresh, $data['status']);
            $this->maybeFireDelivered($fresh, $oldStatus, $data['status']);
        }

        return $event;
    }

    /**
     * Phase 3 / Sprint 12 / task 3.7 — emit the ShipmentDelivered event the
     * single time a shipment crosses the delivered threshold so the escrow
     * auto-release listener can drain on_delivery milestones. We deliberately
     * fire this once per delivery (not on subsequent updates that re-touch
     * the same status) to keep the listener idempotent without extra guards.
     */
    private function maybeFireDelivered(Shipment $shipment, ?string $oldStatus, ?string $newStatus): void
    {
        if ($newStatus === ShipmentStatus::DELIVERED->value && $oldStatus !== ShipmentStatus::DELIVERED->value) {
            ShipmentDelivered::dispatch($shipment);
        }
    }

    /**
     * Send a ShipmentStatusNotification to every user whose company is a
     * party in the related contract (buyer + all supplier-side parties).
     * Degrades silently if the shipment has no contract attached.
     */
    private function notifyContractParties(Shipment $shipment, string $newStatus): void
    {
        $contract = $shipment->contract;
        if (!$contract) {
            return;
        }

        $partyIds = collect($contract->parties ?? [])
            ->pluck('company_id')
            ->push($contract->buyer_company_id)
            ->filter()
            ->unique()
            ->values();

        if ($partyIds->isEmpty()) {
            return;
        }

        // Notify every user in each party company (not just the primary
        // contact) so procurement + logistics teammates both see the update.
        $users = \App\Models\User::whereIn('company_id', $partyIds)->active()->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new ShipmentStatusNotification($shipment, $newStatus));
        }
    }

    public function getTrackingEvents(int $shipmentId): Collection
    {
        return TrackingEvent::where('shipment_id', $shipmentId)
            ->orderByDesc('event_at')
            ->get();
    }
}
