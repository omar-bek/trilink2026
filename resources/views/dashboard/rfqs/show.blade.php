@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', $rfq['id'])

@section('content')

{{-- Header row: back arrow + title + status pill + match pill (left) / Submit Bid (right) --}}
<div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <a href="{{ route('dashboard.rfqs') }}"
           class="w-10 h-10 rounded-[12px] bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] flex items-center justify-center text-[#b4b6c0] hover:text-white hover:border-[#4f7cff]/40 flex-shrink-0 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[28px] sm:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">#{{ $rfq['id'] }}</h1>
                <span class="inline-flex items-center gap-2 h-[26px] px-3 rounded-full border bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                    {{ ucfirst($rfq['status']) }}
                </span>
                <span class="inline-flex items-center gap-1.5 h-[26px] px-3 rounded-full border bg-[rgba(0,217,181,0.1)] border-[rgba(0,217,181,0.2)] text-[#00d9b5] text-[12px] font-medium">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519"/></svg>
                    {{ $rfq['match'] }}% Match
                </span>
            </div>
            <p class="text-[16px] text-[#b4b6c0] mt-1">{{ $rfq['title'] }}</p>
        </div>
    </div>
    @php $hasMyBid = !empty($rfq['competition']['my_bid_id']); @endphp
    @if($rfq['status'] === 'open' && !$hasMyBid)
    <a href="{{ route('dashboard.rfqs.bid.create', ['id' => $rfq['numeric_id']]) }}"
       class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
        </svg>
        {{ __('rfq.submit_bid') }}
    </a>
    @elseif($hasMyBid)
    <a href="{{ route('dashboard.bids.show', ['id' => $rfq['competition']['my_bid_id']]) }}"
       class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ __('rfq.view_my_bid') }}
    </a>
    @endif
</div>

