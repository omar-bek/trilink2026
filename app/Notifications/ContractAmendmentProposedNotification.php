<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Notifications\Concerns\LocalizesNotification;
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
    use LocalizesNotification;

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
        $number = $this->contract->contract_number;

        return $this->baseMail($notifiable, 'notifications.contract.amendment_proposed.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.amendment_proposed.line1', ['number' => $number]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_review'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => $this->t($notifiable, 'notifications.contract.amendment_proposed.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.amendment_proposed.message', [
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
