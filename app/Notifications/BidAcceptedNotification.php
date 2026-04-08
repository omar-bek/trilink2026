<?php

namespace App\Notifications;

use App\Models\Bid;
use App\Models\Contract;
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
        $mail = (new MailMessage)
            ->subject("Your bid was accepted — {$this->bid->rfq?->title}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your bid on RFQ #{$this->bid->rfq?->rfq_number} has been **accepted** by the buyer.")
            ->line('**Amount:** ' . ($this->bid->currency ?? 'AED') . ' ' . number_format((float) $this->bid->price, 2));

        if ($this->contract) {
            $mail->action('View Contract', route('dashboard.contracts.show', ['id' => $this->contract->id]))
                ->line('A contract has been generated and is ready for your review and signature.');
        } else {
            $mail->action('View Bid', route('dashboard.bids.show', ['id' => $this->bid->id]));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'success',
            'title'       => 'Bid Accepted',
            'message'     => "Your bid on #{$this->bid->rfq?->rfq_number} was accepted",
            'entity_type' => $this->contract ? 'contract' : 'bid',
            'entity_id'   => $this->contract?->id ?? $this->bid->id,
        ];
    }
}
