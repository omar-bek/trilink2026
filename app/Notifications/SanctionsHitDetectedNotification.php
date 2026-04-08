<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\SanctionsScreening;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every active admin user the moment a sanctions screening returns
 * a HIT or REVIEW verdict for any company on the platform.
 *
 * The admin handles the alert by visiting the verification queue page,
 * inspecting the matched_entities snapshot stored on the screening row,
 * and either:
 *
 *   - Confirming the hit (company stays blocked, account suspended), or
 *   - Marking it a false positive (status flipped back to clean, allowing
 *     the company to transact again).
 *
 * Queued on the `notifications` queue so a slow mail driver doesn't
 * stall the screening pipeline.
 */
class SanctionsHitDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Company $company,
        private readonly SanctionsScreening $screening,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verdict = strtoupper($this->screening->result);
        $count   = $this->screening->match_count;
        $company = $this->company->name;

        $message = (new MailMessage)
            ->subject("Sanctions {$verdict} — {$company}")
            ->error()
            ->greeting('Action required')
            ->line("A sanctions screening just returned **{$verdict}** for **{$company}** ({$count} matched entities).")
            ->line('The company has been demoted to UNVERIFIED and cannot transact until reviewed.');

        try {
            $message = $message->action(
                'Open verification queue',
                route('admin.verification.index'),
            );
        } catch (\Throwable) {
            // Verification queue route may not be registered yet during
            // partial deploys; skip the CTA in that case rather than crashing
            // the entire notification.
        }

        return $message->line('Inspect the matched entities, then confirm the hit or mark as false positive.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'sanctions_hit',
            'title'        => 'Sanctions screening alert',
            'message'      => "{$this->company->name} flagged ({$this->screening->result}, {$this->screening->match_count} matches)",
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'screening_id' => $this->screening->id,
            'result'       => $this->screening->result,
            'match_count'  => $this->screening->match_count,
        ];
    }
}
