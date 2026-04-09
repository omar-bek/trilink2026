<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the company manager when an admin asks for additional info
 * before approving the registration. Tells the user there's a form
 * waiting for them on /register/success.
 */
class CompanyInfoRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Company $company,
        private readonly string $note,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->baseMail($notifiable, 'notifications.company.info_requested.subject', ['name' => $this->company->name])
            ->line($this->t($notifiable, 'notifications.company.info_requested.message', ['name' => $this->company->name]));

        if ($this->note !== '') {
            $mail->line($this->note);
        }

        return $mail->action(
            $this->t($notifiable, 'notifications.common.action_view'),
            route('register.success')
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_info_requested',
            'title'        => $this->t($notifiable, 'notifications.company.info_requested.title'),
            'message'      => $this->t($notifiable, 'notifications.company.info_requested.message', ['name' => $this->company->name]),
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'note'         => $this->note,
            'action_url'   => route('register.success'),
        ];
    }
}
