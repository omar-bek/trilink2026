<?php

namespace App\Notifications;

use App\Models\Company;
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
        return (new MailMessage)
            ->subject('Company resubmitted info — ' . $this->company->name)
            ->greeting('Hi ' . ($notifiable->first_name ?? 'Admin') . ',')
            ->line("**{$this->company->name}** has just submitted the additional information you requested. Their registration is ready for re-review.")
            ->action('Review Company', route('admin.companies.show', $this->company->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_info_completed',
            'title'        => 'Pending company resubmitted info',
            'message'      => "{$this->company->name} has provided the additional info you requested.",
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'action_url'   => route('admin.companies.show', $this->company->id),
        ];
    }
}
