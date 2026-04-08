@extends('layouts.app')

@section('title', __('demo.meta_title'))

@section('content')

<div class="landing-page">
    <x-landing.navbar />

    {{-- ===================== HERO ===================== --}}
    <section class="relative pt-[112px] pb-20 px-6 lg:px-10 overflow-hidden bg-spotlight">
        <div class="max-w-[1100px] mx-auto text-center relative z-10">
            <span class="inline-flex items-center gap-2 t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-2 mb-8 reveal">
                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                {{ __('demo.eyebrow') }}
            </span>
            <h1 class="h-display reveal reveal-delay-1">
                <span class="text-gradient">{!! __('demo.hero_title') !!}</span>
            </h1>
            <p class="mt-6 t-lead max-w-[680px] mx-auto reveal reveal-delay-2">
                {{ __('demo.hero_subtitle') }}
            </p>

            {{-- CTA buttons --}}
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3 reveal reveal-delay-3">
                <a href="#workflow" class="group inline-flex items-center gap-3 px-7 py-3.5 bg-accent hover:bg-accent-h text-white rounded-full text-[15px] font-semibold tracking-[-0.011em] transition-all duration-300 shadow-[0_10px_40px_rgba(59,126,255,0.45)] hover:-translate-y-0.5">
                    {{ __('demo.cta_start_tour') }}
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
                <a href="{{ route('register') }}" class="inline-flex items-center gap-3 px-7 py-3.5 bg-surface-2 hover:bg-elevated border border-th-border text-primary rounded-full text-[15px] font-semibold tracking-[-0.011em] transition-all">
                    {{ __('demo.cta_create_account') }}
                </a>
            </div>

            {{-- Stats strip --}}
            <div class="mt-16 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-[820px] mx-auto reveal">
                @php
                $stats = [
                    ['v' => '13',  'l' => __('demo.stat_user_roles')],
                    ['v' => '7',   'l' => __('demo.stat_workflow_steps')],
                    ['v' => '20+', 'l' => __('demo.stat_modules')],
                    ['v' => 'AR/EN', 'l' => __('demo.stat_bilingual')],
                ];
                @endphp
                @foreach($stats as $s)
                <div class="bg-surface border border-th-border rounded-2xl px-5 py-6">
                    <div class="font-display text-[28px] font-bold tracking-[-0.024em] text-gradient leading-none">{{ $s['v'] }}</div>
                    <div class="mt-2 text-[12px] uppercase tracking-[0.14em] font-semibold text-muted">{{ $s['l'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== WHAT IS TRILINK ===================== --}}
    <section class="py-24 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1100px] mx-auto">
            <div class="grid lg:grid-cols-[1fr_1.4fr] gap-14 items-center">
                <div class="reveal">
                    <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-5">{{ __('demo.section_01_eyebrow') }}</span>
                    <h2 class="h-section text-gradient mb-5">{{ __('demo.section_01_title') }}</h2>
                    <p class="t-lead">
                        {{ __('demo.section_01_body') }}
                    </p>
                </div>

                {{-- Right: highlight cards --}}
                <div class="grid sm:grid-cols-2 gap-4 reveal reveal-delay-1">
                    @php
                    $highlights = [
                        ['t' => __('demo.highlight_unified_t'),    'd' => __('demo.highlight_unified_d')],
                        ['t' => __('demo.highlight_audit_t'),      'd' => __('demo.highlight_audit_d')],
                        ['t' => __('demo.highlight_bilingual_t'),  'd' => __('demo.highlight_bilingual_d')],
                        ['t' => __('demo.highlight_compliance_t'), 'd' => __('demo.highlight_compliance_d')],
                    ];
                    @endphp
                    @foreach($highlights as $h)
                    <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 transition-colors">
                        <div class="w-9 h-9 rounded-[10px] bg-accent/10 border border-accent/20 flex items-center justify-center mb-4">
                            <svg class="w-4 h-4 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <h3 class="h-card mb-1.5">{{ $h['t'] }}</h3>
                        <p class="t-body">{{ $h['d'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== ROLES ===================== --}}
    <section class="py-24 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1280px] mx-auto">
            <div class="text-center mb-14 reveal">
                <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-5">{{ __('demo.section_02_eyebrow') }}</span>
                <h2 class="h-section text-gradient mb-3">{{ __('demo.section_02_title') }}</h2>
                <p class="t-lead max-w-[640px] mx-auto">{{ __('demo.section_02_subtitle') }}</p>
            </div>

            @php
            $roles = [
                ['n' => __('demo.role_company_manager_n'), 'd' => __('demo.role_company_manager_d'), 'svg' => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>'],
                ['n' => __('demo.role_branch_manager_n'),  'd' => __('demo.role_branch_manager_d'),  'svg' => '<path d="M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
                ['n' => __('demo.role_buyer_n'),           'd' => __('demo.role_buyer_d'),           'svg' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>'],
                ['n' => __('demo.role_supplier_n'),        'd' => __('demo.role_supplier_d'),        'svg' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/>'],
                ['n' => __('demo.role_logistics_n'),       'd' => __('demo.role_logistics_d'),       'svg' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>'],
                ['n' => __('demo.role_clearance_n'),       'd' => __('demo.role_clearance_d'),       'svg' => '<path d="M16 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>'],
                ['n' => __('demo.role_finance_n'),         'd' => __('demo.role_finance_d'),         'svg' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>'],
                ['n' => __('demo.role_sales_n'),           'd' => __('demo.role_sales_d'),           'svg' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
                ['n' => __('demo.role_service_provider_n'),'d' => __('demo.role_service_provider_d'),'svg' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-7a9 9 0 0 1 18 0v7a1 1 0 0 1-1 1h-2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>'],
                ['n' => __('demo.role_government_n'),      'd' => __('demo.role_government_d'),      'svg' => '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>'],
                ['n' => __('demo.role_admin_n'),           'd' => __('demo.role_admin_d'),           'svg' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                ['n' => __('demo.role_finance_manager_n'), 'd' => __('demo.role_finance_manager_d'), 'svg' => '<path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
            ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($roles as $i => $r)
                <div class="bg-surface border border-th-border rounded-2xl p-6 hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all reveal reveal-delay-{{ $i % 3 + 1 }}">
                    <div class="flex items-start gap-4">
                        <div class="flex h-[44px] w-[44px] flex-shrink-0 items-center justify-center rounded-[12px] border border-slate-200/90 bg-slate-100 dark:border-white/[0.06] dark:bg-[#0d1018]">
                            <svg class="h-[18px] w-[18px] text-slate-800 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $r['svg'] !!}</svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <h3 class="h-card">{{ $r['n'] }}</h3>
                            </div>
                            <p class="t-body">{{ $r['d'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== END-TO-END WORKFLOW ===================== --}}
    <section id="workflow" class="py-24 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1280px] mx-auto">
            <div class="text-center mb-16 reveal">
                <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-5">{{ __('demo.section_03_eyebrow') }}</span>
                <h2 class="h-section text-gradient mb-3">{{ __('demo.section_03_title') }}</h2>
                <p class="t-lead max-w-[640px] mx-auto">{{ __('demo.section_03_subtitle') }}</p>
            </div>

            @php
            $flow = [
                [
                    'n' => '01',
                    'title' => __('demo.flow_01_title'),
                    'body'  => __('demo.flow_01_body'),
                    'tags'  => [__('demo.flow_01_tag1'), __('demo.flow_01_tag2'), __('demo.flow_01_tag3'), __('demo.flow_01_tag4')],
                    'mock'  => 'pr',
                ],
                [
                    'n' => '02',
                    'title' => __('demo.flow_02_title'),
                    'body'  => __('demo.flow_02_body'),
                    'tags'  => [__('demo.flow_02_tag1'), __('demo.flow_02_tag2'), __('demo.flow_02_tag3'), __('demo.flow_02_tag4')],
                    'mock'  => 'rfq',
                ],
                [
                    'n' => '03',
                    'title' => __('demo.flow_03_title'),
                    'body'  => __('demo.flow_03_body'),
                    'tags'  => [__('demo.flow_03_tag1'), __('demo.flow_03_tag2'), __('demo.flow_03_tag3'), __('demo.flow_03_tag4')],
                    'mock'  => 'bid',
                ],
                [
                    'n' => '04',
                    'title' => __('demo.flow_04_title'),
                    'body'  => __('demo.flow_04_body'),
                    'tags'  => [__('demo.flow_04_tag1'), __('demo.flow_04_tag2'), __('demo.flow_04_tag3'), __('demo.flow_04_tag4')],
                    'mock'  => 'contract',
                ],
                [
                    'n' => '05',
                    'title' => __('demo.flow_05_title'),
                    'body'  => __('demo.flow_05_body'),
                    'tags'  => [__('demo.flow_05_tag1'), __('demo.flow_05_tag2'), __('demo.flow_05_tag3'), __('demo.flow_05_tag4')],
                    'mock'  => 'payment',
                ],
                [
                    'n' => '06',
                    'title' => __('demo.flow_06_title'),
                    'body'  => __('demo.flow_06_body'),
                    'tags'  => [__('demo.flow_06_tag1'), __('demo.flow_06_tag2'), __('demo.flow_06_tag3'), __('demo.flow_06_tag4')],
                    'mock'  => 'shipment',
                ],
                [
                    'n' => '07',
                    'title' => __('demo.flow_07_title'),
                    'body'  => __('demo.flow_07_body'),
                    'tags'  => [__('demo.flow_07_tag1'), __('demo.flow_07_tag2'), __('demo.flow_07_tag3'), __('demo.flow_07_tag4')],
                    'mock'  => 'delivery',
                ],
            ];
            @endphp

            <div class="space-y-12">
                @foreach($flow as $i => $step)
                <div class="grid lg:grid-cols-[1fr_1.1fr] gap-10 items-center reveal">
                    {{-- Text side --}}
                    <div class="{{ $i % 2 === 1 ? 'lg:order-2' : '' }}">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="flex h-[52px] w-[52px] items-center justify-center rounded-[14px] bg-accent/10 border border-accent/25">
                                <span class="font-display text-[18px] font-bold tracking-[-0.014em] text-accent">{{ $step['n'] }}</span>
                            </div>
                            <div class="h-px flex-1 bg-gradient-to-r from-accent/40 to-transparent"></div>
                        </div>
                        <h3 class="font-display text-[24px] sm:text-[28px] font-bold text-primary tracking-[-0.022em] leading-[1.15] mb-4">{{ $step['title'] }}</h3>
                        <p class="t-lead text-[15px] mb-6">{{ $step['body'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($step['tags'] as $t)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-medium tracking-[-0.005em] rounded-full bg-surface-2 border border-th-border text-muted">
                                <svg class="w-3 h-3 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ $t }}
                            </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Mock screen side --}}
                    <div class="{{ $i % 2 === 1 ? 'lg:order-1' : '' }}">
                        <div class="relative bg-surface border border-th-border rounded-[18px] shadow-[0_24px_80px_-32px_rgba(0,0,0,0.45)] overflow-hidden">
                            {{-- mock window chrome --}}
                            <div class="flex items-center gap-1.5 px-4 py-3 border-b border-th-border bg-surface-2/60">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#FF5F57]"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#FEBC2E]"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-[#28C840]"></span>
                                <div class="ms-3 px-2.5 py-1 rounded-md bg-page/60 border border-th-border text-[10px] text-muted font-mono">trilink.ae/dashboard/{{ $step['mock'] }}</div>
                            </div>

                            {{-- mock body --}}
                            <div class="p-6">
                                @switch($step['mock'])
                                    @case('pr')
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold">{{ __('demo.mock_pr_label') }}</div>
                                                <div class="text-[18px] font-bold text-primary tracking-[-0.014em]">PR-{{ now()->year }}-0184</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-[rgba(255,176,32,0.1)] border border-[rgba(255,176,32,0.25)] text-[#ffb020]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#ffb020]"></span>{{ __('demo.mock_pr_draft') }}
                                            </span>
                                        </div>
                                        <div class="space-y-2.5">
                                            @foreach([__('demo.mock_pr_line1'), __('demo.mock_pr_line2'), __('demo.mock_pr_line3')] as $line)
                                            <div class="flex items-center justify-between p-3 rounded-[10px] bg-page/50 border border-th-border">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-7 h-7 rounded-md bg-accent/10 border border-accent/20 flex items-center justify-center">
                                                        <svg class="w-3.5 h-3.5 text-accent" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/></svg>
                                                    </div>
                                                    <div class="text-[12px] text-primary font-medium">{{ $line }}</div>
                                                </div>
                                                <div class="text-[11px] text-muted font-mono">AED ••••</div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @break

                                    @case('rfq')
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold">{{ __('demo.mock_rfq_label') }}</div>
                                                <div class="text-[18px] font-bold text-primary tracking-[-0.014em]">RFQ-{{ now()->year }}-2271</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-[rgba(0,217,181,0.1)] border border-[rgba(0,217,181,0.25)] text-[#00d9b5]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>{{ __('demo.mock_rfq_open') }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2.5 mb-4">
                                            @foreach([['12', __('demo.mock_rfq_suppliers')], ['08', __('demo.mock_rfq_bids')], ['3d', __('demo.mock_rfq_closes_in')]] as $kv)
                                            <div class="p-3 rounded-[10px] bg-page/50 border border-th-border text-center">
                                                <div class="font-display text-[18px] font-bold text-accent tracking-tight">{{ $kv[0] }}</div>
                                                <div class="text-[10px] text-muted uppercase tracking-[0.1em] mt-0.5">{{ $kv[1] }}</div>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="text-[11px] text-muted mb-2">{{ __('demo.mock_rfq_time_remaining') }}</div>
                                        <div class="h-[6px] rounded-full bg-page/60 overflow-hidden">
                                            <div class="h-full rounded-full bg-accent shadow-[0_0_12px_rgba(59,126,255,0.45)]" style="width: 62%"></div>
                                        </div>
                                        @break

                                    @case('bid')
                                        <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold mb-3">{{ __('demo.mock_bid_label') }}</div>
                                        @foreach([
                                            [__('demo.mock_bid_supplier1'),'AED 184,200','#00d9b5', __('demo.mock_bid_tag_best_price')],
                                            [__('demo.mock_bid_supplier2'),'AED 192,400','#4f7cff', __('demo.mock_bid_tag_fastest')],
                                            [__('demo.mock_bid_supplier3'),'AED 207,900','#ffb020', __('demo.mock_bid_tag_compliant')],
                                        ] as $i2 => $b)
                                        <div class="flex items-center justify-between p-3 mb-2 rounded-[10px] bg-page/50 border border-th-border">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-md flex items-center justify-center text-[10px] font-bold text-white" style="background: {{ $b[2] }}">{{ $i2 + 1 }}</div>
                                                <div>
                                                    <div class="text-[12px] text-primary font-semibold">{{ $b[0] }}</div>
                                                    <div class="text-[10px] text-muted">{{ $b[3] }}</div>
                                                </div>
                                            </div>
                                            <div class="text-[12px] font-mono text-primary">{{ $b[1] }}</div>
                                        </div>
                                        @endforeach
                                        @break

                                    @case('contract')
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold">{{ __('demo.mock_contract_label') }}</div>
                                                <div class="text-[18px] font-bold text-primary tracking-[-0.014em]">CTR-{{ now()->year }}-0617</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.25)] text-accent">
                                                <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>{{ __('demo.mock_contract_pending') }}
                                            </span>
                                        </div>
                                        <div class="space-y-3">
                                            <div class="flex items-center justify-between p-3 rounded-[10px] bg-page/50 border border-th-border">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-[#00d9b5] text-white flex items-center justify-center text-[10px] font-bold">B</div>
                                                    <div class="text-[12px] text-primary font-semibold">{{ __('demo.mock_contract_buyer_signed') }}</div>
                                                </div>
                                                <svg class="w-4 h-4 text-[#00d9b5]" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                            </div>
                                            <div class="flex items-center justify-between p-3 rounded-[10px] bg-page/50 border border-th-border">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-surface-2 border border-th-border text-muted flex items-center justify-center text-[10px] font-bold">S</div>
                                                    <div class="text-[12px] text-muted">{{ __('demo.mock_contract_awaiting_supplier') }}</div>
                                                </div>
                                                <svg class="w-4 h-4 text-muted animate-pulse" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            </div>
                                        </div>
                                        @break

                                    @case('payment')
                                        <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold mb-3">{{ __('demo.mock_payment_label') }}</div>
                                        @php $milestones = [
                                            [__('demo.mock_payment_advance'), 30, 'paid'],
                                            [__('demo.mock_payment_on_shipment'), 40, 'pending'],
                                            [__('demo.mock_payment_on_delivery'), 30, 'queued'],
                                        ]; @endphp
                                        @foreach($milestones as $m)
                                        @php
                                            $color = $m[2] === 'paid' ? '#00d9b5' : ($m[2] === 'pending' ? '#ffb020' : '#4f7cff');
                                            $bg    = 'rgba(' . ($m[2] === 'paid' ? '0,217,181' : ($m[2] === 'pending' ? '255,176,32' : '79,124,255')) . ',0.1)';
                                            $br    = 'rgba(' . ($m[2] === 'paid' ? '0,217,181' : ($m[2] === 'pending' ? '255,176,32' : '79,124,255')) . ',0.25)';
                                        @endphp
                                        <div class="flex items-center justify-between p-3 mb-2 rounded-[10px] bg-page/50 border border-th-border">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-md flex items-center justify-center" style="background: {{ $bg }}; border: 1px solid {{ $br }}">
                                                    <svg class="w-3.5 h-3.5" style="color: {{ $color }}" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                                                </div>
                                                <div class="text-[12px] text-primary font-semibold">{{ $m[0] }} — {{ $m[1] }}%</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold rounded-full uppercase tracking-wider" style="background: {{ $bg }}; border: 1px solid {{ $br }}; color: {{ $color }};">
                                                <span class="w-1 h-1 rounded-full" style="background: {{ $color }}"></span>{{ __('demo.mock_payment_status_' . $m[2]) }}
                                            </span>
                                        </div>
                                        @endforeach
                                        @break

                                    @case('shipment')
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-[0.14em] text-muted font-semibold">{{ __('demo.mock_shipment_label') }}</div>
                                                <div class="text-[18px] font-bold text-primary tracking-[-0.014em]">SHP-{{ now()->year }}-1142</div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.25)] text-accent">
                                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>{{ __('demo.mock_shipment_in_transit') }}
                                            </span>
                                        </div>
                                        <div class="relative pl-8 rtl:pl-0 rtl:pr-8">
                                            <div class="absolute left-3 rtl:left-auto rtl:right-3 top-1.5 bottom-1.5 w-px bg-th-border"></div>
                                            @foreach([
                                                [__('demo.mock_shipment_ev1_t'), __('demo.mock_shipment_ev1_l'),'#00d9b5'],
                                                [__('demo.mock_shipment_ev2_t'), __('demo.mock_shipment_ev2_l'),'#00d9b5'],
                                                [__('demo.mock_shipment_ev3_t'), __('demo.mock_shipment_ev3_l'),'#4f7cff'],
                                                [__('demo.mock_shipment_ev4_t'), __('demo.mock_shipment_ev4_l'),'#b4b6c0'],
                                            ] as $ev)
                                            <div class="relative mb-3">
                                                <div class="absolute -left-[22px] rtl:left-auto rtl:-right-[22px] top-1 w-2.5 h-2.5 rounded-full" style="background: {{ $ev[2] }}; box-shadow: 0 0 0 3px var(--c-surface);"></div>
                                                <div class="text-[12px] text-primary font-semibold">{{ $ev[0] }}</div>
                                                <div class="text-[10px] text-muted">{{ $ev[1] }}</div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @break

                                    @case('delivery')
                                        <div class="text-center py-4">
                                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#00d9b5]/15 border border-[#00d9b5]/30 flex items-center justify-center">
                                                <svg class="w-8 h-8 text-[#00d9b5]" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                            </div>
                                            <div class="text-[18px] font-bold text-primary tracking-[-0.014em]">{{ __('demo.mock_delivery_confirmed') }}</div>
                                            <div class="text-[12px] text-muted mt-1">{{ __('demo.mock_delivery_note') }}</div>
                                            <div class="mt-5 flex items-center justify-center gap-1">
                                                @for($s = 0; $s < 5; $s++)
                                                <svg class="w-5 h-5 {{ $s < 5 ? 'text-[#ffb020]' : 'text-muted' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                                @endfor
                                            </div>
                                            <div class="text-[11px] text-muted mt-2">{{ __('demo.mock_delivery_performance') }}</div>
                                        </div>
                                        @break
                                @endswitch
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== MODULES CATALOG ===================== --}}
    <section class="py-24 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1280px] mx-auto">
            <div class="text-center mb-14 reveal">
                <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-5">{{ __('demo.section_04_eyebrow') }}</span>
                <h2 class="h-section text-gradient mb-3">{{ __('demo.section_04_title') }}</h2>
                <p class="t-lead max-w-[640px] mx-auto">{{ __('demo.section_04_subtitle') }}</p>
            </div>

            @php
            $catProcurement = __('demo.module_cat_procurement');
            $catContracts   = __('demo.module_cat_contracts');
            $catFinance     = __('demo.module_cat_finance');
            $catLogistics   = __('demo.module_cat_logistics');
            $catCatalog     = __('demo.module_cat_catalog');
            $catCompliance  = __('demo.module_cat_compliance');
            $catOperations  = __('demo.module_cat_operations');
            $catPlatform    = __('demo.module_cat_platform');

            $modules = [
                ['cat' => $catProcurement, 'name' => __('demo.module_pr_n'),         'desc' => __('demo.module_pr_d'),         'svg' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>'],
                ['cat' => $catProcurement, 'name' => __('demo.module_rfq_n'),        'desc' => __('demo.module_rfq_d'),        'svg' => '<path d="m14 13-7.5 7.5c-.83.83-2.17.83-3 0 0 0 0 0 0 0a2.12 2.12 0 0 1 0-3L11 10"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/>'],
                ['cat' => $catProcurement, 'name' => __('demo.module_bids_n'),       'desc' => __('demo.module_bids_d'),       'svg' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['cat' => $catContracts,   'name' => __('demo.module_contracts_n'),  'desc' => __('demo.module_contracts_d'),  'svg' => '<path d="M12.5 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5v3"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M13.378 15.626a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/>'],
                ['cat' => $catFinance,     'name' => __('demo.module_milestones_n'), 'desc' => __('demo.module_milestones_d'), 'svg' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>'],
                ['cat' => $catFinance,     'name' => __('demo.module_escrow_n'),     'desc' => __('demo.module_escrow_d'),     'svg' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
                ['cat' => $catFinance,     'name' => __('demo.module_spend_n'),      'desc' => __('demo.module_spend_d'),      'svg' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>'],
                ['cat' => $catLogistics,   'name' => __('demo.module_shipment_n'),   'desc' => __('demo.module_shipment_d'),   'svg' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>'],
                ['cat' => $catLogistics,   'name' => __('demo.module_quotes_n'),     'desc' => __('demo.module_quotes_d'),     'svg' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>'],
                ['cat' => $catLogistics,   'name' => __('demo.module_customs_n'),    'desc' => __('demo.module_customs_d'),    'svg' => '<path d="M16 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>'],
                ['cat' => $catCatalog,     'name' => __('demo.module_categories_n'), 'desc' => __('demo.module_categories_d'), 'svg' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>'],
                ['cat' => $catCatalog,     'name' => __('demo.module_products_n'),   'desc' => __('demo.module_products_d'),   'svg' => '<path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/>'],
                ['cat' => $catCatalog,     'name' => __('demo.module_directory_n'),  'desc' => __('demo.module_directory_d'),  'svg' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>'],
                ['cat' => $catCompliance,  'name' => __('demo.module_kyc_n'),        'desc' => __('demo.module_kyc_d'),        'svg' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                ['cat' => $catCompliance,  'name' => __('demo.module_insurance_n'),  'desc' => __('demo.module_insurance_d'),  'svg' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>'],
                ['cat' => $catCompliance,  'name' => __('demo.module_esg_n'),        'desc' => __('demo.module_esg_d'),        'svg' => '<path d="M12 2a4 4 0 0 0-4 4v3H5a3 3 0 0 0-3 3v3a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-3a3 3 0 0 0-3-3h-3V6a4 4 0 0 0-4-4z"/>'],
                ['cat' => $catOperations,  'name' => __('demo.module_disputes_n'),   'desc' => __('demo.module_disputes_d'),   'svg' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'],
                ['cat' => $catOperations,  'name' => __('demo.module_performance_n'),'desc' => __('demo.module_performance_d'),'svg' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>'],
                ['cat' => $catOperations,  'name' => __('demo.module_notifications_n'),'desc' => __('demo.module_notifications_d'),'svg' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'],
                ['cat' => $catOperations,  'name' => __('demo.module_audit_n'),      'desc' => __('demo.module_audit_d'),      'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/>'],
                ['cat' => $catPlatform,    'name' => __('demo.module_branches_n'),   'desc' => __('demo.module_branches_d'),   'svg' => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>'],
                ['cat' => $catPlatform,    'name' => __('demo.module_api_n'),        'desc' => __('demo.module_api_d'),        'svg' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>'],
                ['cat' => $catPlatform,    'name' => __('demo.module_ai_n'),         'desc' => __('demo.module_ai_d'),         'svg' => '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/>'],
                ['cat' => $catPlatform,    'name' => __('demo.module_loc_n'),        'desc' => __('demo.module_loc_d'),        'svg' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>'],
            ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($modules as $i => $m)
                <div class="bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all reveal">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex h-[42px] w-[42px] items-center justify-center rounded-[12px] border border-slate-200/90 bg-slate-100 dark:border-white/[0.06] dark:bg-[#0d1018]">
                            <svg class="h-[18px] w-[18px] text-slate-800 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $m['svg'] !!}</svg>
                        </div>
                        <span class="text-[10px] uppercase tracking-[0.14em] font-semibold text-muted">{{ $m['cat'] }}</span>
                    </div>
                    <h3 class="h-card mb-1.5">{{ $m['name'] }}</h3>
                    <p class="t-body">{{ $m['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== TRUST & SECURITY ===================== --}}
    <section class="py-24 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1100px] mx-auto">
            <div class="text-center mb-14 reveal">
                <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-5">{{ __('demo.section_05_eyebrow') }}</span>
                <h2 class="h-section text-gradient mb-3">{{ __('demo.section_05_title') }}</h2>
                <p class="t-lead max-w-[640px] mx-auto">{{ __('demo.section_05_subtitle') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @php
                $trust = [
                    ['t' => __('demo.trust_rbac_t'),       'd' => __('demo.trust_rbac_d'),       'svg' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>'],
                    ['t' => __('demo.trust_audit_t'),      'd' => __('demo.trust_audit_d'),      'svg' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
                    ['t' => __('demo.trust_2fa_t'),        'd' => __('demo.trust_2fa_d'),        'svg' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
                    ['t' => __('demo.trust_files_t'),      'd' => __('demo.trust_files_d'),      'svg' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>'],
                    ['t' => __('demo.trust_kyc_t'),        'd' => __('demo.trust_kyc_d'),        'svg' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>'],
                    ['t' => __('demo.trust_government_t'), 'd' => __('demo.trust_government_d'), 'svg' => '<polygon points="12 2 20 7 4 7"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><line x1="3" x2="21" y1="22" y2="22"/>'],
                ];
                @endphp
                @foreach($trust as $t)
                <div class="bg-surface border border-th-border rounded-2xl p-6 reveal">
                    <div class="w-[44px] h-[44px] rounded-[12px] flex items-center justify-center mb-4" style="background: linear-gradient(180deg, #38bdf8 0%, #1d4ed8 100%); border: 1px solid rgba(56, 189, 248, 0.35); box-shadow: inset 0 1px 0 0 rgba(255,255,255,0.12);">
                        <svg class="w-[18px] h-[18px] text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $t['svg'] !!}</svg>
                    </div>
                    <h3 class="h-card mb-1.5">{{ $t['t'] }}</h3>
                    <p class="t-body">{{ $t['d'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== FINAL CTA ===================== --}}
    <section class="py-20 px-6 lg:px-10 bg-page transition-colors duration-300">
        <div class="max-w-[1280px] mx-auto reveal">
            <div class="cta-banner relative overflow-hidden rounded-[28px] px-10 py-16 ring-1 ring-slate-200/70 sm:px-16 sm:py-20 dark:ring-white/5 text-center">
                <div class="absolute -right-[10%] -top-20 h-[480px] w-[480px] rounded-full opacity-40 blur-[110px] dark:opacity-100" style="background: radial-gradient(circle, rgba(59,126,255,0.30) 0%, transparent 60%);"></div>
                <div class="absolute -bottom-20 left-[-5%] h-[420px] w-[420px] rounded-full opacity-30 blur-[100px] dark:opacity-100" style="background: radial-gradient(circle, rgba(90,148,255,0.20) 0%, transparent 60%);"></div>

                <div class="relative">
                    <h2 class="font-display mb-5 text-[32px] font-bold leading-[1.08] tracking-[-0.028em] text-slate-900 sm:text-[44px] dark:text-white">{{ __('demo.cta_title') }}</h2>
                    <p class="mb-9 max-w-[560px] mx-auto text-[16px] leading-[1.7] tracking-[0.01em] text-slate-600 dark:text-white/55">{{ __('demo.cta_subtitle') }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('register') }}" class="group inline-flex items-center gap-3 px-7 py-3.5 bg-accent hover:bg-accent-h text-white rounded-full text-[15px] font-semibold tracking-[-0.011em] transition-all duration-300 shadow-[0_10px_40px_rgba(59,126,255,0.45)] hover:-translate-y-0.5">
                            {{ __('demo.cta_create_account') }}
                            <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                        <a href="{{ route('public.suppliers') }}" class="inline-flex items-center gap-3 px-7 py-3.5 bg-surface-2 hover:bg-elevated border border-th-border text-primary rounded-full text-[15px] font-semibold tracking-[-0.011em] transition-all">
                            {{ __('demo.cta_browse_suppliers') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-landing.footer />
</div>

@endsection
