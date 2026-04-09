<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to platform admins when a pending company manager has just
 * submitted the additional info that an admin previously requested.
 * Lets the reviewer know the file is ready to look at again.
 */
class CompanyInfoCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use LocalizesNotification;

    public function __construct(
        private readonly Company $company,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail($notifiable, 'notifications.company.info_completed.subject', ['name' => $this->company->name])
            ->line($this->t($notifiable, 'notifications.company.info_completed.message', ['name' => $this->company->name]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_review'),
                route('admin.companies.show', $this->company->id)
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_info_completed',
            'title'        => $this->t($notifiable, 'notifications.company.info_completed.title'),
            'message'      => $this->t($notifiable, 'notifications.company.info_completed.message', ['name' => $this->company->name]),
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'action_url'   => route('admin.companies.show', $this->company->id),
        ];
    }
}
