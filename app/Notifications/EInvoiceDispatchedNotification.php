<?php

namespace App\Notifications;

use App\Models\EInvoiceSubmission;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the supplier's finance team when an e-invoice has been
 * successfully handed off to the FTA Peppol clearance system. The
 * "FTA accepted" notification follows asynchronously via the webhook
 * — this one only confirms dispatch.
 */
class EInvoiceDispatchedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly EInvoiceSubmission $submission,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'compliance_alerts',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ref = $this->reference();

        return $this->baseMail($notifiable, 'notifications.einvoice.dispatched.subject', ['ref' => $ref])
            ->line($this->t($notifiable, 'notifications.einvoice.dispatched.line1', ['ref' => $ref]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.einvoice.dispatched.title'),
            'message' => $this->t($notifiable, 'notifications.einvoice.dispatched.message', ['ref' => $this->reference()]),
            'entity_type' => 'einvoice_submission',
            'entity_id' => $this->submission->id,
        ];
    }

    private function reference(): string
    {
        return $this->submission->taxInvoice?->invoice_number
            ?? $this->submission->taxCreditNote?->credit_note_number
            ?? ('#'.$this->submission->id);
    }
}
