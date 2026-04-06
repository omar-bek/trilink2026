<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContractSigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Contract $contract,
        public readonly int $signerCompanyId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("company.{$this->contract->buyer_company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'contract.signed';
    }
}
