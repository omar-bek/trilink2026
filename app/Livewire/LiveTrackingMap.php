<?php

namespace App\Livewire;

use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Real-time shipment tracking map.
 *
 * Subscribes to the broadcast channel `shipment.{id}` via Echo and refreshes
 * its data when a `ShipmentLocationUpdated` event arrives. Falls back to
 * polling every 60s in case Reverb is unavailable.
 *
 * Mounted via:  <livewire:live-tracking-map :shipment-id="$shipment->id" />
 */
class LiveTrackingMap extends Component
{
    public int $shipmentId;

    public function mount(int $shipmentId): void
    {
        $this->shipmentId = $shipmentId;
    }

    #[On('echo-private:shipment.{shipmentId},ShipmentLocationUpdated')]
    public function onLocationUpdated(): void
    {
        // Computed properties recompute on next render — nothing to do but
        // notify Livewire that state changed.
        $this->dispatch('$refresh');
    }

    #[Computed]
    public function shipment(): Shipment
    {
        return Shipment::with(['contract', 'logisticsCompany'])->findOrFail($this->shipmentId);
    }

    /**
     * @return array<int, array{lat:float,lng:float,description:?string,at:string}>
     */
    #[Computed]
    public function trail(): array
    {
        return TrackingEvent::where('shipment_id', $this->shipmentId)
            ->orderBy('event_at')
            ->get()
            ->map(function (TrackingEvent $e) {
                $loc = $e->location ?? [];

                return [
                    'lat' => (float) ($loc['lat'] ?? 0),
                    'lng' => (float) ($loc['lng'] ?? 0),
                    'description' => $e->description,
                    'at' => $e->event_at?->toIso8601String() ?? '',
                ];
            })
            ->filter(fn ($p) => $p['lat'] !== 0.0 || $p['lng'] !== 0.0)
            ->values()
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.live-tracking-map');
    }
}
