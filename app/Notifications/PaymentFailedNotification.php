<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by PaymentService::process() when the underlying gateway
 * (Stripe / Telr / network) rejects the charge. The buyer's finance
 * team needs to know immediately so they can retry with a different
 * card / re-issue the wire / chase the bank.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Payment $payment,
        private readonly ?string $reason = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'payment_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref = (string) $this->payment->id;

        $mail = $this->baseMail($notifiable, 'notifications.payment.failed.subject', ['ref' => $ref])
            ->error()
            ->line($this->t($notifiable, 'notifications.payment.failed.line1', ['ref' => $ref]));

        if ($this->reason) {
            $mail->line($this->t($notifiable, 'notifications.payment.failed.line_reason', ['reason' => $this->reason]));
        }

        return $mail->line($this->t($notifiable, 'notifications.payment.failed.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_payment'),
                route('dashboard.payments.show', ['id' => $this->payment->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'error',
            'title'       => $this->t($notifiable, 'notifications.payment.failed.title'),
            'message'     => $this->t($notifiable, 'notifications.payment.failed.message', ['ref' => $this->payment->id]),
            'entity_type' => 'payment',
            'entity_id'   => $this->payment->id,
            'contract_id' => $this->payment->contract_id,
        ];
    }
}
