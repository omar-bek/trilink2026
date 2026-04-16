@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', $pr['title'])

@php
$priorityPill = [
    'high'     => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'bar' => 'from-[#ff4d7f] to-[#ff7da6]'],
    'medium'   => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'bar' => 'from-[#ffb020] to-[#ffc94d]'],
    'standard' => ['bg' => 'bg-accent/10',    'text' => 'text-accent',    'border' => 'border-accent/20',    'bar' => 'from-accent to-[#6b91ff]'],
];
$prio = $priorityPill[$pr['priority']] ?? $priorityPill['standard'];

// Lifecycle steps — shows where this PR sits in the procurement flow
$lifecycleSteps = [
    ['label' => __('pr.step_created'),  'done' => true],
    ['label' => __('pr.step_approved'), 'done' => in_array($pr['status'], ['approved', 'submitted', 'open'])],
    ['label' => __('pr.step_rfq'),      'done' => $pr['rfq_generated'] || $pr['rfq_count'] > 0],
    ['label' => __('pr.step_bids'),     'done' => $pr['bid_count'] > 0],
];
$lifecyclePct = collect($lifecycleSteps)->filter(fn($s) => $s['done'])->count() / count($lifecycleSteps) * 100;
@endphp

@section('content')

{{-- ============================================================
     HERO HEADER — unified card with ID, status, priority, title,
     lifecycle progress, and action buttons
     ============================================================ --}}
