<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
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
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $id = $this->dispute->id;
        $action = $this->localisedAction($notifiable);

        return $this->baseMail($notifiable, 'notifications.dispute.event.subject', ['id' => $id, 'action' => $action])
            ->line($this->t($notifiable, 'notifications.dispute.event.message', ['id' => $id, 'action' => $action]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                route('dashboard.disputes.show', ['id' => $this->dispute->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->action === 'resolved' ? 'success' : 'warning',
            'title' => $this->t($notifiable, 'notifications.dispute.event.title'),
            'message' => $this->t($notifiable, 'notifications.dispute.event.message', [
                'id' => $this->dispute->id,
                'action' => $this->localisedAction($notifiable),
            ]),
            'entity_type' => 'dispute',
            'entity_id' => $this->dispute->id,
            'contract_id' => $this->dispute->contract_id,
        ];
    }

    private function localisedAction(object $notifiable): string
    {
        $key = 'notifications.dispute.actions.'.$this->action;
        $value = trans($key, [], $this->localeFor($notifiable));

        return $value === $key ? $this->action : $value;
    }
}
