<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer's finance approvers when a Payment row is
 * generated and is sitting in PENDING_APPROVAL — they need to act on
 * it before any money can move. Different from PaymentStatus which
 * fires AFTER an action; this is the "please act on this" prompt.
 */
class PaymentRequestedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'payment_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format((float) $this->payment->amount, 2);
        $currency = $this->payment->currency ?? 'AED';

        return $this->baseMail($notifiable, 'notifications.payment.requested.subject', [
            'amount' => $amount,
            'currency' => $currency,
        ])
            ->line($this->t($notifiable, 'notifications.payment.requested.line1'))
            ->line($this->t($notifiable, 'notifications.payment.requested.line_amount', ['amount' => $amount, 'currency' => $currency]))
            ->when(
                $this->payment->milestone,
                fn ($mail) => $mail->line($this->t($notifiable, 'notifications.payment.requested.line_milestone', ['milestone' => $this->payment->milestone]))
            )
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_payment'),
                route('dashboard.payments.show', ['id' => $this->payment->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.payment.requested.title'),
            'message' => $this->t($notifiable, 'notifications.payment.requested.message', [
                'amount' => number_format((float) $this->payment->amount, 2),
                'currency' => $this->payment->currency ?? 'AED',
            ]),
            'entity_type' => 'payment',
            'entity_id' => $this->payment->id,
            'contract_id' => $this->payment->contract_id,
        ];
    }
}
