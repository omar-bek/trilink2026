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
 * Sent when the FTA Peppol clearance system rejects an e-invoice
 * submission. Carries the rejection reason verbatim — the supplier
 * needs the exact error code/message to figure out which field of
 * the UBL document violated the schema or business rule.
 */
class EInvoiceRejectedNotification extends Notification implements ShouldQueue
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
        $reason = $this->submission->error_message ?: '—';

        return $this->baseMail($notifiable, 'notifications.einvoice.rejected.subject', ['ref' => $ref])
            ->error()
            ->line($this->t($notifiable, 'notifications.einvoice.rejected.line1', ['ref' => $ref]))
            ->line($this->t($notifiable, 'notifications.einvoice.rejected.line_reason', ['reason' => $reason]))
            ->line($this->t($notifiable, 'notifications.einvoice.rejected.line2'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'error',
            'title' => $this->t($notifiable, 'notifications.einvoice.rejected.title'),
            'message' => $this->t($notifiable, 'notifications.einvoice.rejected.message', ['ref' => $this->reference()]),
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
