@extends('layouts.app')

@section('title', __('contracts.verify_page_title', ['number' => $contract->contract_number]))

@section('content')

<div class="landing-page">
    <x-landing.navbar />

    <section class="pt-[120px] pb-20 px-6 lg:px-10">
        <div class="max-w-[820px] mx-auto">

            <div class="mb-8">
                <p class="text-[12px] uppercase tracking-wider font-semibold text-accent mb-2">
                    {{ __('contracts.verify_page_eyebrow') }}
                </p>
                <h1 class="text-[36px] sm:text-[44px] font-bold text-primary leading-tight tracking-[-0.02em] mb-3">
                    {{ __('contracts.verify_page_title', ['number' => $contract->contract_number]) }}
                </h1>
                <p class="text-[14px] text-muted">
                    {{ __('contracts.verify_page_subtitle') }}
                </p>
            </div>

            {{-- Headline integrity banner --}}
            @if(empty($signatures))
                <div class="mb-8 rounded-2xl border border-[#525252]/30 bg-[#525252]/10 p-6">
                    <p class="text-[15px] font-bold text-muted">{{ __('contracts.verify_no_signatures') }}</p>
                </div>
            @elseif($all_intact && $all_meet_grade)
                <div class="mb-8 rounded-2xl border-2 border-[#00d9b5]/40 bg-[#00d9b5]/10 p-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-8 h-8 text-[#00d9b5] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                        <div>
                            <p class="text-[18px] font-bold text-[#00d9b5]">{{ __('contracts.verify_intact') }}</p>
                            <p class="text-[13px] text-body mt-1">{{ __('contracts.verify_intact_subtitle') }}</p>
                        </div>
                    </div>
                </div>
            @elseif(!$all_intact)
                <div class="mb-8 rounded-2xl border-2 border-[#ef4444]/40 bg-[#ef4444]/10 p-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-8 h-8 text-[#ef4444] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="text-[18px] font-bold text-[#ef4444]">{{ __('contracts.verify_tampered') }}</p>
                            <p class="text-[13px] text-body mt-1">{{ __('contracts.verify_tampered_subtitle') }}</p>
                        </div>
                    </div>
                </div>
            @else
                {{-- Intact but not all signatures meet the required grade --}}
                <div class="mb-8 rounded-2xl border-2 border-[#ffb020]/40 bg-[#ffb020]/10 p-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-8 h-8 text-[#ffb020] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <div>
                            <p class="text-[18px] font-bold text-[#ffb020]">{{ __('contracts.verify_under_required_grade') }}</p>
                            <p class="text-[13px] text-body mt-1">{{ __('contracts.verify_under_required_grade_subtitle') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Required grade summary --}}
            <section class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
                <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('contracts.verify_required_grade') }}</h2>
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-[12px] font-bold uppercase tracking-wider bg-accent/15 text-accent border border-accent/30">
                        {{ $required_label }}
                    </span>
                </div>
                <p class="text-[13px] text-body leading-relaxed">{{ $required_reason }}</p>
            </section>

            {{-- Per-signature audit table --}}
            <section class="bg-surface border border-th-border rounded-2xl p-6 mb-6">
                <h2 class="text-[18px] font-bold text-primary mb-4">{{ __('contracts.verify_signature_log') }}</h2>

                @if(empty($signatures))
                    <p class="text-[13px] text-muted">{{ __('contracts.verify_no_signatures') }}</p>
                @else
                    <div class="space-y-4">
                        @foreach($signatures as $i => $sig)
                            <div class="bg-page border border-th-border rounded-xl p-4">
                                <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('contracts.verify_signature_n', ['n' => $i + 1]) }}</p>
                                        <p class="text-[13px] font-mono text-primary mt-0.5">{{ $sig['signed_at'] }}</p>
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($sig['hash_matches'])
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-full bg-[#00d9b5]/10 text-[#00d9b5] border border-[#00d9b5]/20">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                                {{ __('contracts.verify_hash_intact') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-full bg-[#ef4444]/10 text-[#ef4444] border border-[#ef4444]/20">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                                {{ __('contracts.verify_hash_mismatch') }}
                                            </span>
                                        @endif
                                        @if($sig['meets_required'])
                                            <span class="inline-flex items-center text-[10px] font-bold px-2 py-1 rounded-full bg-accent/10 text-accent border border-accent/20">
                                                {{ $sig['achieved_label'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-[10px] font-bold px-2 py-1 rounded-full bg-[#ffb020]/10 text-[#ffb020] border border-[#ffb020]/20">
                                                {{ $sig['achieved_label'] }} — {{ __('contracts.verify_below_required') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12px]">
                                    @if($sig['uae_pass_user_id'])
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wider text-muted">{{ __('contracts.verify_uae_pass') }}</dt>
                                        <dd class="font-mono text-primary mt-0.5 break-all">{{ $sig['uae_pass_user_id'] }}</dd>
                                    </div>
                                    @endif
                                    @if($sig['tsp_provider'])
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wider text-muted">{{ __('contracts.verify_tsp') }}</dt>
                                        <dd class="font-mono text-primary mt-0.5">{{ $sig['tsp_provider'] }}</dd>
                                    </div>
                                    @endif
                                    @if($sig['ip_address'])
                                    <div>
                                        <dt class="text-[10px] uppercase tracking-wider text-muted">{{ __('contracts.verify_ip') }}</dt>
                                        <dd class="font-mono text-primary mt-0.5">{{ $sig['ip_address'] }}</dd>
                                    </div>
                                    @endif
                                    @if($sig['hash_at_sign'])
                                    <div class="sm:col-span-2">
                                        <dt class="text-[10px] uppercase tracking-wider text-muted">{{ __('contracts.verify_hash_at_sign') }}</dt>
                                        <dd class="font-mono text-[10px] text-primary mt-0.5 break-all">{{ $sig['hash_at_sign'] }}</dd>
                                    </div>
                                    @endif
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Current canonical hash --}}
            <section class="bg-surface border border-th-border rounded-2xl p-6">
                <h2 class="text-[16px] font-bold text-primary mb-2">{{ __('contracts.verify_canonical_hash') }}</h2>
                <p class="text-[12px] text-muted mb-3">{{ __('contracts.verify_canonical_hash_hint') }}</p>
                <p class="font-mono text-[11px] text-primary break-all bg-page border border-th-border rounded-lg p-3">{{ $current_hash }}</p>
            </section>

        </div>
    </section>

    <x-landing.footer />
</div>

@endsection
