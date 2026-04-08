<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the PR author (buyer) when their request is rejected by a manager.
 * Carries the rejection reason so the buyer can revise and resubmit.
 */
class PurchaseRequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PurchaseRequest $pr,
        private readonly ?string $reason = null,
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
            ->subject("Purchase request rejected — {$this->pr->title}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your purchase request **{$this->pr->title}** was rejected by your manager.");

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        return $mail->line('You can revise the details and submit again when ready.')
            ->action('View Request', route('dashboard.purchase-requests.show', ['id' => $this->pr->id]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'warning',
            'title'       => 'Purchase request rejected',
            'message'     => $this->reason
                ? "Rejected: {$this->reason}"
                : "Your request \"{$this->pr->title}\" was rejected",
            'entity_type' => 'purchase_request',
            'entity_id'   => $this->pr->id,
        ];
    }
}
