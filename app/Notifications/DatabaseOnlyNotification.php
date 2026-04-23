<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic database + mail notification used by simple reminders that
 * don't need a dedicated class (BG expiry, retention release due, etc).
 * Collects a title / body / url and formats them for both channels.
 */
class DatabaseOnlyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->title)->line($this->body);
        if ($this->url) {
            $m->action(__('common.view'), $this->url);
        }

        return $m;
    }
}
