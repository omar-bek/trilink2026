@extends('layouts.dashboard', ['active' => 'admin-oversight'])
@section('title', __('admin.oversight.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.oversight.title')" :subtitle="__('admin.oversight.read_only_subtitle')" />

<x-admin.navbar active="oversight" />

{{-- ─────────────────────── Scope segmented control ─────────────────────── --}}
@php
$scopeMeta = [
    'purchase_requests' => [
        'label' => __('admin.oversight.purchase_requests'),
        'count' => $totals['purchase_requests'],
        'color' => '#4f7cff',
        'icon'  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    ],
    'rfqs' => [
        'label' => __('admin.metric.rfqs'),
        'count' => $totals['rfqs'],
        'color' => '#8B5CF6',
        'icon'  => 'M9 12h6m-6 4h6M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    ],
    'bids' => [
        'label' => __('admin.metric.bids'),
        'count' => $totals['bids'],
        'color' => '#00d9b5',
        'icon'  => 'M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.84L3 20l1.34-3.36A7.97 7.97 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    ],
    'contracts' => [
        'label' => __('admin.metric.contracts'),
        'count' => $totals['contracts'],
        'color' => '#14B8A6',
        'icon'  => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M9 15l2 2 4-4',
    ],
    'payments' => [
        'label' => __('admin.metric.payments'),
        'count' => $totals['payments'],
        'color' => '#ffb020',
        'icon'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    ],
    'shipments' => [
        'label' => __('admin.metric.shipments'),
        'count' => $totals['shipments'],
        'color' => '#00d9b5',
        'icon'  => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1',
    ],
    'disputes' => [
        'label' => __('admin.metric.disputes'),
        'count' => $totals['disputes'],
        'color' => '#ff4d7f',
        'icon'  => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z',
    ],
];
@endphp

<div class="bg-surface border border-th-border rounded-[16px] p-[6px] mb-6">
    <div class="flex items-center gap-1 overflow-x-auto scrollbar-none">
        @foreach($scopeMeta as $key => $meta)
        @php $isActive = $scope === $key; @endphp
        <a href="{{ route('admin.oversight.index', ['scope' => $key]) }}"
           class="group inline-flex items-center gap-2 px-4 h-11 rounded-[12px] text-[13px] font-semibold whitespace-nowrap flex-shrink-0 transition-all
                  {{ $isActive
                        ? 'bg-accent text-white shadow-[0_4px_14px_rgba(79,124,255,0.35)]'
                        : 'text-muted hover:text-primary hover:bg-surface-2' }}">
            <svg class="w-[15px] h-[15px] flex-shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/></svg>
            <span>{{ $meta['label'] }}</span>
            <span class="text-[11px] font-bold rounded-full px-2 py-0.5 {{ $isActive ? 'bg-white/20 text-white' : 'bg-surface-2 text-faint' }}">
                {{ number_format($meta['count']) }}
            </span>
        </a>
        @endforeach
    </div>
</div>

{{-- ─────────────────────── Search bar (scope-aware) ─────────────────────── --}}
<form method="GET" class="bg-surface border border-th-border rounded-[16px] p-[17px] mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="scope" value="{{ $scope }}" />
    <div class="flex-1 min-w-[200px] relative">
        <svg class="w-4 h-4 text-muted absolute start-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('common.search') }}"
               class="w-full bg-surface-2 border border-th-border rounded-[12px] ps-11 pe-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
    </div>
    <button type="submit"
            class="inline-flex items-center justify-center gap-2 bg-accent text-white rounded-[12px] px-5 h-11 text-[13px] font-bold hover:bg-accent-h transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        {{ __('common.filter') }}
    </button>
    @if($q !== '')
    <a href="{{ route('admin.oversight.index', ['scope' => $scope]) }}"
       class="inline-flex items-center gap-2 px-4 h-11 rounded-[12px] text-[12px] font-semibold text-muted hover:text-primary border border-th-border bg-surface-2 transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ __('common.clear') }}
    </a>
    @endif
</form>

{{-- ─────────────────────── Oversight table (scope-driven) ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">

    @php
        $thCls = 'text-start px-5 py-4 font-bold';
        $thEnd = 'text-end px-5 py-4 font-bold';
        $tdCls = 'px-5 py-4';
    @endphp

    @switch($scope)
        @case('purchase_requests')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.title_col') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.buyer') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.company') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.budget') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $pr)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-primary font-semibold">{{ $pr->title }}</td>
                    <td class="{{ $tdCls }} text-body">{{ trim(($pr->buyer?->first_name ?? '') . ' ' . ($pr->buyer?->last_name ?? '')) ?: '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $pr->company?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$pr->status?->value ?? 'draft'" /></td>
                    <td class="{{ $tdCls }} text-end text-primary font-mono">{{ $pr->budget ? number_format((float) $pr->budget, 0) . ' ' . $pr->currency : '—' }}</td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $pr->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 6])
                @endforelse
            </tbody>
        </table>
        @break

        @case('rfqs')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.rfq_number') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.title_col') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.company') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.deadline') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $rfq)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $rfq->rfq_number }}</td>
                    <td class="{{ $tdCls }} text-primary font-semibold">{{ $rfq->title }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $rfq->company?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$rfq->status?->value ?? 'draft'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $rfq->deadline?->format('Y-m-d') ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $rfq->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 6])
                @endforelse
            </tbody>
        </table>
        @break

        @case('bids')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.rfq') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.provider') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.price') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $bid)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $bid->rfq?->rfq_number ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $bid->company?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-end text-primary font-mono">{{ number_format((float) $bid->price, 0) }} {{ $bid->currency }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$bid->status?->value ?? 'draft'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $bid->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 5])
                @endforelse
            </tbody>
        </table>
        @break

        @case('contracts')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.contract_number') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.title_col') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.buyer_company') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.amount') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $contract)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $contract->contract_number }}</td>
                    <td class="{{ $tdCls }} text-primary font-semibold">{{ $contract->title }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $contract->buyerCompany?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-end text-primary font-mono">{{ number_format((float) $contract->total_amount, 0) }} {{ $contract->currency }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$contract->status?->value ?? 'draft'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $contract->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 6])
                @endforelse
            </tbody>
        </table>
        @break

        @case('payments')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.contract') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.buyer_company') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.recipient') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.amount') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $payment)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $payment->contract?->contract_number ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $payment->company?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $payment->recipientCompany?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-end text-primary font-mono">{{ number_format((float) $payment->total_amount, 0) }} {{ $payment->currency }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$payment->status?->value ?? 'pending'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $payment->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 6])
                @endforelse
            </tbody>
        </table>
        @break

        @case('shipments')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.tracking_number') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.contract') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.buyer_company') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $shipment)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $shipment->tracking_number }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $shipment->contract?->contract_number ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ $shipment->company?->name ?? '—' }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$shipment->status?->value ?? 'preparing'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $shipment->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 5])
                @endforelse
            </tbody>
        </table>
        @break

        @case('disputes')
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="{{ $thCls }}">{{ __('admin.oversight.title_col') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.contract') }}</th>
                    <th class="{{ $thCls }}">{{ __('admin.oversight.raised_by') }}</th>
                    <th class="{{ $thCls }}">{{ __('common.status') }}</th>
                    <th class="{{ $thEnd }}">{{ __('common.created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($rows as $dispute)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="{{ $tdCls }} text-primary font-semibold">{{ $dispute->title }}</td>
                    <td class="{{ $tdCls }} text-accent font-mono text-[11px]">{{ $dispute->contract?->contract_number ?? '—' }}</td>
                    <td class="{{ $tdCls }} text-body">{{ trim(($dispute->raisedByUser?->first_name ?? '') . ' ' . ($dispute->raisedByUser?->last_name ?? '')) ?: '—' }}</td>
                    <td class="{{ $tdCls }}"><x-dashboard.status-badge :status="$dispute->status?->value ?? 'open'" /></td>
                    <td class="{{ $tdCls }} text-end text-[11px] text-muted">{{ $dispute->created_at?->diffForHumans() }}</td>
                </tr>
                @empty
                @include('dashboard.admin.oversight._empty_row', ['cols' => 5])
                @endforelse
            </tbody>
        </table>
        @break
    @endswitch

    </div>
    <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $rows->links() }}</div>
</div>

@endsection
