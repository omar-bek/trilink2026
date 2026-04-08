@props([
    'rows' => [],          // array of ['milestone', 'percentage', 'amount', 'stage'?]
    'total' => null,       // optional total label e.g. "AED 92,500"
    'title' => 'Payment Schedule',
    'subtitle' => null,    // optional helper text under the title
    'compact' => false,    // when true, drops the icons column for tighter detail panes
])

@php
// Stage → icon + accent color. The Bid/Contract controllers tag each row with
// a `stage` so views don't need to re-derive it from the milestone string.
$stageIcons = [
    'advance'    => ['color' => 'text-[#4f7cff]', 'bg' => 'bg-[rgba(79,124,255,0.1)]', 'icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.5-4-3.5S9.79 5 12 5c1.128 0 2.147.373 2.854.968l.875.675'],
    'production' => ['color' => 'text-[#ffb020]', 'bg' => 'bg-[rgba(255,176,32,0.1)]','icon' => 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.277a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z'],
    'delivery'   => ['color' => 'text-[#00d9b5]', 'bg' => 'bg-[rgba(0,217,181,0.1)]', 'icon' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25'],
    'final'      => ['color' => 'text-[#8b5cf6]', 'bg' => 'bg-[rgba(139,92,246,0.1)]','icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'milestone'  => ['color' => 'text-[#b4b6c0]', 'bg' => 'bg-[rgba(180,182,192,0.1)]','icon' => 'M9 12.75L11.25 15 15 9.75'],
];

$totalPct = collect($rows)->sum(fn ($r) => (float) ($r['percentage'] ?? 0));
@endphp

<div {{ $attributes->merge(['class' => 'bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]']) }}>
    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h3 class="text-[16px] font-semibold text-white">{{ $title }}</h3>
            @if($subtitle)
            <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @if($total)
        <div class="text-end">
            <p class="text-[12px] text-[#b4b6c0]">Total</p>
            <p class="text-[16px] font-semibold text-[#00d9b5]">{{ $total }}</p>
        </div>
        @endif
    </div>

    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] overflow-hidden">
        {{-- Head --}}
        <div class="grid {{ $compact ? 'grid-cols-[1fr_90px_140px]' : 'grid-cols-[40px_1fr_90px_140px]' }} gap-3 px-4 py-3 border-b border-[rgba(255,255,255,0.08)] bg-[rgba(255,255,255,0.02)]">
            @if(!$compact)<span></span>@endif
            <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider">Milestone</p>
            <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider text-end">%</p>
            <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider text-end">Amount</p>
        </div>

        {{-- Rows --}}
        @forelse($rows as $row)
            @php
                $stage = $row['stage'] ?? 'milestone';
                $s = $stageIcons[$stage] ?? $stageIcons['milestone'];
            @endphp
            <div class="grid {{ $compact ? 'grid-cols-[1fr_90px_140px]' : 'grid-cols-[40px_1fr_90px_140px]' }} gap-3 px-4 py-3 border-b border-[rgba(255,255,255,0.06)] last:border-b-0 items-center">
                @if(!$compact)
                <div class="w-8 h-8 rounded-[8px] {{ $s['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 {{ $s['color'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
                </div>
                @endif
                <p class="text-[14px] font-medium text-white truncate">{{ $row['milestone'] }}</p>
                <p class="text-[13px] text-white text-end font-medium">{{ rtrim(rtrim(number_format((float) $row['percentage'], 2), '0'), '.') }}%</p>
                <p class="text-[14px] font-semibold text-[#00d9b5] text-end">{{ $row['amount'] }}</p>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-[13px] text-[#b4b6c0]">No payment schedule defined.</div>
        @endforelse

        {{-- Footer total row --}}
        @if(!empty($rows))
        <div class="grid {{ $compact ? 'grid-cols-[1fr_90px_140px]' : 'grid-cols-[40px_1fr_90px_140px]' }} gap-3 px-4 py-3 border-t border-[rgba(255,255,255,0.08)] bg-[rgba(255,255,255,0.02)] items-center">
            @if(!$compact)<span></span>@endif
            <p class="text-[13px] font-medium text-white">Total</p>
            <p class="text-[13px] font-semibold {{ abs($totalPct - 100) < 0.01 ? 'text-[#00d9b5]' : 'text-[#ffb020]' }} text-end">{{ rtrim(rtrim(number_format($totalPct, 2), '0'), '.') }}%</p>
            <p class="text-[13px] font-semibold text-[#00d9b5] text-end">{{ $total ?? '' }}</p>
        </div>
        @endif
    </div>
</div>