<section class="relative overflow-hidden bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px] mb-6">
    {{-- Priority accent bar --}}
    <div class="absolute top-0 bottom-0 start-0 w-[3px] bg-gradient-to-b {{ $prio['bar'] }}" aria-hidden="true"></div>

    <div class="relative">
        {{-- Top row: back + actions --}}
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <a href="{{ route('dashboard.purchase-requests') }}" class="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-primary transition-colors">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('pr.title') }}
            </a>
            <div class="flex items-center gap-2 flex-wrap">
                @if($pr['can_edit'])
                <a href="{{ route('dashboard.purchase-requests.create') }}"
                   class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] text-[12px] font-semibold text-primary bg-page border border-th-border hover:border-accent/40 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                    {{ __('common.edit') }}
                </a>
                @endif
                @if($pr['can_delete'])
                <form method="POST" action="{{ route('dashboard.purchase-requests.destroy', ['id' => $pr['numeric_id']]) }}" onsubmit="return confirm('{{ __('pr.confirm_delete') }}');">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] text-[12px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                        {{ __('common.delete') }}
                    </button>
                </form>
                @endif
                @if($pr['can_approve'])
                <form method="POST" action="{{ route('dashboard.purchase-requests.approve', ['id' => $pr['numeric_id']]) }}" onsubmit="return confirm('{{ __('pr.confirm_approve') }}');" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-[10px] text-[12px] font-bold text-white bg-[#00d9b5] hover:bg-[#00b894] shadow-[0_6px_20px_-8px_rgba(0,217,181,0.5)] transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
                        {{ __('pr.approve_action') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('dashboard.purchase-requests.reject', ['id' => $pr['numeric_id']]) }}"
                      onsubmit="var r = prompt('{{ __('pr.reject_reason_prompt') }}'); if (r === null) return false; this.querySelector('input[name=reason]').value = r; return confirm('{{ __('pr.confirm_reject') }}');" class="inline">
                    @csrf
                    <input type="hidden" name="reason" value="">
                    <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-[10px] text-[12px] font-bold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 hover:bg-[#ff4d7f]/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('pr.reject_action') }}
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Identity chips --}}
        <div class="flex items-center gap-2 flex-wrap mb-3">
            <span class="text-[11px] font-mono text-muted px-2 h-[22px] inline-flex items-center rounded-md bg-page border border-th-border">{{ $pr['id'] }}</span>
            <x-dashboard.status-badge :status="$pr['status']" />
            <span class="inline-flex items-center px-2.5 h-[22px] rounded-full text-[10px] font-bold border {{ $prio['bg'] }} {{ $prio['text'] }} {{ $prio['border'] }}">
                {{ $pr['priority_label'] }}
            </span>
        </div>

        {{-- Title --}}
        <h1 class="text-[24px] sm:text-[30px] font-bold text-primary leading-[1.15] tracking-[-0.02em] mb-1">{{ $pr['title'] }}</h1>
        @if($pr['description'])
        <p class="text-[13px] text-muted mt-1 max-w-3xl leading-relaxed">{{ $pr['description'] }}</p>
        @endif

        {{-- Lifecycle progress --}}
        <div class="mt-6 pt-5 border-t border-th-border">
            <div class="flex items-center justify-between mb-3">
                <p class="text-[10px] uppercase tracking-wider text-faint font-semibold">{{ __('pr.lifecycle') ?? 'Procurement Lifecycle' }}</p>
                <p class="text-[11px] font-bold text-accent tabular-nums">{{ (int)$lifecyclePct }}%</p>
            </div>
            <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden mb-3">
                <div class="h-full bg-gradient-to-r from-accent to-[#00d9b5] rounded-full transition-all duration-500" style="width: {{ $lifecyclePct }}%"></div>
            </div>
            <div class="grid grid-cols-4 gap-2">
                @foreach($lifecycleSteps as $step)
                <div class="flex items-center gap-1.5">
                    @if($step['done'])
                    <svg class="w-3.5 h-3.5 text-[#00d9b5] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                    <svg class="w-3.5 h-3.5 text-faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                    @endif
                    <span class="text-[11px] {{ $step['done'] ? 'text-primary font-semibold' : 'text-faint' }} truncate">{{ $step['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ====================== MAIN COLUMN ====================== --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- General Information --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-accent" aria-hidden="true"></div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('pr.general_information') }}</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                @php
                $fields = [
                    ['icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0', 'label' => __('pr.created_by'), 'value' => $pr['created_by'], 'color' => ''],
                    ['icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25', 'label' => __('pr.created_date'), 'value' => $pr['created_date'], 'color' => ''],
                    ['icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15', 'label' => __('pr.department'), 'value' => $pr['department'], 'color' => ''],
                    ['icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.343-4-3s1.79-3 4-3 4 1.343 4 3', 'label' => __('pr.total_budget'), 'value' => $pr['budget'], 'color' => 'text-[#00d9b5]'],
                    ['icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => __('pr.expected_delivery'), 'value' => $pr['delivery'] ?: '—', 'color' => ''],
                    ['icon' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z', 'label' => __('pr.delivery_location'), 'value' => $pr['location'], 'color' => ''],
                ];
                @endphp
                @foreach($fields as $field)
                <div>
                    <p class="flex items-center gap-1.5 text-[10px] text-faint uppercase tracking-wider font-semibold mb-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $field['icon'] }}"/></svg>
                        {{ $field['label'] }}
                    </p>
                    <p class="text-[14px] font-semibold {{ $field['color'] ?: 'text-primary' }}">{{ $field['value'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Line Items — table format for better scannability --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-[#00d9b5]" aria-hidden="true"></div>
                <h3 class="text-[16px] font-bold text-primary">{{ __('pr.line_items') }}</h3>
                <span class="ms-auto text-[11px] text-muted font-semibold tabular-nums">{{ count($pr['items']) }} {{ count($pr['items']) === 1 ? __('pr.item') : __('pr.items_count') }}</span>
            </div>
            @if(count($pr['items']))
            <div class="overflow-x-auto -mx-[17px] sm:-mx-[25px] px-[17px] sm:px-[25px]">
                <table class="w-full text-[13px]" role="table">
                    <thead>
                        <tr class="border-b border-th-border">
                            <th scope="col" class="text-start text-[10px] font-bold text-faint uppercase tracking-wider pb-3 w-8">#</th>
                            <th scope="col" class="text-start text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('pr.item_name') ?? 'Item' }}</th>
                            <th scope="col" class="text-end text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('pr.quantity') }}</th>
                            <th scope="col" class="text-end text-[10px] font-bold text-faint uppercase tracking-wider pb-3">{{ __('pr.estimated_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pr['items'] as $item)
                        <tr class="border-b border-th-border last:border-b-0 hover:bg-page/50 transition-colors">
                            <td class="py-3 text-muted tabular-nums">{{ $item['n'] }}</td>
                            <td class="py-3">
                                <p class="text-[14px] font-medium text-primary">{{ $item['name'] }}</p>
                                @if($item['desc'])
                                <p class="text-[11px] text-muted mt-0.5 line-clamp-1">{{ $item['desc'] }}</p>
                                @endif
                            </td>
                            <td class="py-3 text-end text-body tabular-nums">{{ $item['qty'] }}</td>
                            <td class="py-3 text-end font-bold {{ $item['has_price'] ? 'text-[#00d9b5]' : 'text-muted' }} tabular-nums">{{ $item['price'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="pt-4 text-end text-[11px] uppercase tracking-wider text-faint font-semibold">{{ __('pr.total_budget') }}</td>
                            <td class="pt-4 text-end text-[18px] font-bold text-[#00d9b5] tabular-nums">{{ $pr['budget'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            <p class="text-[13px] text-muted">{{ __('pr.no_items') }}</p>
            @endif
        </div>

        {{-- Related RFQs --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-5 rounded-full bg-[#8b5cf6]" aria-hidden="true"></div>
                    <h3 class="text-[16px] font-bold text-primary">{{ __('pr.related_rfqs') }}</h3>
                </div>
                <span class="text-[11px] text-muted font-semibold tabular-nums">{{ __('pr.rfqs_created', ['count' => $pr['rfq_count']]) }}</span>
            </div>

            @if($pr['status'] === 'approved' && $pr['rfq_count'] === 0)
            {{-- Convert to RFQ CTA — shown only when PR is approved but no RFQ exists yet --}}
            <div class="bg-accent/5 border border-accent/20 rounded-[12px] p-5 mb-4 flex items-start gap-3">
                <div class="w-10 h-10 rounded-[10px] bg-accent/15 text-accent flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[14px] font-bold text-primary">{{ __('pr.ready_for_rfq') ?? 'Ready to create RFQ' }}</p>
                    <p class="text-[12px] text-muted mt-0.5">{{ __('pr.ready_for_rfq_desc') ?? 'This purchase request has been approved. Create an RFQ to start collecting bids from suppliers.' }}</p>
                </div>
                <a href="{{ route('dashboard.purchase-requests.create') }}"
                   class="inline-flex items-center gap-2 h-10 px-4 rounded-[10px] text-[13px] font-bold text-white bg-accent hover:bg-accent-h shadow-[0_6px_20px_-8px_rgba(79,124,255,0.5)] transition-all flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25"/></svg>
                    {{ __('pr.create_rfq') ?? 'Create RFQ' }}
                </a>
            </div>
            @endif

            @if(count($pr['related_rfqs']))
            <div class="space-y-3">
                @foreach($pr['related_rfqs'] as $rfq)
                <a href="{{ route('dashboard.rfqs.show', ['id' => $rfq['numeric_id']]) }}"
                   class="block bg-page border border-th-border rounded-[12px] p-4 hover:border-accent/30 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-mono text-muted">{{ $rfq['id'] }}</span>
                            <x-dashboard.status-badge :status="$rfq['status']" />
                            <span class="text-[10px] font-bold text-[#8b5cf6] bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 rounded-full px-2 py-0.5">{{ $rfq['tag'] }}</span>
                        </div>
                        <div class="text-end">
                            <p class="text-[18px] font-bold text-primary leading-none tabular-nums">{{ $rfq['bids'] }}</p>
                            <p class="text-[10px] text-muted mt-1">{{ __('pr.bids_received') }}</p>
                        </div>
                    </div>
                    <p class="text-[14px] font-bold text-accent mb-2">{{ $rfq['title'] }}</p>
                    <div class="flex items-center gap-4 text-[11px] text-muted">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25"/></svg>
                            {{ __('pr.created') }} {{ $rfq['created'] }}
                        </span>
                        @if($rfq['deadline'])
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('pr.deadline') }} {{ $rfq['deadline'] }}
                        </span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
            @else
                @if($pr['status'] !== 'approved')
                <p class="text-[13px] text-muted">{{ __('pr.no_rfqs_yet') }}</p>
                @endif
            @endif
        </div>
    </div>

    {{-- ====================== SIDEBAR ====================== --}}
    <aside class="space-y-5">

        {{-- Quick Actions --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-5 rounded-full bg-accent" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('pr.quick_actions') }}</h3>
            </div>
            <div class="space-y-2.5">
                <a href="{{ route('dashboard.rfqs') }}"
                   class="flex items-center gap-3 w-full h-11 px-4 rounded-[12px] bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('pr.view_all_rfqs') }}
                </a>
                <a href="{{ route('dashboard.bids') }}"
                   class="flex items-center gap-3 w-full h-11 px-4 rounded-[12px] bg-page border border-th-border text-[13px] font-semibold text-primary hover:border-accent/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ __('pr.view_bids') }} ({{ $pr['bid_count'] }})
                </a>
                <button type="button" onclick="window.print()"
                        class="flex items-center gap-3 w-full h-11 px-4 rounded-[12px] bg-page border border-th-border text-[13px] font-semibold text-primary hover:border-accent/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    {{ __('pr.print_request') }}
                </button>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-5 rounded-full bg-[#00d9b5]" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('pr.timeline') }}</h3>
            </div>
            <div class="space-y-1">
                @forelse($pr['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)
                    <div class="absolute start-3 top-6 w-0.5 h-[calc(100%-1rem)] bg-th-border"></div>
                    @endif
                    <div class="w-6 h-6 rounded-full {{ $event['done'] ? 'bg-[#00d9b5]' : 'bg-elevated border border-th-border' }} flex items-center justify-center flex-shrink-0 z-10">
                        @if($event['done'])
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        @else
                        <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 pb-5">
                        <p class="text-[13px] font-bold text-primary">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted">{{ $event['who'] }}</p>
                        <p class="text-[10px] text-faint">{{ $event['when'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Additional Services --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[17px] sm:p-[25px]">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-1 h-5 rounded-full bg-[#8b5cf6]" aria-hidden="true"></div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('pr.additional_services') }}</h3>
            </div>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] {{ $pr['additional_services']['logistics'] ? 'bg-[#00d9b5]/10 text-[#00d9b5]' : 'bg-elevated text-muted' }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9-1.5h12.75m0 0V8.25c0-.414-.336-.75-.75-.75H6.75a.75.75 0 00-.75.75v9m12 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0M21 12.75H18M3 12.75h12.75"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-primary">{{ __('pr.logistics_services') }}</p>
                        <p class="text-[11px] {{ $pr['additional_services']['logistics'] ? 'text-[#00d9b5] font-semibold' : 'text-muted' }}">
                            {{ $pr['additional_services']['logistics'] ? __('pr.required') : __('pr.not_required') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] {{ $pr['additional_services']['clearance'] ? 'bg-[#8b5cf6]/10 text-[#8b5cf6]' : 'bg-elevated text-muted' }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-primary">{{ __('pr.customs_clearance') }}</p>
                        <p class="text-[11px] {{ $pr['additional_services']['clearance'] ? 'text-[#8b5cf6] font-semibold' : 'text-muted' }}">
                            {{ $pr['additional_services']['clearance'] ? __('pr.required') : __('pr.not_required') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

@endsection
