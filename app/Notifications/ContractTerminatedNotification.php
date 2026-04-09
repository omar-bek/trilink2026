<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to all parties when a contract is terminated mid-life — i.e.
 * one side is exercising a termination clause, not waiting for the
 * end_date to roll around. Carries the termination reason so the
 * other side has a paper trail of WHY.
 */
class ContractTerminatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Contract $contract,
        private readonly ?string $reason = null,
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

        $mail = $this->baseMail($notifiable, 'notifications.contract.terminated.subject', ['number' => $number])
            ->error()
            ->line($this->t($notifiable, 'notifications.contract.terminated.line1', ['number' => $number]));

        if ($this->reason) {
            $mail->line($this->t($notifiable, 'notifications.contract.terminated.line_reason', ['reason' => $this->reason]));
        }

        return $mail->action(
            $this->t($notifiable, 'notifications.common.action_view_contract'),
            route('dashboard.contracts.show', ['id' => $this->contract->id])
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'error',
            'title'       => $this->t($notifiable, 'notifications.contract.terminated.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.terminated.message', [
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
