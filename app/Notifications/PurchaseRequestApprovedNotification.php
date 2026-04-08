<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PR author (buyer) when their request is approved. An RFQ is
 * typically auto-created in the same action, so the mail mentions that and
 * the CTA lands on the now-updated PR page.
 */
class PurchaseRequestApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PurchaseRequest $pr,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        // PR approval / rejection counts as a "bid update" preference since
        // it's the buyer's procurement workflow status — same toggle.
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'bid_updates',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Purchase request approved — {$this->pr->title}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your purchase request **{$this->pr->title}** has been approved by your manager.")
            ->line('An RFQ has been auto-created so suppliers can start bidding.')
            ->action('View Request', route('dashboard.purchase-requests.show', ['id' => $this->pr->id]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'success',
            'title'       => 'Purchase request approved',
            'message'     => "Your request \"{$this->pr->title}\" was approved",
            'entity_type' => 'purchase_request',
            'entity_id'   => $this->pr->id,
        ];
    }
}
