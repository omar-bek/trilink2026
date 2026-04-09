<?php

namespace App\Notifications;

use App\Models\EInvoiceSubmission;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the supplier's finance team when the FTA Peppol clearance
 * system accepts an e-invoice submission and returns a clearance ID.
 * The clearance ID becomes the legal record of the transaction —
 * suppliers need to know it landed so they can match against their
 * own VAT return when filing.
 */
class EInvoiceAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly EInvoiceSubmission $submission,
    ) {
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
        $ref = $this->reference();

        return $this->baseMail($notifiable, 'notifications.einvoice.accepted.subject', ['ref' => $ref])
            ->line($this->t($notifiable, 'notifications.einvoice.accepted.line1', ['ref' => $ref]))
            ->when(
                $this->submission->fta_clearance_id,
                fn ($mail) => $mail->line('FTA Clearance ID: ' . $this->submission->fta_clearance_id)
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'success',
            'title'       => $this->t($notifiable, 'notifications.einvoice.accepted.title'),
            'message'     => $this->t($notifiable, 'notifications.einvoice.accepted.message', ['ref' => $this->reference()]),
            'entity_type' => 'einvoice_submission',
            'entity_id'   => $this->submission->id,
        ];
    }

    private function reference(): string
    {
        return $this->submission->taxInvoice?->invoice_number
            ?? $this->submission->taxCreditNote?->credit_note_number
            ?? ('#' . $this->submission->id);
    }
}
