<?php

namespace App\Events;

use App\Models\Dispute;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeEscalated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Dispute $dispute,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('government'),
            new Channel("company.{$this->dispute->company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dispute.escalated';
    }
}
