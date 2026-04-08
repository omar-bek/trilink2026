<?php

namespace App\Notifications;

use App\Models\Company;
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
            ->subject('New company awaiting approval — ' . $this->company->name)
            ->greeting('Hi ' . ($notifiable->first_name ?? 'Admin') . ',')
            ->line("A new company has just registered on the platform and is waiting for your review.")
            ->line("**Company:** {$this->company->name}")
            ->line("**Type:** " . ($this->company->type?->value ?? '—'))
            ->line("**Country:** " . ($this->company->country ?? '—'))
            ->line("**Trade License:** {$this->company->registration_number}")
            ->action('Review Company', route('admin.companies.show', $this->company->id))
            ->line('Please verify the submitted documents before approving.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'company_registered',
            'title'         => 'New company awaiting approval',
            'message'       => "{$this->company->name} just registered and needs review.",
            'entity_type'   => 'company',
            'entity_id'     => $this->company->id,
            'company_name'  => $this->company->name,
            'company_type'  => $this->company->type?->value,
            'country'       => $this->company->country,
            'action_url'    => route('admin.companies.show', $this->company->id),
        ];
    }
}
