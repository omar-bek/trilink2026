<?php

namespace App\Notifications;

use App\Models\CompanyDocument;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a company manager when one of their verified documents (trade
 * license, ISO cert, insurance, etc.) is within 30 days of expiry. Lets
 * them re-upload before the doc expires and the verification tier drops.
 */
class DocumentExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(private readonly CompanyDocument $document)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'compliance_alerts',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type    = $this->document->type instanceof \BackedEnum ? $this->document->type->value : (string) $this->document->type;
        $expires = $this->document->expires_at?->format('M j, Y') ?? '—';

        return $this->baseMail($notifiable, 'notifications.document.expiring.subject', ['name' => $type])
            ->line($this->t($notifiable, 'notifications.document.expiring.message', ['name' => $type, 'date' => $expires]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                route('dashboard.documents.index')
            );
    }

    public function toArray(object $notifiable): array
    {
        $type = $this->document->type instanceof \BackedEnum ? $this->document->type->value : (string) $this->document->type;

        return [
            'type'        => 'document_expiring_soon',
            'title'       => $this->t($notifiable, 'notifications.document.expiring.title'),
            'message'     => $this->t($notifiable, 'notifications.document.expiring.message', [
                'name' => $type,
                'date' => $this->document->expires_at?->format('M j, Y') ?? '—',
            ]),
            'entity_type' => 'company_document',
            'entity_id'   => $this->document->id,
            'action_url'  => route('dashboard.documents.index'),
        ];
    }
}
