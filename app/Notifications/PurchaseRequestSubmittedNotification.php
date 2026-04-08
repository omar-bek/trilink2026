<?php

namespace App\Notifications;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every company manager in the buyer's company as soon as a buyer
 * creates a purchase request. The PR enters the manager's "Pending Approval"
 * inbox; the notification is the first signal they have new work waiting.
 */
class PurchaseRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PurchaseRequest $pr,
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
        $buyer = trim(($this->pr->buyer?->first_name ?? '') . ' ' . ($this->pr->buyer?->last_name ?? '')) ?: 'A buyer';
        $url   = route('dashboard.purchase-requests.show', ['id' => $this->pr->id]);

        return (new MailMessage)
            ->subject("New purchase request awaiting your approval — {$this->pr->title}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("{$buyer} just submitted a new purchase request and it's waiting for your approval.")
            ->line("**Title:** {$this->pr->title}")
            ->line('**Budget:** ' . number_format((float) $this->pr->budget, 2) . ' ' . ($this->pr->currency ?? 'AED'))
            ->action('Review request', $url)
            ->line('Approve or reject it from the Pending Requests inbox in your dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        $buyer = trim(($this->pr->buyer?->first_name ?? '') . ' ' . ($this->pr->buyer?->last_name ?? '')) ?: 'A buyer';

        return [
            'type'        => 'info',
            'title'       => 'New purchase request awaiting approval',
            'message'     => "{$buyer} submitted: {$this->pr->title}",
            'entity_type' => 'purchase_request',
            'entity_id'   => $this->pr->id,
        ];
    }
}
