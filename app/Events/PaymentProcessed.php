<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("company.{$this->payment->company_id}"),
            new Channel("company.{$this->payment->recipient_company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.processed';
    }
}
