<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 3 — fired by EscrowService whenever an escrow account changes
 * state. Both buyer and supplier teams receive it because both sides have
 * money on the line. The frontend NotificationFormatter routes the click
 * back to the contract show page where the escrow panel lives.
 */
class EscrowEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $contractId,
        public readonly string $action,
        public readonly ?float $amount,
        public readonly string $currency,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = match ($this->action) {
            'activated' => 'An escrow account has been activated for your contract.',
            'deposit'   => "A deposit of {$this->currency} " . number_format((float) $this->amount, 2) . ' has been received in escrow.',
            'release'   => "An escrow release of {$this->currency} " . number_format((float) $this->amount, 2) . ' has been wired to the supplier.',
            'refund'    => "An escrow refund of {$this->currency} " . number_format((float) $this->amount, 2) . ' has been returned to the buyer.',
            default     => 'Your contract escrow status has changed.',
        };

        return (new MailMessage)
            ->subject('Escrow ' . ucfirst($this->action))
            ->line($line)
            ->action('View Contract', config('app.frontend_url') . "/contracts/{$this->contractId}");
    }

    public function toArray(object $notifiable): array
    {
        $type = match ($this->action) {
            'release', 'deposit', 'activated' => 'success',
            'refund'                          => 'warning',
            default                           => 'info',
        };

        return [
            'type'        => $type,
            'title'       => 'Escrow ' . ucfirst($this->action),
            'message'     => match ($this->action) {
                'activated' => 'Escrow account activated for your contract',
                'deposit'   => "Deposit received: {$this->currency} " . number_format((float) $this->amount, 2),
                'release'   => "Funds released: {$this->currency} " . number_format((float) $this->amount, 2),
                'refund'    => "Funds refunded: {$this->currency} " . number_format((float) $this->amount, 2),
                default     => 'Escrow status updated',
            },
            'entity_type' => 'contract',
            'entity_id'   => $this->contractId,
        ];
    }
}
