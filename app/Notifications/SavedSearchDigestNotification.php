<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily digest of new matching results for a user's saved searches.
 * Phase 1 / task 1.6.
 *
 * Receives a flat array of "hit" payloads — each one a small
 * {label, count, url, items[]} bundle the mailer will render. The
 * controller / cron caller is responsible for filtering by the user's
 * match threshold (`rfq_match_threshold`) before constructing this.
 */
class SavedSearchDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        /** @var array<int, array{label:string,count:int,url:string,items:array<int,array<string,mixed>>}> */
        private readonly array $bundles,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalHits = array_sum(array_column($this->bundles, 'count'));
        $first = $notifiable->first_name ?? 'there';

        $mail = (new MailMessage)
            ->subject('Your TriLink digest — '.$totalHits.' new matches')
            ->greeting('Hi '.$first.',')
            ->line("You have {$totalHits} new results across your saved searches today.");

        foreach ($this->bundles as $bundle) {
            $mail->line("**{$bundle['label']}** — {$bundle['count']} new")
                ->line('[View results]('.$bundle['url'].')');
        }

        return $mail->line('Manage your saved searches from your dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'saved_search_digest',
            'title' => 'New matches in your saved searches',
            'message' => array_sum(array_column($this->bundles, 'count')).' new results',
            'entity_type' => 'saved_search',
            'entity_id' => null,
            'bundles' => array_map(fn ($b) => [
                'label' => $b['label'],
                'count' => $b['count'],
                'url' => $b['url'],
            ], $this->bundles),
        ];
    }
}
