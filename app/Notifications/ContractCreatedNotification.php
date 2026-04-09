<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired the moment a contract is materialised — typically by
 * {@see \App\Services\ContractService::createFromBid()} after the
 * buyer accepts a bid. Recipients are every user belonging to a
 * party of the contract (buyer + supplier sides) so both teams know
 * the agreement is now waiting for them to sign.
 *
 * Without this notification the contract appeared in the listings
 * silently and users had to refresh the page or stumble onto it via
 * the bid history — which is exactly the gap the user reported.
 */
class ContractCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
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
        return (new MailMessage)
            ->subject("New Contract {$this->contract->contract_number} — Awaiting Signature")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("A new contract has been generated and is awaiting your signature.")
            ->line("**Contract:** {$this->contract->contract_number}")
            ->line("**Title:** {$this->contract->title}")
            ->line('**Total Value:** ' . ($this->contract->currency ?? 'AED') . ' ' . number_format((float) $this->contract->total_amount, 2))
            ->action('Review & Sign', route('dashboard.contracts.show', ['id' => $this->contract->id]))
            ->line('Both parties must agree on every clause before signing — propose changes from the contract page if any wording needs to be reworked.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'info',
            'title'       => 'New Contract Created',
            'message'     => "Contract {$this->contract->contract_number} is awaiting your signature",
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
