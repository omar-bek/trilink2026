@extends('layouts.dashboard', ['active' => 'settings'])

@section('title', __('privacy.dashboard_title'))

@section('content')

<div class="mb-8">
    <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('privacy.dashboard_title') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('privacy.dashboard_subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">
        {{ session('status') }}
    </div>
@endif
@if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[13px] text-[#ff4d7f]">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- DSAR / Data Export --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-[18px] font-bold text-primary">{{ __('privacy.export_card_title') }}</h2>
                    <p class="text-[13px] text-muted mt-1 leading-relaxed">{{ __('privacy.export_card_subtitle') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('dashboard.privacy.export') }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
                    {{ __('privacy.request_export') }}
                </button>
            </form>

            @php
                $exports = $requests->where('request_type', 'data_export')->take(3);
            @endphp
            @if($exports->isNotEmpty())
                <div class="mt-5 pt-5 border-t border-th-border">
                    <p class="text-[12px] uppercase tracking-wider font-semibold text-muted mb-3">{{ __('privacy.recent_exports') }}</p>
                    <div class="space-y-2">
                        @foreach($exports as $r)
                            <div class="flex items-center justify-between bg-page border border-th-border rounded-xl px-4 py-3">
                                <div>
                                    <p class="text-[13px] font-semibold text-primary">{{ __('privacy.status_' . $r->status) }}</p>
                                    <p class="text-[11px] text-muted">{{ $r->requested_at?->format('d M Y, H:i') }}</p>
                                </div>
                                @if($r->status === 'completed' && !empty($r->fulfillment_metadata['archive_path']))
                                    <a href="{{ route('dashboard.privacy.export.download', $r->id) }}"
                                       class="text-[12px] font-semibold text-accent hover:underline">{{ __('privacy.download') }}</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right to Erasure --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-[18px] font-bold text-primary">{{ __('privacy.erasure_card_title') }}</h2>
                    <p class="text-[13px] text-muted mt-1 leading-relaxed">{{ __('privacy.erasure_card_subtitle') }}</p>
                </div>
            </div>

            @php
                $hardBlockers = collect($erasureBlockers)->reject(fn ($b) => str_starts_with($b, 'Note:'))->all();
                $notes = collect($erasureBlockers)->filter(fn ($b) => str_starts_with($b, 'Note:'))->all();
                $pendingErasure = $requests->firstWhere(fn ($r) => $r->isErasure() && $r->isOpen());
            @endphp

            @if(!empty($hardBlockers))
                <div class="mt-4 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30">
                    <p class="text-[12px] font-semibold text-[#ff4d7f] mb-2">{{ __('privacy.erasure_blockers') }}</p>
                    <ul class="text-[12px] text-body space-y-1 list-disc ps-5">
                        @foreach($hardBlockers as $b)
                            <li>{{ $b }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($notes))
                <div class="mt-4 px-4 py-3 rounded-xl bg-[#ffb020]/10 border border-[#ffb020]/30">
                    <ul class="text-[12px] text-body space-y-1 list-disc ps-5">
                        @foreach($notes as $n)
                            <li>{{ $n }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($pendingErasure)
                <div class="mt-4 px-4 py-3 rounded-xl bg-[#ffb020]/10 border border-[#ffb020]/30">
                    <p class="text-[13px] font-semibold text-primary">
                        {{ __('privacy.erasure_pending', ['date' => $pendingErasure->scheduled_for?->format('d M Y')]) }}
                    </p>
                    <form method="POST" action="{{ route('dashboard.privacy.erasure.cancel', $pendingErasure->id) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-[12px] font-semibold text-accent hover:underline">
                            {{ __('privacy.cancel_erasure') }}
                        </button>
                    </form>
                </div>
            @elseif(empty($hardBlockers))
                <form method="POST" action="{{ route('dashboard.privacy.erasure') }}" class="mt-4"
                      onsubmit="return confirm('{{ __('privacy.erasure_confirm') }}')">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-[#ff4d7f] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 hover:bg-[#ff4d7f]/15 transition-colors">
                        {{ __('privacy.request_erasure') }}
                    </button>
                </form>
            @endif
        </div>

        {{-- Consent log --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h2 class="text-[18px] font-bold text-primary mb-1">{{ __('privacy.consents_title') }}</h2>
            <p class="text-[13px] text-muted mb-5">{{ __('privacy.consents_subtitle') }}</p>

            <div class="space-y-3">
                @foreach(['privacy_policy', 'data_processing', 'cookies_essential', 'cookies_analytics', 'marketing_email', 'third_party_share'] as $type)
                    @php $active = $activeConsents->get($type); @endphp
                    <div class="flex items-center justify-between bg-page border border-th-border rounded-xl px-4 py-3">
                        <div class="flex-1">
                            <p class="text-[13px] font-semibold text-primary">{{ __('privacy.consent_type_' . $type) }}</p>
                            @if($active)
                                <p class="text-[11px] text-muted">
                                    {{ __('privacy.granted_on') }} {{ $active->granted_at?->format('d M Y, H:i') }}
                                    @if($active->ip_address) · IP {{ $active->ip_address }} @endif
                                </p>
                            @else
                                <p class="text-[11px] text-muted">{{ __('privacy.not_granted') }}</p>
                            @endif
                        </div>
                        @if($type !== 'cookies_essential')
                            @if($active)
                                <form method="POST" action="{{ route('dashboard.privacy.consents.withdraw', $type) }}">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-semibold text-[#ff4d7f] hover:underline">{{ __('privacy.withdraw') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('dashboard.privacy.consents.grant', $type) }}">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-semibold text-accent hover:underline">{{ __('privacy.grant') }}</button>
                                </form>
                            @endif
                        @else
                            <span class="text-[10px] uppercase tracking-wider text-muted font-semibold">{{ __('privacy.required') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('privacy.residency_title') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[11px] uppercase tracking-wider text-muted">{{ __('privacy.region') }}</dt>
                    <dd class="font-mono font-semibold text-primary mt-0.5">{{ $dataResidency['region'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] uppercase tracking-wider text-muted">{{ __('privacy.basis') }}</dt>
                    <dd class="font-semibold text-primary mt-0.5">{{ $dataResidency['adequacy_basis'] }}</dd>
                </div>
            </dl>

            @if(!empty($dataResidency['sub_processors']))
                <div class="pt-4 mt-4 border-t border-th-border">
                    <p class="text-[11px] uppercase tracking-wider text-muted mb-2">{{ __('privacy.sub_processors') }}</p>
                    <ul class="text-[12px] text-body space-y-1.5">
                        @foreach($dataResidency['sub_processors'] as $p)
                            <li class="flex items-center justify-between">
                                <span>{{ $p['name'] }}</span>
                                <span class="font-mono text-muted text-[10px]">{{ $p['location'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="pt-4 mt-4 border-t border-th-border">
                <a href="{{ route('public.privacy') }}" class="text-[12px] font-semibold text-accent hover:underline">{{ __('privacy.read_full_policy') }} →</a>
                <br>
                <a href="{{ route('public.dpa') }}" class="text-[12px] font-semibold text-accent hover:underline">{{ __('privacy.read_dpa') }} →</a>
            </div>
        </div>

        @if(!empty($dataResidency['dpo']['email']))
            <div class="bg-surface border border-th-border rounded-2xl p-6">
                <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('privacy.contact_dpo') }}</h3>
                <p class="text-[13px] text-body">{{ $dataResidency['dpo']['name'] }}</p>
                <a href="mailto:{{ $dataResidency['dpo']['email'] }}" class="text-[12px] text-accent hover:underline">{{ $dataResidency['dpo']['email'] }}</a>
            </div>
        @endif
    </div>
</div>

@endsection
