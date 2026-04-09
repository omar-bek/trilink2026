<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to suppliers in matching categories the moment an RFQ moves
 * from draft to published. The marketplace dashboard surfaces these
 * eventually, but until they get an email/bell ping the supplier
 * has no reason to log in — and the deadline ticks down without them.
 *
 * Recipients are resolved by the dispatcher: every active company
 * whose category set intersects with the RFQ's category. Excluding
 * the buyer's own company is the dispatcher's job, not this class's.
 */
class RfqPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Rfq $rfq,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'rfq_matches',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title    = (string) $this->rfq->title;
        $buyer    = $this->rfq->company?->name ?? '—';
        $deadline = $this->rfq->deadline?->format('M j, Y H:i') ?? '—';
        $budget   = number_format((float) $this->rfq->budget, 2);
        $currency = $this->rfq->currency ?? 'AED';

        return $this->baseMail($notifiable, 'notifications.rfq.published.subject', ['title' => $title])
            ->line($this->t($notifiable, 'notifications.rfq.published.line1'))
            ->line($this->t($notifiable, 'notifications.rfq.published.line_buyer', ['buyer' => $buyer]))
            ->line($this->t($notifiable, 'notifications.rfq.published.line_budget', ['amount' => $budget, 'currency' => $currency]))
            ->line($this->t($notifiable, 'notifications.rfq.published.line_deadline', ['deadline' => $deadline]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs.show', ['id' => $this->rfq->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'info',
            'title'       => $this->t($notifiable, 'notifications.rfq.published.title'),
            'message'     => $this->t($notifiable, 'notifications.rfq.published.message', [
                'rfq'   => $this->rfq->rfq_number,
                'title' => $this->rfq->title,
            ]),
            'entity_type' => 'rfq',
            'entity_id'   => $this->rfq->id,
        ];
    }
}
