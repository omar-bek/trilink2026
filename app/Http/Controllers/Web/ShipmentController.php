<?php

namespace App\Http\Controllers\Web;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\FormatsForViews;
use App\Http\Requests\Shipment\TrackShipmentRequest;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    use FormatsForViews;

    public function __construct(private readonly ShipmentService $service)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->hasPermission('shipment.view'), 403);

        $companyId = $this->currentCompanyId();

        $base = Shipment::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $stats = [
            'total'      => (clone $base)->count(),
            'in_transit' => (clone $base)->where('status', ShipmentStatus::IN_TRANSIT->value)->count(),
            'at_customs' => (clone $base)->where('status', ShipmentStatus::IN_CLEARANCE->value)->count(),
            'delayed'    => (clone $base)
                ->where('estimated_delivery', '<', now())
                ->whereNotIn('status', [ShipmentStatus::DELIVERED->value, ShipmentStatus::CANCELLED->value])
                ->count(),
            'delivered'  => (clone $base)->where('status', ShipmentStatus::DELIVERED->value)->count(),
        ];

        $shipments = (clone $base)
            ->with(['contract', 'logisticsCompany'])
            ->latest()
            ->get()
            ->map(function (Shipment $sh) {
                $statusKey = $this->mapShipmentStatus($this->statusValue($sh->status));

                return [
                    'id'       => $sh->tracking_number,
                    'status'   => $statusKey,
                    'title'    => $sh->contract?->title ?? ('Shipment ' . $sh->tracking_number),
                    'contract' => $sh->contract?->contract_number ?? '—',
                    'from'     => $this->locationLabel($sh->origin),
                    'to'       => $this->locationLabel($sh->destination),
                    'progress' => $this->progressFor($statusKey),
                    'eta'      => $this->longDate($sh->estimated_delivery),
                    'carrier'  => $sh->logisticsCompany?->name ?? '—',
                    'time'     => $sh->updated_at?->diffForHumans(null, true) ?? '',
                ];
            })
            ->toArray();

        return view('dashboard.shipments.index', compact('stats', 'shipments'));
    }

    public function show(string $id): View
    {
        abort_unless(auth()->user()?->hasPermission('shipment.view'), 403);

        $sh = $this->findOrFail($id)->load(['contract', 'logisticsCompany', 'trackingEvents']);
        $statusKey = $this->mapShipmentStatus($this->statusValue($sh->status));

        $events = $sh->trackingEvents;
        $timeline = $this->buildTimeline($sh, $events, $statusKey);
        $gpsUpdates = $events->take(5)->map(function ($event) {
            $loc = $event->location ?? [];
            return [
                'title'    => $event->description ?: ucfirst(str_replace('_', ' ', $event->status)),
                'location' => $this->locationLabel($loc),
                'time'     => $event->event_at?->format('M j, g:i A') ?? '',
            ];
        })->all();

        $daysRemaining = null;
        if ($sh->estimated_delivery) {
            $diff = now()->startOfDay()->diffInDays($sh->estimated_delivery->startOfDay(), false);
            $daysRemaining = (int) max(0, $diff);
        }

        $logistics = $sh->logisticsCompany;

        $shipment = [
            'id'              => $sh->tracking_number,
            'numeric_id'      => $sh->id,
            'title'           => $sh->contract?->title ?? __('shipments.shipment') . ' ' . $sh->tracking_number,
            'status'          => $statusKey,
            'contract_number' => $sh->contract?->contract_number,
            'contract_id'     => $sh->contract?->id,
            'from'            => $this->locationLabel($sh->origin),
            'to'              => $this->locationLabel($sh->destination),
            'progress'        => $this->progressFor($statusKey),
            'eta'             => $this->longDate($sh->estimated_delivery),
            'days_remaining'  => $daysRemaining,
            'carrier'         => $logistics?->name,
            'carrier_code'    => $logistics ? $this->initials($logistics->name) : null,
            'carrier_email'   => $logistics?->email,
            'carrier_phone'   => $logistics?->phone,
            'timeline'        => $timeline,
            'gps_updates'     => $gpsUpdates,
            'notes'           => $sh->notes,
        ];

        return view('dashboard.shipments.show', compact('shipment'));
    }

    /**
     * Build a status timeline blending the canonical phases with real tracking_events.
     *
     * @return array<int, array{done:bool, current:bool, title:string, desc:string, time:string, location:string}>
     */
    private function buildTimeline(Shipment $sh, $events, string $statusKey): array
    {
        $phases = [
            'preparing'        => ['key' => 'preparing'],
            'in_transit'       => ['key' => 'in_transit'],
            'at_customs'       => ['key' => 'at_customs'],
            'out_for_delivery' => ['key' => 'out_for_delivery'],
            'delivered'        => ['key' => 'delivered'],
        ];

        $order = array_keys($phases);
        $currentIdx = array_search($statusKey, $order, true);
        if ($currentIdx === false) {
            $currentIdx = 0;
        }

        $eventByStatus = $events->keyBy(fn ($e) => $this->mapShipmentStatus($e->status));

        $timeline = [];
        foreach ($order as $idx => $phaseKey) {
            $event = $eventByStatus->get($phaseKey);
            $isDone = $idx < $currentIdx || ($idx === $currentIdx && $statusKey === 'delivered');
            $isCurrent = $idx === $currentIdx && $statusKey !== 'delivered';

            $timeline[] = [
                'done'     => $isDone || $isCurrent,
                'current'  => $isCurrent,
                'title'    => __('status.' . $phaseKey),
                'desc'     => $event?->description ?? __('shipments.phase_desc_' . $phaseKey),
                'time'     => $event?->event_at?->format('F j, Y - g:i A') ?? '',
                'location' => $event ? $this->locationLabel($event->location ?? []) : '',
            ];
        }

        return $timeline;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/[\s\-]+/u', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) break;
        }
        return $letters !== '' ? $letters : '—';
    }

    public function track(TrackShipmentRequest $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('shipment.track'), 403);

        $shipment = $this->findOrFail($id);

        $location = array_filter([
            'lat'  => $request->input('lat'),
            'lng'  => $request->input('lng'),
            'city' => $request->input('city'),
        ]);

        $this->service->addTrackingEvent($shipment->id, [
            'status'      => $request->input('status'),
            'description' => $request->input('description'),
            'location'    => $location ?: null,
            'event_at'    => now(),
        ]);

        return redirect()
            ->route('dashboard.shipments.show', ['id' => $shipment->id])
            ->with('status', __('shipments.tracking_updated'));
    }

    private function findOrFail(string $id): Shipment
    {
        if (str_starts_with($id, 'SHP-')) {
            return Shipment::where('tracking_number', $id)->firstOrFail();
        }

        return Shipment::findOrFail((int) $id);
    }

    private function locationLabel($location): string
    {
        if (is_string($location)) {
            return $location;
        }
        if (is_array($location)) {
            return $location['city'] ?? $location['address'] ?? $location['name'] ?? '—';
        }

        return '—';
    }

    private function mapShipmentStatus(string $status): string
    {
        return match ($status) {
            'in_production'    => 'preparing',
            'ready_for_pickup' => 'preparing',
            'in_transit'       => 'in_transit',
            'in_clearance'     => 'at_customs',
            'delivered'        => 'delivered',
            'cancelled'        => 'closed',
            default            => 'preparing',
        };
    }

    private function progressFor(string $statusKey): int
    {
        return match ($statusKey) {
            'preparing'        => 20,
            'in_transit'       => 65,
            'at_customs'       => 45,
            'out_for_delivery' => 90,
            'delivered'        => 100,
            default            => 0,
        };
    }
}
