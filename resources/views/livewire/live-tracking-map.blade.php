<div wire:poll.60s class="space-y-4">
    {{-- Header --}}
    <div class="bg-surface border border-th-border rounded-2xl p-4 flex items-center justify-between flex-wrap gap-3">
        <div>
            <p class="text-[12px] font-mono text-muted">{{ $this->shipment->tracking_number }}</p>
            <p class="text-[16px] font-bold text-primary">{{ $this->shipment->contract?->title ?? '—' }}</p>
        </div>
        <x-dashboard.status-badge :status="$this->shipment->status?->value ?? 'preparing'" />
    </div>

    {{-- Map --}}
    <div id="tracking-map-{{ $shipmentId }}" class="w-full h-[420px] rounded-2xl border border-th-border overflow-hidden bg-surface"></div>

    {{-- Trail list --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('shipments.trail') }}</h3>
        <ul class="space-y-3">
            @forelse($this->trail as $point)
            <li class="flex items-start gap-3">
                <span class="w-2 h-2 rounded-full bg-accent mt-1.5 flex-shrink-0"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-semibold text-primary">{{ $point['description'] ?? '—' }}</p>
                    <p class="text-[11px] text-muted">{{ $point['lat'] }}, {{ $point['lng'] }} · {{ $point['at'] }}</p>
                </div>
            </li>
            @empty
            <li class="text-[13px] text-muted">{{ __('shipments.no_tracking_yet') }}</li>
            @endforelse
        </ul>
    </div>

    {{-- Leaflet bootstrap --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function () {
        const trail = @json($this->trail);
        const mapEl = document.getElementById('tracking-map-{{ $shipmentId }}');
        if (!mapEl || mapEl._leafletInstance) return;

        const center = trail.length > 0 ? [trail[trail.length - 1].lat, trail[trail.length - 1].lng] : [25.276987, 55.296249];
        const map = L.map(mapEl).setView(center, 9);
        mapEl._leafletInstance = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        if (trail.length > 0) {
            const latlngs = trail.map(p => [p.lat, p.lng]);
            L.polyline(latlngs, { color: '#3B82F6', weight: 4 }).addTo(map);
            L.marker(latlngs[latlngs.length - 1]).addTo(map)
                .bindPopup('Current location').openPopup();
        }
    })();
    </script>
</div>
