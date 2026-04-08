<?php

namespace App\Notifications;

use App\Models\Company;
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
        return (new MailMessage)
            ->subject('Action required — additional info needed for ' . $this->company->name)
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Our admin team has reviewed your registration for **{$this->company->name}** and needs a few more details before they can approve it.")
            ->when($this->note !== '', fn ($mail) => $mail->line("**Admin note:** {$this->note}"))
            ->action('Complete missing information', route('register.success'))
            ->line('Once you submit the requested items the team will pick the review back up.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_info_requested',
            'title'        => 'Action required on your registration',
            'message'      => "Admin team needs more info before approving {$this->company->name}.",
            'entity_type'  => 'company',
            'entity_id'    => $this->company->id,
            'note'         => $this->note,
            'action_url'   => route('register.success'),
        ];
    }
}
