<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OTHER party when one party explicitly declines to sign
 * a contract — the other side needs to know the deal is off (or that
 * the wording needs to be reworked) without having to refresh the
 * contract page.
 */
class ContractSignatureDeclinedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly string $declinerName,
        private readonly ?string $reason = null,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return NotificationPreferences::channels(
            $notifiable instanceof User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->contract->contract_number;

        $mail = $this->baseMail($notifiable, 'notifications.contract.signature_declined.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.signature_declined.line1', [
                'party' => $this->declinerName,
                'number' => $number,
            ]));

        if ($this->reason) {
            $mail->line($this->t($notifiable, 'notifications.contract.signature_declined.line_reason', ['reason' => $this->reason]));
        }

        return $mail->action(
            $this->t($notifiable, 'notifications.common.action_view_contract'),
            route('dashboard.contracts.show', ['id' => $this->contract->id])
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'error',
            'title' => $this->t($notifiable, 'notifications.contract.signature_declined.title'),
            'message' => $this->t($notifiable, 'notifications.contract.signature_declined.message', [
                'party' => $this->declinerName,
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
