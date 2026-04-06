<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("shipment.{$this->shipment->id}"),
            new Channel("company.{$this->shipment->company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.updated';
    }
}
