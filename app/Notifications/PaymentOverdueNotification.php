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
 * Fired by SendPaymentOverdueRemindersCommand when a Payment row has
 * a milestone due_date in the past AND is still in PENDING_APPROVAL
 * or APPROVED (i.e. nobody has actually moved the money yet). Three
 * tier reminder bands: 7 / 14 / 30 days past due.
 */
class PaymentOverdueNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
        private readonly int $daysOverdue,
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
        $ref = (string) $this->payment->id;

        return $this->baseMail($notifiable, 'notifications.payment.overdue.subject', ['ref' => $ref])
            ->line($this->t($notifiable, 'notifications.payment.overdue.line1', ['ref' => $ref, 'days' => $this->daysOverdue]))
            ->line($this->t($notifiable, 'notifications.payment.overdue.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_payment'),
                route('dashboard.payments.show', ['id' => $this->payment->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.payment.overdue.title'),
            'message' => $this->t($notifiable, 'notifications.payment.overdue.message', [
                'ref' => $this->payment->id,
                'days' => $this->daysOverdue,
            ]),
            'entity_type' => 'payment',
            'entity_id' => $this->payment->id,
            'contract_id' => $this->payment->contract_id,
        ];
    }
}
