<?php

namespace App\Notifications;

use App\Models\Shipment;
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
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment,
        private readonly string $newStatus,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return \App\Support\NotificationPreferences::channels(
            $notifiable instanceof \App\Models\User ? $notifiable : null,
            'contract_milestones',
            ['database', 'mail']
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = ucwords(str_replace('_', ' ', $this->newStatus));
        $contract = $this->shipment->contract;
        $title = $contract?->title ?: 'your contract';

        return (new MailMessage)
            ->subject("Shipment update — {$label}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("The shipment for **{$title}** is now **{$label}**.")
            ->when(
                $this->shipment->tracking_number,
                fn ($mail) => $mail->line("Tracking number: **{$this->shipment->tracking_number}**")
            )
            ->when(
                $this->shipment->estimated_delivery,
                fn ($mail) => $mail->line('Estimated delivery: ' . $this->shipment->estimated_delivery->format('F j, Y'))
            )
            ->action('View Shipment', route('dashboard.shipments.show', ['id' => $this->shipment->id]));
    }

    public function toArray(object $notifiable): array
    {
        $label = ucwords(str_replace('_', ' ', $this->newStatus));

        return [
            'type'        => match ($this->newStatus) {
                'delivered' => 'success',
                'in_clearance', 'in_transit' => 'info',
                default => 'info',
            },
            'title'       => 'Shipment ' . $label,
            'message'     => ($this->shipment->tracking_number ?? '—') . ' · ' . ($this->shipment->contract?->title ?? ''),
            'entity_type' => 'shipment',
            'entity_id'   => $this->shipment->id,
        ];
    }
}