{{-- Already-bid info bar: pinned to the top so the supplier never sees the
     submit form again. Replaces the "Privacy Protected" message in this
     branch since the privacy contract no longer applies once they've bid. --}}
@if($hasMyBid)
<div class="bg-[rgba(0,217,181,0.08)] border border-[rgba(0,217,181,0.3)] rounded-[12px] p-4 mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <div class="w-10 h-10 rounded-[10px] bg-[rgba(0,217,181,0.15)] flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-[14px] font-semibold text-[#00d9b5]">{{ __('rfq.you_already_bid') }}</p>
            <p class="text-[13px] text-[#b4b6c0] mt-0.5">{{ __('rfq.you_already_bid_hint') }}</p>
        </div>
        <a href="{{ route('dashboard.bids.show', ['id' => $rfq['competition']['my_bid_id']]) }}"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-[10px] text-[13px] font-semibold text-[#00d9b5] bg-[rgba(0,217,181,0.12)] border border-[rgba(0,217,181,0.3)] hover:bg-[rgba(0,217,181,0.18)] transition-colors">
            {{ __('rfq.view_my_bid') }} →
        </a>
    </div>
</div>
@endif

{{-- Deadline warning bar --}}
@if($rfq['days_left'] !== null && $rfq['days_left'] <= 4)
<div class="bg-[rgba(255,176,32,0.08)] border border-[rgba(255,176,32,0.3)] rounded-[12px] p-4 mb-4">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-[#ffb020] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 3h.01"/>
        </svg>
        <div>
            <p class="text-[14px] font-semibold text-[#ffb020]">Deadline approaching: {{ $rfq['days_left'] }} days remaining</p>
            <p class="text-[13px] text-[#b4b6c0] mt-0.5">Submit your bid before {{ $rfq['deadline'] }} at {{ $rfq['deadline_time'] }}</p>
        </div>
    </div>
</div>
@endif

{{-- Privacy protected alert --}}
<div class="bg-[rgba(79,124,255,0.06)] border border-[rgba(79,124,255,0.25)] rounded-[12px] p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-[#4f7cff] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
        </svg>
        <div>
            <p class="text-[14px] font-semibold text-[#4f7cff]">Privacy Protected Bidding</p>
            <p class="text-[13px] text-[#b4b6c0] mt-0.5">Buyer identity is protected until contract signing. This ensures a fair and unbiased bidding process.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- LEFT: Info cards + tabs --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- 4 info cards: Budget / Deadline / Category / Location --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-[10px] bg-[rgba(0,217,181,0.1)] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.5-4-3.5S9.79 5 12 5c1.128 0 2.147.373 2.854.968l.875.675"/></svg>
                    </div>
                    <p class="text-[13px] text-[#b4b6c0]">Budget Range</p>
                </div>
                <p class="text-[20px] font-semibold text-[#00d9b5] leading-[28px]">{{ $rfq['budget'] }}</p>
            </div>

            <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-[10px] bg-[rgba(255,176,32,0.1)] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    </div>
                    <p class="text-[13px] text-[#b4b6c0]">Submission Deadline</p>
                </div>
                <p class="text-[18px] font-semibold text-white leading-[24px]">{{ $rfq['deadline'] }}</p>
                @if($rfq['days_left'] !== null)
                <p class="text-[12px] text-[#ff4d7f] mt-1">{{ $rfq['days_left'] }} days remaining</p>
                @endif
            </div>

            <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-[10px] bg-[rgba(79,124,255,0.1)] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                    </div>
                    <p class="text-[13px] text-[#b4b6c0]">Category</p>
                </div>
                <p class="text-[18px] font-semibold text-white leading-[24px]">{{ $rfq['category'] }}</p>
            </div>

            <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-[10px] bg-[rgba(255,176,32,0.1)] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    </div>
                    <p class="text-[13px] text-[#b4b6c0]">Delivery Location</p>
                </div>
                <p class="text-[18px] font-semibold text-white leading-[24px]">{{ $rfq['location'] }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div x-data="{ tab: 'details' }" class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <div class="flex items-center gap-6 border-b border-[rgba(255,255,255,0.1)] mb-5 -mx-[25px] px-[25px]">
                @foreach(['details' => 'Details', 'specifications' => 'Specifications', 'terms' => 'Terms & Conditions', 'documents' => 'Documents'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'text-[#4f7cff] border-[#4f7cff]' : 'text-[#b4b6c0] border-transparent hover:text-white'"
                        class="pb-3 text-[14px] font-medium border-b-2 transition-colors">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Details tab --}}
            <div x-show="tab === 'details'" x-cloak>
                <h3 class="text-[16px] font-semibold text-white mb-2">Project Description</h3>
                <p class="text-[14px] text-[#b4b6c0] leading-[22px] mb-6">{{ $rfq['description'] ?: 'No description provided.' }}</p>

                @if(!empty($rfq['items']))
                <h3 class="text-[16px] font-semibold text-white mb-3">Line Items</h3>
                <div class="space-y-3 mb-6">
                    @foreach($rfq['items'] as $item)
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <p class="text-[14px] font-medium text-white">{{ $item['name'] }}</p>
                            <p class="text-[14px] font-semibold text-[#00d9b5]">{{ $item['total'] }}</p>
                        </div>
                        <div class="flex items-center gap-4 text-[12px] text-[#b4b6c0]">
                            <span>Quantity: <span class="text-white">{{ number_format($item['qty']) }} {{ $item['unit'] }}</span></span>
                            <span>Unit: <span class="text-white">{{ $item['unit'] }}</span></span>
                            <span>Unit Price: <span class="text-white">{{ $item['unit_price'] }}</span></span>
                        </div>
                        @if($item['spec'])
                        <p class="text-[12px] text-[#b4b6c0] mt-2">{{ $item['spec'] }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                <h3 class="text-[16px] font-semibold text-white mb-3">Delivery Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Expected Delivery Date</p>
                        <p class="text-[14px] font-medium text-white">{{ $rfq['required_date'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Delivery Terms</p>
                        <p class="text-[14px] font-medium text-white">{{ $rfq['delivery_terms'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Delivery Location</p>
                        <p class="text-[14px] font-medium text-white">{{ $rfq['location'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Payment Terms</p>
                        <p class="text-[14px] font-medium text-white">{{ $rfq['payment_terms'] }}</p>
                    </div>
                </div>
            </div>

            <div x-show="tab === 'specifications'" x-cloak>
                <p class="text-[14px] text-[#b4b6c0]">Technical specifications will be listed here as line-item specs.</p>
            </div>
            <div x-show="tab === 'terms'" x-cloak>
                <p class="text-[14px] text-[#b4b6c0]">{{ $rfq['payment_terms'] }} · {{ $rfq['delivery_terms'] }}</p>
            </div>
            <div x-show="tab === 'documents'" x-cloak>
                <p class="text-[14px] text-[#b4b6c0]">No documents attached.</p>
            </div>
        </div>
    </div>

    {{-- RIGHT: Buyer info + Competition + Submit Bid + Download --}}
    <div class="space-y-4">
        {{-- Buyer Information — supplier-side only; owner/admin see null. --}}
        @if(!empty($rfq['buyer']))
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Buyer Information</h3>
            <div class="flex items-center gap-3 mb-5">
                <div class="w-11 h-11 rounded-[10px] bg-[rgba(79,124,255,0.1)] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[14px] font-medium text-white truncate">{{ $rfq['buyer']['name'] }}</p>
                    @if($rfq['buyer']['verified'])
                    <p class="text-[12px] text-[#00d9b5]">Verified Buyer</p>
                    @endif
                </div>
            </div>
            <dl class="space-y-3 text-[13px]">
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">Company Type</dt>
                    <dd class="text-white font-medium">{{ $rfq['buyer']['type'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">Rating</dt>
                    <dd class="text-white font-medium inline-flex items-center gap-1">
                        @if($rfq['buyer']['rating'])
                            {{ $rfq['buyer']['rating'] }}
                            <svg class="w-3.5 h-3.5 text-[#ffb020]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        @else
                            <span class="text-[#b4b6c0] text-[12px]">No reviews yet</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">Total Projects</dt>
                    <dd class="text-white font-medium">{{ $rfq['buyer']['total_projects'] }}</dd>
                </div>
            </dl>
        </div>
        @endif

        {{-- Competition --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Competition</h3>
            <div class="text-center mb-4">
                <p class="text-[36px] font-bold text-[#4f7cff] leading-none">{{ $rfq['competition']['count'] }}</p>
                <p class="text-[13px] text-[#b4b6c0] mt-1">Suppliers Bidding</p>
            </div>
            <dl class="space-y-3 text-[13px] pt-4 border-t border-[rgba(255,255,255,0.1)]">
                @if($rfq['competition']['my_bid_id'])
                    {{-- Supplier already submitted, so revealing aggregates is fair. --}}
                    <div class="flex items-center justify-between">
                        <dt class="text-[#b4b6c0]">Average Bid</dt>
                        <dd class="text-white font-medium">{{ $rfq['competition']['average'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-[#b4b6c0]">Lowest Bid</dt>
                        <dd class="text-[#00d9b5] font-semibold">{{ $rfq['competition']['lowest'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-[#b4b6c0]">Your Position</dt>
                        <dd class="font-medium text-[#00d9b5]">#{{ $rfq['competition']['my_position'] }}</dd>
                    </div>
                @else
                    {{-- Hide pricing intelligence until the supplier commits a bid. --}}
                    <p class="text-[12px] text-[#b4b6c0]">Submit your bid to unlock competition insights (average, lowest, your ranking).</p>
                @endif
            </dl>
        </div>

        {{-- Submit + Download --}}
        @if($rfq['status'] === 'open' && !$rfq['competition']['my_bid_id'])
        <a href="{{ route('dashboard.rfqs.bid.create', ['id' => $rfq['numeric_id']]) }}"
           class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
            {{ __('rfq.submit_your_bid') }}
        </a>
        @elseif($rfq['competition']['my_bid_id'])
        <a href="{{ route('dashboard.bids.show', ['id' => $rfq['competition']['my_bid_id']]) }}"
           class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('rfq.view_my_bid') }}
        </a>
        @endif
        <a href="{{ route('dashboard.rfqs.pdf', ['id' => $rfq['numeric_id']]) }}"
           class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
            {{ __('rfq.download_package') }}
        </a>
    </div>
</div>

@endsection
