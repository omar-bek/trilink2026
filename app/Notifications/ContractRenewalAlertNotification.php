<?php

namespace App\Notifications;

use App\Console\Commands\SendContractRenewalAlertsCommand;
use App\Models\Contract;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by the daily {@see SendContractRenewalAlertsCommand}
 * when an active contract is approaching its end date. Lets the
 * procurement team plan a renewal (or wind-down) before the contract
 * silently expires.
 *
 * `$daysOut` is the bucket the contract fell into: 90 / 60 / 30. The
 * command picks the LARGEST bucket the contract has not yet been
 * notified about, so each contract gets exactly three reminders over
 * its final quarter.
 */
class ContractRenewalAlertNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly int $daysOut,
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

        return $this->baseMail($notifiable, 'notifications.contract.renewal_alert.subject', [
            'number' => $number,
            'days' => $this->daysOut,
        ])
            ->line($this->t($notifiable, 'notifications.contract.renewal_alert.line1', [
                'number' => $number,
                'days' => $this->daysOut,
            ]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.contract.renewal_alert.title'),
            'message' => $this->t($notifiable, 'notifications.contract.renewal_alert.message', [
                'number' => $this->contract->contract_number,
                'days' => $this->daysOut,
            ]),
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
