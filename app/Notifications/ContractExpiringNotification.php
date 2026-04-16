<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Distinct from ContractRenewalAlert — this fires when a contract is
 * approaching its end_date and is NOT marked for auto-renewal. The
 * recipient is being told "this is going to wind down" as opposed to
 * "decide whether to renew".
 *
 * Used by SendContractExpiryRemindersCommand on the 30 / 7 / 1 day
 * thresholds before end_date.
 */
class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly int $daysUntilExpiry,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->contract->contract_number;
        $title = (string) $this->contract->title;
        $endDate = $this->contract->end_date?->toDateString() ?? '—';

        return $this->baseMail($notifiable, 'notifications.contract.expiring.subject', [
            'number' => $number,
            'days' => $this->daysUntilExpiry,
        ])
            ->line($this->t($notifiable, 'notifications.contract.expiring.line1', [
                'number' => $number,
                'title' => $title,
            ]))
            ->line($this->t($notifiable, 'notifications.contract.expiring.line_date', ['date' => $endDate]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.contract.expiring.title'),
            'message' => $this->t($notifiable, 'notifications.contract.expiring.message', [
                'number' => $this->contract->contract_number,
                'days' => $this->daysUntilExpiry,
            ]),
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
