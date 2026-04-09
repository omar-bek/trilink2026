<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Losing bid notice" — required by procurement industry norms (and
 * mandatory for some UAE government tenders): when a buyer awards an
 * RFQ to one supplier, every OTHER supplier that bid must be told the
 * award decision was made and they were not selected.
 *
 * Different from BidRejectedNotification:
 *   - BidRejected fires per-bid when a buyer hits the reject button
 *     on an individual bid (could be early-stage, RFQ still open).
 *   - LosingBid fires when the AWARD has been made — final, with no
 *     possibility of being reconsidered for this RFQ.
 *
 * The current accept() flow auto-rejects sibling bids and fires
 * BidRejectedNotification, which is OK but doesn't give the supplier
 * the dignity of "the buyer made a decision and chose someone else"
 * vs. "your bid was rejected" (which sounds like it was bad). The
 * losing-bid wording matches what mature procurement platforms do.
 */
class LosingBidNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Bid $bid,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'bid_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->bid->rfq?->rfq_number ?? '—';

        return $this->baseMail($notifiable, 'notifications.bid.losing_award.subject', ['rfq' => $rfqNumber])
            ->line($this->t($notifiable, 'notifications.bid.losing_award.line1', ['rfq' => $rfqNumber]))
            ->line($this->t($notifiable, 'notifications.bid.losing_award.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view_rfq'),
                route('dashboard.rfqs.show', ['id' => $this->bid->rfq_id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'info',
            'title'       => $this->t($notifiable, 'notifications.bid.losing_award.title'),
            'message'     => $this->t($notifiable, 'notifications.bid.losing_award.message', [
                'rfq' => $this->bid->rfq?->rfq_number ?? '—',
            ]),
            'entity_type' => 'bid',
            'entity_id'   => $this->bid->id,
        ];
    }
}
