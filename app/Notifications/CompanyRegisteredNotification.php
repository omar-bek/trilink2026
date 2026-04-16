<?php

namespace App\Notifications;

use App\Models\Company;
use App\Notifications\Concerns\LocalizesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired at all platform admins whenever a new company self-registers.
 * Stored in the `notifications` table (database channel) so it surfaces in
 * the admin's bell + a mail copy goes out for off-hours visibility.
 */
class CompanyRegisteredNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

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
        return $this->baseMail($notifiable, 'notifications.company.registered.subject', ['name' => $this->company->name])
            ->line($this->t($notifiable, 'notifications.company.registered.message', ['name' => $this->company->name]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_review'),
                route('admin.companies.show', $this->company->id)
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'company_registered',
            'title' => $this->t($notifiable, 'notifications.company.registered.title'),
            'message' => $this->t($notifiable, 'notifications.company.registered.message', ['name' => $this->company->name]),
            'entity_type' => 'company',
            'entity_id' => $this->company->id,
            'company_name' => $this->company->name,
            'company_type' => $this->company->type?->value,
            'country' => $this->company->country,
            'action_url' => route('admin.companies.show', $this->company->id),
        ];
    }
}
