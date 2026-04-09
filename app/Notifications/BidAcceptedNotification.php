<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Models\Contract;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the bid's submitter (supplier) when the buyer accepts their bid.
 * A Contract is usually created in the same request — the notification
 * carries its id so the mail CTA and in-app link jump straight to it.
 */
class BidAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Bid $bid,
        private readonly ?Contract $contract = null,
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
        $title     = $this->bid->rfq?->title ?? '';
        $amount    = number_format((float) $this->bid->price, 2);
        $currency  = $this->bid->currency ?? 'AED';

        $mail = $this->baseMail($notifiable, 'notifications.bid.accepted.subject', ['title' => $title])
            ->line($this->t($notifiable, 'notifications.bid.accepted.line1', ['rfq' => $rfqNumber]))
            ->line($this->t($notifiable, 'notifications.bid.accepted.line_amount', ['amount' => $amount, 'currency' => $currency]));

        if ($this->contract) {
            $mail->line($this->t($notifiable, 'notifications.bid.accepted.line_contract'))
                ->action(
                    $this->t($notifiable, 'notifications.common.action_view_contract'),
                    route('dashboard.contracts.show', ['id' => $this->contract->id])
                );
        } else {
            $mail->action(
                $this->t($notifiable, 'notifications.common.action_view_bid'),
                route('dashboard.bids.show', ['id' => $this->bid->id])
            );
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $rfqNumber = $this->bid->rfq?->rfq_number ?? '—';

        return [
            'type'        => 'success',
            'title'       => $this->t($notifiable, 'notifications.bid.accepted.title'),
            'message'     => $this->t($notifiable, 'notifications.bid.accepted.message', ['rfq' => $rfqNumber]),
            'entity_type' => $this->contract ? 'contract' : 'bid',
            'entity_id'   => $this->contract?->id ?? $this->bid->id,
        ];
    }
}
