<?php

namespace App\Notifications;

use App\Models\CompanyDocument;
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

    public function __construct(private readonly CompanyDocument $document)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = $this->document->type instanceof \BackedEnum ? $this->document->type->value : (string) $this->document->type;
        $expires = $this->document->expires_at?->format('M j, Y') ?? '—';

        return (new MailMessage)
            ->subject("Document expiring soon — {$type}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("One of your verified documents on TriLink is about to expire on **{$expires}**.")
            ->line("Document: **{$type}**")
            ->action('Open Document Vault', route('dashboard.documents.index'))
            ->line('Re-upload a fresh copy before it expires to keep your verification tier intact.');
    }

    public function toArray(object $notifiable): array
    {
        $type = $this->document->type instanceof \BackedEnum ? $this->document->type->value : (string) $this->document->type;

        return [
            'type'        => 'document_expiring_soon',
            'title'       => 'Document expiring soon',
            'message'     => "Your {$type} expires on " . ($this->document->expires_at?->format('M j, Y') ?? '—'),
            'entity_type' => 'company_document',
            'entity_id'   => $this->document->id,
            'action_url'  => route('dashboard.documents.index'),
        ];
    }
}
