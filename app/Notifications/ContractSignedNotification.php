<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractSignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly string $signerName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
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
