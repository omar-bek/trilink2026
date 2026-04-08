<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new MailMessage)
            ->subject("Contract {$this->contract->contract_number} Signed")
            ->line("{$this->signerName} has signed the contract.")
            ->action('View Contract', config('app.frontend_url') . "/contracts/{$this->contract->id}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'success',
            'title' => 'Contract Signed',
            'message' => "{$this->signerName} has signed contract {$this->contract->contract_number}",
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
