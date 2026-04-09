<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Contract $contract,
        private readonly string $signerName,
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

        return $this->baseMail($notifiable, 'notifications.contract.signed.subject', [
                'number' => $number,
                'party'  => $this->signerName,
            ])
            ->line($this->t($notifiable, 'notifications.contract.signed.line1', [
                'party'  => $this->signerName,
                'number' => $number,
            ]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'success',
            'title'       => $this->t($notifiable, 'notifications.contract.signed.title'),
            'message'     => $this->t($notifiable, 'notifications.contract.signed.message', [
                'party'  => $this->signerName,
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id'   => $this->contract->id,
        ];
    }
}
