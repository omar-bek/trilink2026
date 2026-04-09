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
    use LocalizesNotification;

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
        $number   = $this->contract->contract_number;
        $decision = $this->localisedDecision($notifiable);

        return $this->baseMail($notifiable, 'notifications.contract.amendment_decided.subject', [
                'number'   => $number,
                'decision' => $decision,
            ])
            ->line($this->t($notifiable, 'notifications.contract.amendment_decided.line1', [
                'number'   => $number,
                'decision' => $decision,
            ]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => $this->decision === 'approved' ? 'success' : 'error',
            'title'       => $this->t($notifiable, 'notifications.contract.amendment_decided.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.amendment_decided.message', [
                'number'   => $this->contract->contract_number,
                'decision' => $this->localisedDecision($notifiable),
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }

    private function localisedDecision(object $notifiable): string
    {
        $key = 'notifications.contract.amendment_decided.verb_' . $this->decision;
        $value = trans($key, [], $this->localeFor($notifiable));
        return $value === $key ? $this->decision : $value;
    }
}
