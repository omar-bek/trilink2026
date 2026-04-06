<?php

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Bid $bid,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("company.{$this->bid->rfq->company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bid.submitted';
    }
}
