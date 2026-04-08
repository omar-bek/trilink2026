<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Dispute $dispute,
        private readonly string $action,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        // Disputes are critical workflow events — they fall under
        // contract_milestones since they directly affect contract execution.
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Dispute {$this->action}: {$this->dispute->title}")
            ->line("Dispute \"{$this->dispute->title}\" has been {$this->action}.")
            ->action('View Dispute', config('app.frontend_url') . "/disputes/{$this->dispute->id}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->action === 'resolved' ? 'success' : 'warning',
            'title' => "Dispute " . ucfirst($this->action),
            'message' => "Dispute \"{$this->dispute->title}\" has been {$this->action}",
            'entity_type' => 'dispute',
            'entity_id' => $this->dispute->id,
            'contract_id' => $this->dispute->contract_id,
        ];
    }
}
