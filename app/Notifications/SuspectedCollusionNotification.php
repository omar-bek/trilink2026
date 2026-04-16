<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 7 (UAE Compliance Roadmap) — fired when the anti-collusion
 * service detects a CRITICAL-severity pattern (shared beneficial
 * owner across competing bids). Goes to every platform admin so they
 * can investigate and label the alert before the award is finalised.
 *
 * Federal Decree-Law 36/2023 Article 5 prohibits agreements between
 * undertakings that restrict competition. The platform must
 * demonstrate it flagged suspicious patterns — otherwise it risks
 * being viewed as a facilitator under Article 13.
 */
class SuspectedCollusionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Rfq $rfq,
        public readonly array $findings,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rfqNumber = $this->rfq->rfq_number ?? $this->rfq->id;
        $count = count($this->findings);

        return (new MailMessage)
            ->subject("[CRITICAL] Suspected bid collusion on RFQ {$rfqNumber}")
            ->greeting('Hi '.($notifiable->first_name ?? 'Admin').',')
            ->line("The anti-collusion service detected {$count} critical-severity finding(s) on RFQ **{$rfqNumber}** (\"{$this->rfq->title}\").")
            ->line('At least two bidders share a beneficial owner — this is the textbook bid-rigging scenario under Federal Decree-Law 36/2023 Article 5.')
            ->line('Please investigate immediately and label the alert before the award is finalised.')
            ->action('Review Alerts', url('/dashboard/admin/anti-collusion'))
            ->line('Ignoring this alert creates regulatory exposure for the platform.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'suspected_collusion',
            'title' => 'Suspected bid collusion',
            'message' => count($this->findings).' critical finding(s) on RFQ '.($this->rfq->rfq_number ?? $this->rfq->id),
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
            'finding_count' => count($this->findings),
            'severity' => 'critical',
            'action_url' => '/dashboard/admin/anti-collusion',
        ];
    }
}
