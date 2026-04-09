<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

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
            'payment_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref    = $this->payment->id;
        $status = $this->localisedAction($notifiable);

        return $this->baseMail($notifiable, 'notifications.payment.status.subject', [
                'ref'    => $ref,
                'status' => $status,
            ])
            ->line($this->t($notifiable, 'notifications.payment.status.line1', [
                'ref'    => $ref,
                'status' => $status,
            ]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_payment'),
                route('dashboard.payments.show', ['id' => $this->payment->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        $type = match ($this->action) {
            'approved', 'completed' => 'success',
            'rejected'              => 'error',
            default                 => 'info',
        };

        return [
            'type'        => $type,
            'title'       => $this->t($notifiable, 'notifications.payment.status.title'),
            'message'     => $this->t($notifiable, 'notifications.payment.status.message', [
                'ref'    => $this->payment->id,
                'status' => $this->localisedAction($notifiable),
            ]),
            'entity_type' => 'payment',
            'entity_id'   => $this->payment->id,
            'contract_id' => $this->payment->contract_id,
        ];
    }

    /**
     * Translate the raw action verb (approved, rejected, …) into a
     * locale-aware label so the message reads naturally in both
     * English and Arabic.
     */
    private function localisedAction(object $notifiable): string
    {
        $key = 'notifications.payment.actions.' . $this->action;
        $localised = trans($key, [], $this->localeFor($notifiable));
        return $localised === $key ? $this->action : $localised;
    }
}
