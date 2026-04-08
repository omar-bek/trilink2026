@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('auction.title') . ' — ' . $rfq->title)

@section('content')

<div class="mb-6">
    <a href="{{ route('dashboard.rfqs.show', $rfq->id) }}"
       class="inline-flex items-center gap-2 text-[12px] text-muted hover:text-primary">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('auction.back_to_rfq') }}
    </a>
</div>

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Live status + bid form --}}
    <div class="space-y-6">
        <div class="bg-surface border-2 border-accent/30 rounded-2xl p-6">
            <div class="text-[11px] text-muted uppercase tracking-wider mb-2">{{ __('auction.time_remaining') }}</div>
            <div id="auction-clock" class="text-[36px] font-bold text-accent leading-none mb-1">--:--:--</div>
            <div id="auction-status" class="text-[12px] text-muted">{{ __('auction.live') }}</div>
        </div>

        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="text-[11px] text-muted uppercase tracking-wider mb-2">{{ __('auction.current_leader') }}</div>
            <div id="leader-price" class="text-[28px] font-bold text-[#00d9b5] leading-none">
                {{ $snapshot['leader_price'] ? number_format($snapshot['leader_price'], 2) : '—' }}
            </div>
            <div class="text-[12px] text-muted mt-1">{{ $rfq->currency }}</div>
            @if($snapshot['reserve_price'])
                <div class="mt-3 pt-3 border-t border-th-border text-[11px] text-muted">
                    {{ __('auction.reserve') }}: <span class="text-primary font-semibold">{{ number_format($snapshot['reserve_price'], 2) }} {{ $rfq->currency }}</span>
                </div>
            @endif
            @if($snapshot['bid_decrement'])
                <div class="text-[11px] text-muted">
                    {{ __('auction.min_decrement') }}: <span class="text-primary font-semibold">{{ number_format($snapshot['bid_decrement'], 2) }}</span>
                </div>
            @endif
        </div>

        @auth
            @if(auth()->user()->company_id !== $rfq->company_id && auth()->user()->hasPermission('bid.submit'))
            <form method="POST" action="{{ route('dashboard.auctions.bid', $rfq->id) }}" class="bg-surface border border-th-border rounded-2xl p-6">
                @csrf
                <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('auction.place_bid') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('auction.your_price') }} ({{ $rfq->currency }})</label>
                        <input type="number" step="0.01" min="0.01" name="price" required
                               class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[14px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <button type="submit"
                            class="w-full h-12 rounded-xl bg-accent text-white text-[14px] font-bold hover:bg-accent/90 transition-colors">
                        {{ __('auction.submit_bid') }}
                    </button>
                </div>
                <p class="text-[10px] text-muted mt-3 leading-relaxed">{{ __('auction.bid_explainer') }}</p>
            </form>
            @endif
        @endauth
    </div>

    {{-- Leaderboard --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[15px] font-bold text-primary">{{ __('auction.leaderboard') }}</h3>
            <span class="inline-flex items-center gap-1 text-[10px] text-muted">
                <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                {{ __('auction.auto_refresh') }}
            </span>
        </div>
        <div id="leaderboard" class="space-y-2">
            @forelse($snapshot['leaderboard'] as $row)
            <div class="flex items-center justify-between bg-page rounded-xl p-3 border border-th-border">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full {{ $row['rank'] === 1 ? 'bg-[#00d9b5] text-white' : 'bg-surface-2 text-muted' }} flex items-center justify-center font-bold text-[12px]">{{ $row['rank'] }}</span>
                    <span class="text-[13px] font-semibold text-primary">{{ $row['company'] }}</span>
                </div>
                <span class="text-[14px] font-bold {{ $row['rank'] === 1 ? 'text-[#00d9b5]' : 'text-primary' }}">{{ number_format($row['price'], 2) }} {{ $row['currency'] }}</span>
            </div>
            @empty
            <p class="text-[12px] text-muted text-center py-6">{{ __('auction.no_bids_yet') }}</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const auctionId = {{ $rfq->id }};
    const liveUrl   = "{{ route('dashboard.auctions.live', $rfq->id) }}";
    const clockEl       = document.getElementById('auction-clock');
    const statusEl      = document.getElementById('auction-status');
    const leaderEl      = document.getElementById('leader-price');
    const leaderboardEl = document.getElementById('leaderboard');

    let endsAt = @json($snapshot['auction_ends_at']);
    let currency = @json($rfq->currency);

    function fmtNum(n) {
        return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function tick() {
        if (!endsAt) {
            clockEl.textContent = '--:--:--';
            return;
        }
        const diff = Math.max(0, Math.floor((new Date(endsAt).getTime() - Date.now()) / 1000));
        const h = Math.floor(diff / 3600).toString().padStart(2, '0');
        const m = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
        const s = (diff % 60).toString().padStart(2, '0');
        clockEl.textContent = `${h}:${m}:${s}`;
        if (diff === 0) {
            statusEl.textContent = "{{ __('auction.ended') }}";
            statusEl.classList.remove('text-muted');
            statusEl.classList.add('text-[#ff4d7f]');
        }
    }

    function renderLeaderboard(rows) {
        if (!rows || rows.length === 0) {
            leaderboardEl.innerHTML = '<p class="text-[12px] text-muted text-center py-6">{{ __('auction.no_bids_yet') }}</p>';
            return;
        }
        leaderboardEl.innerHTML = rows.map(r => `
            <div class="flex items-center justify-between bg-page rounded-xl p-3 border border-th-border">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full ${r.rank === 1 ? 'bg-[#00d9b5] text-white' : 'bg-surface-2 text-muted'} flex items-center justify-center font-bold text-[12px]">${r.rank}</span>
                    <span class="text-[13px] font-semibold text-primary">${r.company}</span>
                </div>
                <span class="text-[14px] font-bold ${r.rank === 1 ? 'text-[#00d9b5]' : 'text-primary'}">${fmtNum(r.price)} ${r.currency}</span>
            </div>
        `).join('');
    }

    async function poll() {
        try {
            const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            endsAt = data.auction_ends_at;
            leaderEl.textContent = data.leader_price ? fmtNum(data.leader_price) : '—';
            renderLeaderboard(data.leaderboard);
        } catch (e) { /* swallow — UI keeps last good state */ }
    }

    tick();
    setInterval(tick, 1000);
    setInterval(poll, 5000);
})();
</script>
@endpush

@endsection
