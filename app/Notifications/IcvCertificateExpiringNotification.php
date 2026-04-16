<?php

namespace App\Notifications;

use App\Models\IcvCertificate;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 4.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * fired by NotifyExpiringIcvCertificatesCommand for the 60/30/7 day
 * thresholds. Recipients are the company managers of the supplier
 * that holds the cert.
 *
 * Without this notification the supplier discovers the expiry the
 * day they lose a tender — by the time the dashboard surfaces it,
 * the renewal lead time at MoIAT/ADNOC/etc. is already too short.
 */
class IcvCertificateExpiringNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        public readonly IcvCertificate $certificate,
        public readonly int $daysUntilExpiry,
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
        $expiresOn = $this->certificate->expires_date?->format('d M Y') ?? '—';

        return $this->baseMail($notifiable, 'notifications.icv.expiring.subject', ['days' => $this->daysUntilExpiry])
            ->line($this->t($notifiable, 'notifications.icv.expiring.line1'))
            ->line($this->t($notifiable, 'notifications.icv.expiring.line_date', ['date' => $expiresOn]))
            ->line($this->t($notifiable, 'notifications.icv.expiring.line2'))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                url('/dashboard/icv-certificates')
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'icv_certificate_expiring',
            'title' => $this->t($notifiable, 'notifications.icv.expiring.title'),
            'message' => $this->t($notifiable, 'notifications.icv.expiring.message', ['days' => $this->daysUntilExpiry]),
            'icv_certificate_id' => $this->certificate->id,
            'issuer' => $this->certificate->issuer,
            'certificate_number' => $this->certificate->certificate_number,
            'expires_date' => $this->certificate->expires_date?->toIso8601String(),
            'days_until_expiry' => $this->daysUntilExpiry,
            'action_url' => '/dashboard/icv-certificates',
        ];
    }
}
