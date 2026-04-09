<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\ContractAmendment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when a clause amendment is APPROVED or REJECTED. The proposer
 * (and the rest of the contract parties) get a notification with the
 * outcome so they don't have to keep refreshing the contract page to
 * see whether the counter-party acted.
 *
 * `$decision` is one of: 'approved' | 'rejected'.
 */
class ContractAmendmentDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly ContractAmendment $amendment,
        private readonly string $decision,
        private readonly string $deciderName,
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
        $changes = $this->amendment->changes ?? [];
        $section = $changes['section_title'] ?? '—';
        $verb = $this->decision === 'approved' ? 'approved' : 'rejected';

        return (new MailMessage)
            ->subject("Contract {$this->contract->contract_number} — Amendment {$verb}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("{$this->deciderName} has {$verb} the proposed amendment to \"{$section}\" on contract {$this->contract->contract_number}.")
            ->action('View Contract', route('dashboard.contracts.show', ['id' => $this->contract->id]));
    }

    public function toArray(object $notifiable): array
    {
        $section = ($this->amendment->changes ?? [])['section_title'] ?? '—';
        return [
            'type'        => $this->decision === 'approved' ? 'success' : 'error',
            'title'       => $this->decision === 'approved' ? 'Amendment Approved' : 'Amendment Rejected',
            'message'     => "{$this->deciderName} {$this->decision} the change to \"{$section}\" on contract {$this->contract->contract_number}",
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
