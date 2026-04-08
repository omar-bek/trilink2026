<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
        private readonly string $action,
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
            ->subject("Payment {$this->action}")
            ->line("Payment of {$this->payment->total_amount} {$this->payment->currency} has been {$this->action}.")
            ->action('View Payment', config('app.frontend_url') . "/payments/{$this->payment->id}");
    }

    public function toArray(object $notifiable): array
    {
        $type = match ($this->action) {
            'approved' => 'success',
            'rejected' => 'error',
            'completed' => 'success',
            default => 'info',
        };

        return [
            'type' => $type,
            'title' => "Payment " . ucfirst($this->action),
            'message' => "Payment of {$this->payment->total_amount} {$this->payment->currency} has been {$this->action}",
            'entity_type' => 'payment',
            'entity_id' => $this->payment->id,
            'contract_id' => $this->payment->contract_id,
        ];
    }
}
