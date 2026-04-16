<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 3 — fired by EscrowService whenever an escrow account changes
 * state. Both buyer and supplier teams receive it because both sides have
 * money on the line. The frontend NotificationFormatter routes the click
 * back to the contract show page where the escrow panel lives.
 */
class EscrowEventNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        public readonly int $contractId,
        public readonly string $action,
        public readonly ?float $amount,
        public readonly string $currency,
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
        $event = $this->localisedEvent($notifiable);

        return $this->baseMail($notifiable, 'notifications.escrow.event.subject', ['event' => $event])
            ->line($this->t($notifiable, 'notifications.escrow.event.line1'))
            ->line($event.($this->amount ? ' — '.$this->currency.' '.number_format((float) $this->amount, 2) : ''))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_contract'),
                route('dashboard.contracts.show', ['id' => $this->contractId])
            );
    }

    public function toArray(object $notifiable): array
    {
        $type = match ($this->action) {
            'release', 'deposit', 'activated' => 'success',
            'refund' => 'warning',
            default => 'info',
        };

        return [
            'type' => $type,
            'title' => $this->t($notifiable, 'notifications.escrow.event.title'),
            'message' => $this->t($notifiable, 'notifications.escrow.event.message', [
                'event' => $this->localisedEvent($notifiable),
            ]),
            'entity_type' => 'contract',
            'entity_id' => $this->contractId,
        ];
    }

    private function localisedEvent(object $notifiable): string
    {
        $key = 'notifications.escrow.events.'.$this->action;
        $value = trans($key, [], $this->localeFor($notifiable));

        return $value === $key ? $this->action : $value;
    }
}
