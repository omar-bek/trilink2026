<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Services\ContractService;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired the moment a contract is materialised — typically by
 * {@see ContractService::createFromBid()} after the
 * buyer accepts a bid. Recipients are every user belonging to a
 * party of the contract (buyer + supplier sides) so both teams know
 * the agreement is now waiting for them to sign.
 */
class ContractCreatedNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Contract $contract,
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
        $title = $this->contract->title;
        $currency = $this->contract->currency ?? 'AED';
        $amount = number_format((float) $this->contract->total_amount, 2);

        return $this->baseMail($notifiable, 'notifications.contract.created.subject', ['number' => $number])
            ->line($this->t($notifiable, 'notifications.contract.created.line1'))
            ->line($this->t($notifiable, 'notifications.contract.created.line_number', ['number' => $number]))
            ->line($this->t($notifiable, 'notifications.contract.created.line_title', ['title' => $title]))
            ->line($this->t($notifiable, 'notifications.contract.created.line_value', ['currency' => $currency, 'amount' => $amount]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_sign'),
                route('dashboard.contracts.show', ['id' => $this->contract->id])
            )
            ->line($this->t($notifiable, 'notifications.contract.created.line_footer'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'info',
            'title' => $this->t($notifiable, 'notifications.contract.created.title'),
            'message' => $this->t($notifiable, 'notifications.contract.created.message', ['number' => $this->contract->contract_number]),
            'entity_type' => 'contract',
            'entity_id' => $this->contract->id,
        ];
    }
}
