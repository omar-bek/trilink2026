<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by ExpireSignatureWindowsCommand when a contract has been
 * sitting in PENDING_SIGNATURES past its signing window. Tells both
 * parties the window closed and they need to issue a fresh request
 * if they still want to execute the contract.
 */
class ContractSignatureExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

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
        $number = $this->contract->contract_number;

        return $this->baseMail($notifiable, 'notifications.contract.signature_expired.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.signature_expired.line1', ['number' => $number]))
            ->line($this->t($notifiable, 'notifications.contract.signature_expired.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => $this->t($notifiable, 'notifications.contract.signature_expired.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.signature_expired.message', [
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
