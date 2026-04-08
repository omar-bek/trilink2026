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
                    <p class="text-[18px] font-bold text-[#00d9b5]">{{ $contract['amount'] }}</p>
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
                            <p class="text-[11px] text-[#00d9b5] inline-flex items-center gap-1 mt-1">
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

        {{-- Payment Schedule (milestone breakdown table — same component the bid pages use) --}}
        @if(!empty($contract['payment_schedule']))
        <x-payment-schedule
            :rows="$contract['payment_schedule']"
            :total="$contract['amount']"
            title="{{ __('contracts.payment_schedule') ?? 'Payment Schedule' }}"
            subtitle="{{ __('contracts.payment_schedule_hint') ?? 'Milestone breakdown for this contract.' }}" />
        @endif

        {{-- Payment Milestones (status + actions per milestone) --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('contracts.payment_milestones') }}</h3>
            <div class="space-y-3">
                @forelse($contract['milestones'] as $milestone)
                    @php
                        $status = $milestone['status'];
                        $wrapClasses = match($status) {
                            'paid'    => 'bg-[#00d9b5]/5 border border-[#00d9b5]/20',
                            'pending' => 'bg-[#ffb020]/5 border border-[#ffb020]/20',
                            default   => 'bg-page border border-th-border',
                        };
                        $iconBg = match($status) {
                            'paid'    => 'bg-[#00d9b5]/20',
                            'pending' => 'bg-[#ffb020]/20',
                            default   => 'bg-surface-2',
                        };
                        $badgeClasses = match($status) {
                            'paid'    => 'text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20',
                            'pending' => 'text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/20',
                            default   => 'text-muted bg-surface-2 border border-th-border',
                        };
                    @endphp
                    <div class="{{ $wrapClasses }} rounded-xl p-5 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg {{ $iconBg }} flex items-center justify-center flex-shrink-0">
                            @if($status === 'paid')
                                <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($status === 'pending')
                                <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
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
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                {{ __('contracts.view_pdf') }}
            </a>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">

        {{-- Phase 3 — Escrow panel. Renders for any contract whether or not
             escrow has been activated. When inactive, shows the Activate
             CTA (buyer-only) so the buyer can pre-fund. When active, shows
             held / released / available + the inline deposit + release UI
             plus a recent ledger of escrow events. --}}
        @if(!empty($contract['escrow']))
        @include('dashboard.contracts._escrow-panel', ['escrow' => $contract['escrow'], 'contract_id' => $contract['numeric_id']])
        @endif

        {{-- Quick Actions --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.quick_actions') }}</h3>
            <div class="space-y-3">
                @if($contract['can_sign'])
                <form method="POST" action="{{ route('dashboard.contracts.sign', ['id' => $contract['numeric_id']]) }}"
                      onsubmit="return confirm('{{ __('contracts.confirm_sign') }}');">
                    @csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00b894] shadow-[0_4px_14px_rgba(0,217,181,0.25)]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                        </svg>
                        {{ __('contracts.sign_contract') }}
                    </button>
                </form>
                @endif
                @if($contract['has_shipment'] && $contract['shipment_id'])
                <a href="{{ route('dashboard.shipments.show', ['id' => $contract['shipment_id']]) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    {{ __('contracts.track_shipment') }}
                </a>
                @endif
                <a href="{{ route('dashboard.disputes') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                    {{ __('contracts.report_issue') }}
                </a>

                {{-- Phase 4 / Sprint 18 — Quick reorder. Repopulates the
                     buyer's open cart with this contract's line items.
                     Visible only when the current user is the buyer (the
                     server-side handler enforces this too). --}}
                @if(auth()->user()?->hasPermission('cart.use') && auth()->user()?->company_id === ($contract['buyer_company_id'] ?? null))
                <form method="POST" action="{{ route('dashboard.contracts.reorder', ['id' => $contract['numeric_id']]) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        {{ __('cart.buy_again') }}
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-5">{{ __('contracts.timeline') }}</h3>
            <div class="space-y-5">
                @forelse($contract['timeline'] as $event)
                <div class="flex items-start gap-3 relative">
                    @if(!$loop->last)<div class="absolute start-[5px] top-3 w-0.5 h-full bg-th-border"></div>@endif
                    <div class="w-2.5 h-2.5 rounded-full {{ $event['done'] ? 'bg-[#00d9b5]' : 'bg-th-border' }} mt-1.5 flex-shrink-0 z-10"></div>
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
                    <svg class="w-4 h-4 text-accent flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
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

        {{-- Supplier-uploaded production documents — visible to the buyer. --}}
        @if(!empty($contract['supplier_documents']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.supplier_documents') ?? 'Supplier Documents' }}</h3>
            <div class="space-y-2">
                @foreach($contract['supplier_documents'] as $doc)
                <a href="{{ $doc['url'] }}" class="bg-page border border-th-border rounded-lg p-3 flex items-center gap-3 hover:border-accent/40 transition-colors">
                    <div class="w-8 h-8 rounded bg-[#00d9b5]/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-medium text-primary truncate">{{ $doc['name'] }}</p>
                        <p class="text-[11px] text-muted">{{ $doc['type'] }} · {{ $doc['size'] }} · {{ $doc['uploaded_at'] }}</p>
                    </div>
                    <svg class="w-3.5 h-3.5 text-muted flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Progress update log — buyer wants to see what the supplier posted. --}}
        @if(!empty($contract['progress_log']))
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('contracts.progress_updates') ?? 'Progress Updates' }}</h3>
            <div class="space-y-3">
                @foreach($contract['progress_log'] as $entry)
                <div class="flex items-start gap-3 pb-3 border-b border-th-border last:border-b-0 last:pb-0">
                    <div class="w-8 h-8 rounded-full bg-[#00d9b5]/15 flex items-center justify-center flex-shrink-0 text-[11px] font-semibold text-[#00d9b5]">{{ $entry['percent'] }}%</div>
                    <div class="flex-1 min-w-0">
                        @if($entry['note'])
                        <p class="text-[12px] text-primary leading-[18px]">{{ $entry['note'] }}</p>
                        @else
                        <p class="text-[12px] text-muted italic">Progress updated to {{ $entry['percent'] }}%</p>
                        @endif
                        <p class="text-[11px] text-muted mt-0.5">{{ $entry['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Rate this contract (only after completion) --}}
@if($contract['can_review'])
<div class="mt-6">
    <x-contract-review :contract-id="$contract['numeric_id']" :existing="$contract['existing_review']" />
</div>
@endif

@endsection
