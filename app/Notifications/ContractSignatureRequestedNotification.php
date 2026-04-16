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
 * Sent specifically to the user(s) who still need to sign a contract,
 * separate from the broader ContractCreated fan-out. The trigger is
 * either:
 *   - Contract was just created and is waiting on first signature, or
 *   - One party signed and the OTHER party now needs to sign.
 *
 * Carries an explicit "expires in N days" line so the recipient knows
 * the signing window has a hard floor — that's the difference between
 * a casual "FYI a contract exists" ping (ContractCreated) and the
 * action-required "you need to act on this" prompt (this class).
 */
class ContractSignatureRequestedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
        private readonly int $expiresInDays = 14,
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

        return $this->baseMail($notifiable, 'notifications.contract.signature_requested.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.signature_requested.line1', ['number' => $number]))
            ->line($this->t($notifiable, 'notifications.contract.signature_requested.line2', ['days' => $this->expiresInDays]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_sign'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warning',
            'title' => $this->t($notifiable, 'notifications.contract.signature_requested.title'),
            'message' => $this->t($notifiable, 'notifications.contract.signature_requested.message', [
                'number' => $this->contract->contract_number,
            ]),
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
