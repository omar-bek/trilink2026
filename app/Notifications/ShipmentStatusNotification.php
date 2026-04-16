<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\User;
use App\Notifications\Concerns\LocalizesNotification;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every party of a contract when the related shipment's status
 * changes (in_production → in_transit → in_clearance → delivered).
 *
 * Triggered from `ShipmentService::updateStatus()` (added in this change)
 * which dispatches the notification after persisting the new status.
 */
class ShipmentStatusNotification extends Notification implements ShouldQueue
{
    use LocalizesNotification;
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment,
        private readonly string $newStatus,
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
        $ref = $this->shipment->tracking_number ?: ('#'.$this->shipment->id);
        $status = $this->localisedStatus($notifiable);

        return $this->baseMail($notifiable, 'notifications.shipment.status.subject', [
            'ref' => $ref,
            'status' => $status,
        ])
            ->line($this->t($notifiable, 'notifications.shipment.status.message', [
                'ref' => $ref,
                'status' => $status,
            ]))
            ->action(
                $this->t($notifiable, 'notifications.common.action_view'),
                route('dashboard.shipments.show', ['id' => $this->shipment->id])
            );
    }

    public function toArray(object $notifiable): array
    {
        $ref = $this->shipment->tracking_number ?: ('#'.$this->shipment->id);

        return [
            'type' => match ($this->newStatus) {
                'delivered' => 'success',
                'in_clearance', 'in_transit' => 'info',
                default => 'info',
            },
            'title' => $this->t($notifiable, 'notifications.shipment.status.title'),
            'message' => $this->t($notifiable, 'notifications.shipment.status.message', [
                'ref' => $ref,
                'status' => $this->localisedStatus($notifiable),
            ]),
            'entity_type' => 'shipment',
            'entity_id' => $this->shipment->id,
        ];
    }

    private function localisedStatus(object $notifiable): string
    {
        $key = 'notifications.shipment.statuses.'.$this->newStatus;
        $value = trans($key, [], $this->localeFor($notifiable));

        return $value === $key ? ucwords(str_replace('_', ' ', $this->newStatus)) : $value;
    }
}
