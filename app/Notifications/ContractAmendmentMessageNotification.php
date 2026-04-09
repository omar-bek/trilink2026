<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractAmendmentMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when one party posts a discussion message on a clause
 * amendment thread. Lets the other party know there is a new message
 * waiting in the negotiation page without having to keep the contract
 * tab open.
 */
class ContractAmendmentMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly ContractAmendment $amendment,
        private readonly ContractAmendmentMessage $message,
        private readonly string $senderName,
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
        $section = ($this->amendment->changes ?? [])['section_title'] ?? '—';
        $excerpt = mb_substr($this->message->body, 0, 140);

        return (new MailMessage)
            ->subject("Contract {$this->contract->contract_number} — New negotiation message")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("{$this->senderName} posted a new message on the amendment for \"{$section}\".")
            ->line("\"{$excerpt}\"")
            ->action('Open Negotiation', route('dashboard.contracts.show', ['id' => $this->contract->id]));
    }

    public function toArray(object $notifiable): array
    {
        $section = ($this->amendment->changes ?? [])['section_title'] ?? '—';
        return [
            'type'        => 'info',
            'title'       => 'Negotiation message',
            'message'     => "{$this->senderName} replied on the \"{$section}\" amendment of contract {$this->contract->contract_number}",
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
