@extends('layouts.app')

@section('title', __('privacy.policy_title'))

@section('content')

<div class="landing-page">
    <x-landing.navbar />

    <section class="pt-[120px] pb-20 px-6 lg:px-10">
        <div class="max-w-[820px] mx-auto">

            <div class="mb-10">
                <p class="text-[12px] uppercase tracking-wider font-semibold text-accent mb-2">
                    {{ __('privacy.policy_eyebrow') }}
                </p>
                <h1 class="text-[40px] sm:text-[48px] font-bold text-primary leading-tight tracking-[-0.02em] mb-3">
                    {{ __('privacy.policy_title') }}
                </h1>
                <p class="text-[14px] text-muted">
                    {{ __('privacy.policy_version_label') }}: <span class="font-mono text-primary">{{ $version }}</span>
                    &nbsp;·&nbsp;
                    {{ __('privacy.policy_effective') }}: {{ now()->format('d M Y') }}
                </p>
            </div>

            <div class="prose prose-invert max-w-none space-y-8">

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_who_we_are') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_who_we_are') }}</p>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_what_we_collect') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed mb-3">{{ __('privacy.body_what_we_collect') }}</p>
                    <ul class="text-[14px] text-body space-y-1.5 list-disc ps-6">
                        <li>{{ __('privacy.collect_account') }}</li>
                        <li>{{ __('privacy.collect_company') }}</li>
                        <li>{{ __('privacy.collect_kyc') }}</li>
                        <li>{{ __('privacy.collect_transactional') }}</li>
                        <li>{{ __('privacy.collect_technical') }}</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_legal_basis') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_legal_basis') }}</p>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_residency') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed mb-3">
                        {{ __('privacy.body_residency', ['region' => $region, 'basis' => $adequacy_basis]) }}
                    </p>

                    @if(!empty($sub_processors))
                        <div class="bg-surface border border-th-border rounded-2xl p-5 mt-4">
                            <h3 class="text-[14px] font-semibold text-primary mb-3">{{ __('privacy.sub_processors') }}</h3>
                            <table class="w-full text-[12px]">
                                <thead class="text-muted uppercase tracking-wider text-[10px] border-b border-th-border">
                                    <tr>
                                        <th class="text-start pb-2">{{ __('privacy.col_name') }}</th>
                                        <th class="text-start pb-2">{{ __('privacy.col_purpose') }}</th>
                                        <th class="text-start pb-2">{{ __('privacy.col_location') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sub_processors as $p)
                                        <tr class="border-b border-th-border last:border-0">
                                            <td class="py-2 font-semibold text-primary">{{ $p['name'] }}</td>
                                            <td class="py-2 text-body">{{ $p['purpose'] }}</td>
                                            <td class="py-2 font-mono text-muted">{{ $p['location'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_your_rights') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed mb-3">{{ __('privacy.body_your_rights') }}</p>
                    <ul class="text-[14px] text-body space-y-1.5 list-disc ps-6">
                        <li>{{ __('privacy.right_access') }}</li>
                        <li>{{ __('privacy.right_rectify') }}</li>
                        <li>{{ __('privacy.right_erase') }}</li>
                        <li>{{ __('privacy.right_object') }}</li>
                        <li>{{ __('privacy.right_withdraw') }}</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_retention') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_retention') }}</p>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_security') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_security') }}</p>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_breach') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_breach') }}</p>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_dpo') }}</h2>
                    <div class="bg-surface border border-th-border rounded-2xl p-5">
                        <p class="text-[14px] font-semibold text-primary">{{ $dpo['name'] ?? __('privacy.dpo_default_name') }}</p>
                        @if(!empty($dpo['email']))
                            <p class="text-[13px] text-body mt-1">
                                <span class="text-muted">{{ __('privacy.email') }}:</span>
                                <a href="mailto:{{ $dpo['email'] }}" class="text-accent hover:underline">{{ $dpo['email'] }}</a>
                            </p>
                        @endif
                        @if(!empty($dpo['phone']))
                            <p class="text-[13px] text-body mt-1">
                                <span class="text-muted">{{ __('privacy.phone') }}:</span>
                                <span class="font-mono">{{ $dpo['phone'] }}</span>
                            </p>
                        @endif
                    </div>
                </section>

                <section>
                    <h2 class="text-[22px] font-bold text-primary mb-3">{{ __('privacy.section_complaints') }}</h2>
                    <p class="text-[14px] text-body leading-relaxed">{{ __('privacy.body_complaints') }}</p>
                </section>

            </div>
        </div>
    </section>

    <x-landing.footer />
</div>

<x-privacy.cookie-banner />

@endsection
