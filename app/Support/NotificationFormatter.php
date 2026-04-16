<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Formats Laravel `notifications` table rows into UI-ready arrays
 * for the dashboard bell, dropdown, and notifications index page.
 *
 * Each notification dispatched by the app stores a `data` JSON with
 * a consistent shape (see app/Notifications/*::toArray()):
 *
 *   ['type' => 'success|warning|error|info',
 *    'title' => '...',
 *    'message' => '...',
 *    'entity_type' => 'bid|contract|payment|dispute|...',
 *    'entity_id' => 123,
 *    ...optional refs (rfq_id, contract_id, ...)]
 *
 * This formatter is the *only* place that knows how to map a stored
 * notification to its icon, color, and clickable URL — change a mapping
 * here and every consumer (dashboard shell, dropdown, index page) updates.
 */
class NotificationFormatter
{
    /**
     * Map an entity_type to:
     *   - icon path (Heroicons-style `d=`)
     *   - color slot (matches the shell's notifColors map)
     *   - route name builder closure (returns route name + params)
     */
    private const ENTITY_MAP = [
        'bid' => [
            'icon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25',
            'color' => 'green',
            'route' => 'dashboard.bids.show',
        ],
        'contract' => [
            'icon'  => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'color' => 'blue',
            'route' => 'dashboard.contracts.show',
        ],
        'payment' => [
            'icon'  => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15',
            'color' => 'orange',
            'route' => 'dashboard.payments.show',
        ],
        'dispute' => [
            'icon'  => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z',
            'color' => 'red',
            'route' => 'dashboard.disputes.show',
        ],
        'rfq' => [
            'icon'  => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25',
            'color' => 'purple',
            'route' => 'dashboard.rfqs.show',
        ],
        'shipment' => [
            'icon'  => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25',
            'color' => 'purple',
            'route' => 'dashboard.shipments.show',
        ],
        'purchase_request' => [
            'icon'  => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75',
            'color' => 'blue',
            'route' => 'dashboard.purchase-requests.show',
        ],
        'einvoice_submission' => [
            'icon'  => 'M9 14.25l3-3m0 0l3 3m-3-3v8.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'color' => 'orange',
            'route' => null,
        ],
        'privacy_request' => [
            'icon'  => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            'color' => 'blue',
            'route' => null,
        ],
    ];

    /**
     * If the notification's `data.type` is set, it overrides the entity color.
     * Lets a "rejected payment" show as red even though payments are normally orange.
     */
    private const TYPE_COLOR_OVERRIDE = [
        'success' => 'green',
        'warning' => 'orange',
        'error'   => 'red',
        'info'    => 'blue',
    ];

    /**
     * Format a single DatabaseNotification into a display array.
     *
     * @return array{
     *   id: string,
     *   icon: string,
     *   color: string,
     *   title: string,
     *   desc: string,
     *   time: string,
     *   url: ?string,
     *   read: bool,
     *   created_at: \Illuminate\Support\Carbon|null
     * }
     */
    public function format(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;
        $entityType = $data['entity_type'] ?? null;
        $entityId   = $data['entity_id'] ?? null;
        $type       = $data['type'] ?? null;

        $entity = self::ENTITY_MAP[$entityType] ?? null;

        $color = $entity['color'] ?? 'blue';
        if ($type && isset(self::TYPE_COLOR_OVERRIDE[$type])) {
            $color = self::TYPE_COLOR_OVERRIDE[$type];
        }

        $url = null;
        if ($entity && $entityId) {
            try {
                $url = route($entity['route'], ['id' => $entityId]);
            } catch (\Throwable $e) {
                // Route may not exist (e.g. tests with stub data) — leave url null.
                $url = null;
            }
        }

        $createdAt = $notification->created_at instanceof Carbon ? $notification->created_at : null;

        return [
            'id'         => $notification->id,
            'icon'       => $entity['icon'] ?? self::DEFAULT_ICON,
            'color'      => $color,
            'title'      => (string) ($data['title'] ?? __('notifications.title')),
            'desc'       => (string) ($data['message'] ?? ''),
            'time'       => $createdAt?->diffForHumans(short: true) ?? '',
            'url'        => $url,
            'read'       => $notification->read_at !== null,
            'created_at' => $createdAt,
        ];
    }

    /**
     * Format a collection of notifications.
     *
     * @param  iterable<DatabaseNotification>  $notifications
     * @return array<int, array<string, mixed>>
     */
    public function formatMany(iterable $notifications): array
    {
        $out = [];
        foreach ($notifications as $n) {
            $out[] = $this->format($n);
        }
        return $out;
    }

    private const DEFAULT_ICON = 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0';
}
