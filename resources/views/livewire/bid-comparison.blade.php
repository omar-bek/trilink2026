<div class="space-y-6" wire:poll.30s>
    {{-- Header --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
            <div>
                <p class="text-[12px] font-mono text-muted">{{ $this->rfq->rfq_number }}</p>
                <h2 class="text-[20px] font-bold text-primary">{{ $this->rfq->title }}</h2>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[12px] text-muted">{{ __('bids.sort_by') }}:</span>
                <button wire:click="sort('price')" class="px-3 py-1.5 rounded-lg text-[12px] font-semibold {{ $sortBy === 'price' ? 'text-white bg-accent' : 'text-primary bg-page border border-th-border' }}">
                    {{ __('bids.price') }}
                    @if($sortBy === 'price')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                </button>
                <button wire:click="sort('delivery')" class="px-3 py-1.5 rounded-lg text-[12px] font-semibold {{ $sortBy === 'delivery' ? 'text-white bg-accent' : 'text-primary bg-page border border-th-border' }}">
                    {{ __('bids.delivery') }}
                    @if($sortBy === 'delivery')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                </button>
                <button wire:click="sort('ai_score')" class="px-3 py-1.5 rounded-lg text-[12px] font-semibold {{ $sortBy === 'ai_score' ? 'text-white bg-accent' : 'text-primary bg-page border border-th-border' }}">
                    {{ __('bids.ai_score') }}
                    @if($sortBy === 'ai_score')<span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                </button>
            </div>
        </div>
        <p class="text-[12px] text-muted">{{ __('bids.compare_hint') }}</p>
    </div>

    {{-- All bids list --}}
    <div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
        <table class="w-full">
            <thead class="bg-page border-b border-th-border">
                <tr>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider w-12"></th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.supplier') }}</th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.price') }}</th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.delivery_days') }}</th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('bids.ai_score') }}</th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                    <th class="text-start p-4 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($this->bids as $bid)
                <tr class="hover:bg-page transition-colors {{ in_array($bid->id, $selected, true) ? 'bg-accent/5' : '' }}">
                    <td class="p-4">
                        <input type="checkbox" wire:click="toggle({{ $bid->id }})" {{ in_array($bid->id, $selected, true) ? 'checked' : '' }} class="w-4 h-4 rounded border-th-border text-accent focus:ring-accent">
                    </td>
                    <td class="p-4">
                        <p class="text-[13px] font-bold text-primary">{{ $bid->company?->name ?? '—' }}</p>
                        <p class="text-[11px] text-muted">BID-{{ $bid->id }}</p>
                    </td>
                    <td class="p-4">
                        <p class="text-[15px] font-bold text-[#10B981]">{{ $bid->currency }} {{ number_format((float) $bid->price) }}</p>
                    </td>
                    <td class="p-4">
                        <p class="text-[13px] font-semibold text-primary">{{ $bid->delivery_time_days }} days</p>
                    </td>
                    <td class="p-4">
                        <p class="text-[14px] font-bold text-accent">{{ $bid->ai_score['score'] ?? '—' }}</p>
                    </td>
                    <td class="p-4">
                        <x-dashboard.status-badge :status="$bid->status?->value ?? 'submitted'" />
                    </td>
                    <td class="p-4">
                        @if(in_array($bid->status?->value, ['submitted', 'under_review'], true))
                        <button wire:click="accept({{ $bid->id }})"
                                wire:confirm="{{ __('bids.confirm_accept') }}"
                                class="px-3 py-1.5 rounded-lg text-[11px] font-bold text-white bg-[#10B981] hover:bg-[#0EA371]">
                            {{ __('bids.accept') }}
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-[13px] text-muted">{{ __('bids.no_bids_yet') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Side-by-side comparison panel --}}
    @if(count($selected) >= 2)
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('bids.side_by_side', ['count' => count($selected)]) }}</h3>
        <div class="grid grid-cols-{{ count($selected) }} gap-4">
            @foreach($this->bids->whereIn('id', $selected) as $bid)
            <div class="bg-page border border-th-border rounded-xl p-5">
                <p class="text-[11px] text-muted mb-1">{{ $bid->company?->name }}</p>
                <p class="text-[24px] font-bold text-[#10B981] mb-3">{{ $bid->currency }} {{ number_format((float) $bid->price) }}</p>
                <dl class="space-y-2 text-[12px]">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">{{ __('bids.delivery') }}:</dt>
                        <dd class="font-semibold text-primary">{{ $bid->delivery_time_days }}d</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">{{ __('bids.payment_terms') }}:</dt>
                        <dd class="font-semibold text-primary truncate">{{ $bid->payment_terms ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">{{ __('bids.ai_score') }}:</dt>
                        <dd class="font-bold text-accent">{{ $bid->ai_score['score'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
