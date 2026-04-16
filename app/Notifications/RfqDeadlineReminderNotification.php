<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to suppliers who haven't yet bid on an RFQ that's about to
 * close. Fired by the SendRfqDeadlineRemindersCommand for the 48h /
 * 24h / 2h thresholds — the command picks the largest threshold
 * the supplier hasn't been notified about, so each supplier gets at
 * most one reminder per RFQ per threshold.
 */
class RfqDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Rfq $rfq,
        private readonly int $hoursLeft,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'rfq_matches',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->rfq->rfq_number;
        $title = (string) $this->rfq->title;
        $deadline = $this->rfq->deadline?->format('M j, Y H:i') ?? '—';

        return $this->baseMail($notifiable, 'notifications.rfq.deadline.subject', [
            'rfq' => $rfqNumber,
            'hours' => $this->hoursLeft,
        ])
            ->line($this->t($notifiable, 'notifications.rfq.deadline.line1', [
                'rfq' => $rfqNumber,
                'title' => $title,
            ]))
            ->line($this->t($notifiable, 'notifications.rfq.deadline.line_deadline', ['deadline' => $deadline]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs.show', ['id' => $this->rfq->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.rfq.deadline.title'),
            'message' => $this->t($notifiable, 'notifications.rfq.deadline.message', [
                'rfq' => $this->rfq->rfq_number,
                'hours' => $this->hoursLeft,
            ]),
            'entity_type' => 'rfq',
            'entity_id' => $this->rfq->id,
        ];
    }
}
