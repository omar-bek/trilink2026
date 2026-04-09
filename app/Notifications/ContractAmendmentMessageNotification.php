<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractAmendmentMessage;
use App\Notifications\Concerns\LocalizesNotification;
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
    use LocalizesNotification;

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
        $number  = $this->contract->contract_number;
        $excerpt = mb_substr((string) $this->message->body, 0, 140);

        return $this->baseMail($notifiable, 'notifications.contract.amendment_message.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.amendment_message.line1', ['number' => $number]))
            ->line('"' . $excerpt . '"')
            ->action(
                $this->t($notifiable, 'notifications.common.action_open'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'info',
            'title'       => $this->t($notifiable, 'notifications.contract.amendment_message.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.amendment_message.message', [
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
