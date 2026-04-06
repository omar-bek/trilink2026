@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.details'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <a href="{{ route('dashboard.contracts') }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('common.back_to_dashboard') }}
        </a>
        <p class="text-[12px] font-mono text-muted mb-1">{{ $contract['id'] }}</p>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary">{{ __('contracts.details') }}</h1>
        <p class="text-[14px] text-muted mt-1">{{ $contract['title'] }}</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-surface border border-th-border hover:bg-surface-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg>
            {{ __('contracts.download') }}
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Contract Status --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[16px] font-bold text-primary">{{ __('contracts.contract_status') }}</h3>
                <x-dashboard.status-badge :status="$contract['status']" />
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('contracts.total_value') }}</p>
                    <p class="text-[18px] font-bold text-[#10B981]">{{ $contract['amount'] }}</p>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('common.progress') }}</p>
                    <div class="flex items-center gap-2">
                        <p class="text-[18px] font-bold text-primary">{{ $contract['progress'] }}%</p>
                        <div class="flex-1 h-1.5 bg-elevated rounded-full overflow-hidden"><div class="h-full bg-accent rounded-full" style="width: {{ $contract['progress'] }}%"></div></div>
                    </div>
                </div>
                <div class="bg-page border border-th-border rounded-xl p-4">
                    <p class="text-[11px] text-muted mb-1">{{ __('contracts.days_remaining') }}</p>
                    <p class="text-[18px] font-bold text-primary">{{ $contract['days_remaining'] ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Parties Involved --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.parties') }}</h3>
            <div class="space-y-3">
                @forelse($contract['parties'] as $party)
                <div class="bg-page border border-th-border rounded-xl p-4 flex items-start gap-4">
                    <div class="w-11 h-11 rounded-lg {{ $party['color'] }} text-white font-bold flex items-center justify-center flex-shrink-0">{{ $party['code'] }}</div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <p class="text-[14px] font-bold text-primary">{{ $party['name'] }}</p>
                            <span class="text-[10px] font-medium text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ $party['type'] }}</span>
                        </div>
                        @if($party['contact'])
                            <p class="text-[12px] text-muted">{{ $party['contact'] }}</p>
                        @endif
                        @if($party['signed'])
                            <p class="text-[11px] text-[#10B981] inline-flex items-center gap-1 mt-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                {{ __('contracts.signed_on', ['date' => $party['signed_on']]) }}
                            </p>
                        @else
                            <p class="text-[11px] text-muted inline-flex items-center gap-1 mt-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ __('contracts.awaiting_signature') }}
                            </p>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Payment Milestones --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.payment_milestones') }}</h3>
            <div class="space-y-3">
                @forelse($contract['milestones'] as $milestone)
                    @php
                        $status = $milestone['status'];
                        $wrapClasses = match($status) {
                            'paid'    => 'bg-[#10B981]/5 border border-[#10B981]/20',
                            'pending' => 'bg-[#F59E0B]/5 border border-[#F59E0B]/20',
                            default   => 'bg-page border border-th-border',
                        };
                        $iconBg = match($status) {
                            'paid'    => 'bg-[#10B981]/20',
                            'pending' => 'bg-[#F59E0B]/20',
                            default   => 'bg-surface-2',
                        };
                        $badgeClasses = match($status) {
                            'paid'    => 'text-[#10B981] bg-[#10B981]/10 border border-[#10B981]/20',
                            'pending' => 'text-[#F59E0B] bg-[#F59E0B]/10 border border-[#F59E0B]/20',
                            default   => 'text-muted bg-surface-2 border border-th-border',
                        };
                    @endphp
                    <div class="{{ $wrapClasses }} rounded-xl p-5 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg {{ $iconBg }} flex items-center justify-center flex-shrink-0">
                            @if($status === 'paid')
                                <svg class="w-5 h-5 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($status === 'pending')
                                <svg class="w-5 h-5 text-[#F59E0B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            @else
                                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5"/></svg>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-[14px] font-bold text-primary">{{ $milestone['name'] }}</p>
                                <span class="text-[10px] font-bold {{ $badgeClasses }} rounded-full px-2 py-0.5">{{ $milestone['percentage'] }}%</span>
                            </div>
                            <p class="text-[11px] text-muted">
                                @if($status === 'paid' && $milestone['paid_date'])
                                    {{ __('common.due_date') }}: {{ $milestone['due_date'] }} · {{ __('contracts.paid_on', ['date' => $milestone['paid_date']]) }}
                                @else
                                    {{ __('common.due_date') }}: {{ $milestone['due_date'] ?: '—' }}
                                @endif
                            </p>
                        </div>
                        <div class="text-end">
                            <p class="text-[18px] font-bold text-accent">{{ $milestone['amount'] }}</p>
                            @if($status === 'pending' && $milestone['payment_id'])
                                @can('payment.process')
                                <form method="POST" action="{{ route('dashboard.payments.process', ['id' => $milestone['payment_id']]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="mt-1 px-3 py-1 rounded-lg text-[11px] font-bold text-white bg-accent hover:bg-accent-h">{{ __('contracts.process_payment') }}</button>
                                </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Terms & Conditions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.terms_conditions') }}</h3>
            <div class="space-y-5">
                @forelse($contract['terms_sections'] as $i => $section)
                <div>
                    <h4 class="text-[14px] font-bold text-primary mb-2">{{ ($i + 1) }}. {{ $section['title'] }}</h4>
                    <ul class="space-y-1 text-[13px] text-body ms-4">
                        @foreach($section['items'] as $item)
                        <li>• {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>

            <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id']]) }}" class="w-full mt-6 inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
                {{ __('contracts.view_pdf') }}
            </a>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Quick Actions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.quick_actions') }}</h3>
            <div class="space-y-3">
                @if($contract['has_shipment'] && $contract['shipment_id'])
                <a href="{{ route('dashboard.shipments.show', ['id' => $contract['shipment_id']]) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0"/></svg>
                    {{ __('contracts.track_shipment') }}
                </a>
                @endif
                <a href="{{ route('dashboard.disputes') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                    {{ __('contracts.report_issue') }}
                </a>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-5">{{ __('contracts.timeline') }}</h3>
            <div class="space-y-5">
                @forelse($contract['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)<div class="absolute start-[5px] top-3 w-0.5 h-full bg-th-border"></div>@endif
                    <div class="w-2.5 h-2.5 rounded-full {{ $event['done'] ? 'bg-[#10B981]' : 'bg-th-border' }} mt-1.5 flex-shrink-0 z-10"></div>
                    <div class="flex-1 min-w-0 pb-2">
                        <p class="text-[10px] text-muted">{{ $event['date'] }}</p>
                        <p class="text-[13px] font-bold text-primary">{{ $event['title'] }}</p>
                        <p class="text-[11px] text-muted leading-snug">{{ $event['desc'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Documents --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.documents') }}</h3>
            <div class="space-y-2">
                @forelse($contract['documents'] as $file)
                <div class="bg-page border border-th-border rounded-lg p-3 flex items-center gap-3">
                    <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/></svg>
                    <span class="text-[12px] font-medium text-body flex-1 truncate">{{ $file['name'] }}</span>
                    @if($file['url'])
                        <a href="{{ $file['url'] }}" class="w-6 h-6 rounded text-muted hover:text-primary flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5"/></svg></a>
                    @endif
                </div>
                @empty
                <p class="text-[12px] text-muted">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
