<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Shipment $shipment)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('shipment.' . $this->shipment->id)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $loc = $this->shipment->current_location ?? [];

        return [
            'shipment_id' => $this->shipment->id,
            'tracking'    => $this->shipment->tracking_number,
            'status'      => $this->shipment->status?->value,
            'lat'         => $loc['lat'] ?? null,
            'lng'         => $loc['lng'] ?? null,
            'at'          => now()->toIso8601String(),
        ];
    }
}
