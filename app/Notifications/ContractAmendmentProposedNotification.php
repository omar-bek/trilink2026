<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\ContractAmendment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when one party proposes a clause amendment so the other party
 * is alerted that there is a wording change waiting for their decision.
 * Without this the counter-party only saw the proposal next time they
 * happened to open the contract page — which is the second half of the
 * notification gap the user reported.
 */
class ContractAmendmentProposedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly ContractAmendment $amendment,
        private readonly string $proposerName,
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
        $kindLabel = ($changes['kind'] ?? 'modify') === 'add' ? 'add a new clause to' : 'modify a clause in';

        return (new MailMessage)
            ->subject("Contract {$this->contract->contract_number} — Amendment Proposed")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("{$this->proposerName} has proposed to {$kindLabel} \"{$section}\" of contract {$this->contract->contract_number}.")
            ->line('The proposed wording is awaiting your approval. Until either party approves or rejects it, the contract cannot be signed.')
            ->action('Review Amendment', route('dashboard.contracts.show', ['id' => $this->contract->id]));
    }

    public function toArray(object $notifiable): array
    {
        $section = ($this->amendment->changes ?? [])['section_title'] ?? '—';
        return [
            'type'        => 'warning',
            'title'       => 'Amendment Proposed',
            'message'     => "{$this->proposerName} proposed a change to \"{$section}\" on contract {$this->contract->contract_number}",
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
