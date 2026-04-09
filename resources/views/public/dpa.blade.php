@extends('layouts.app')

@section('title', __('privacy.dpa_title'))

@section('content')

<div class="landing-page">
    <x-landing.navbar />

    <section class="pt-[120px] pb-20 px-6 lg:px-10">
        <div class="max-w-[820px] mx-auto">

            <div class="mb-10">
                <p class="text-[12px] uppercase tracking-wider font-semibold text-accent mb-2">
                    {{ __('privacy.dpa_eyebrow') }}
                </p>
                <h1 class="text-[40px] sm:text-[48px] font-bold text-primary leading-tight tracking-[-0.02em] mb-3">
                    {{ __('privacy.dpa_title') }}
                </h1>
                <p class="text-[14px] text-muted">
                    {{ __('privacy.policy_version_label') }}: <span class="font-mono text-primary">{{ $version }}</span>
                </p>
            </div>

            <div class="space-y-8">

                <section class="bg-surface border border-th-border rounded-2xl p-6">
                    <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('privacy.dpa_purpose_title') }}</h2>
                    <p class="text-[13px] text-body leading-relaxed">{{ __('privacy.dpa_purpose_body') }}</p>
                </section>

                <section class="bg-surface border border-th-border rounded-2xl p-6">
                    <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('privacy.dpa_definitions_title') }}</h2>
                    <dl class="space-y-3 text-[13px]">
                        <div>
                            <dt class="font-semibold text-primary">{{ __('privacy.dpa_term_controller') }}</dt>
                            <dd class="text-body mt-0.5">{{ __('privacy.dpa_def_controller') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-primary">{{ __('privacy.dpa_term_processor') }}</dt>
                            <dd class="text-body mt-0.5">{{ __('privacy.dpa_def_processor') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-primary">{{ __('privacy.dpa_term_subject') }}</dt>
                            <dd class="text-body mt-0.5">{{ __('privacy.dpa_def_subject') }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="bg-surface border border-th-border rounded-2xl p-6">
                    <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('privacy.dpa_obligations_title') }}</h2>
                    <ol class="text-[13px] text-body space-y-2 list-decimal ps-6">
                        <li>{{ __('privacy.dpa_obligation_1') }}</li>
                        <li>{{ __('privacy.dpa_obligation_2') }}</li>
                        <li>{{ __('privacy.dpa_obligation_3') }}</li>
                        <li>{{ __('privacy.dpa_obligation_4') }}</li>
                        <li>{{ __('privacy.dpa_obligation_5') }}</li>
                    </ol>
                </section>

                <section class="bg-surface border border-th-border rounded-2xl p-6">
                    <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('privacy.dpa_schedule_title') }}</h2>
                    <p class="text-[13px] text-muted mb-3">{{ __('privacy.dpa_schedule_intro') }}</p>
                    <table class="w-full text-[12px]">
                        <thead class="text-muted uppercase tracking-wider text-[10px] border-b border-th-border">
                            <tr>
                                <th class="text-start pb-2">{{ __('privacy.col_name') }}</th>
                                <th class="text-start pb-2">{{ __('privacy.col_purpose') }}</th>
                                <th class="text-start pb-2">{{ __('privacy.col_location') }}</th>
                                <th class="text-start pb-2">{{ __('privacy.col_basis') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sub_processors as $p)
                                <tr class="border-b border-th-border last:border-0">
                                    <td class="py-2 font-semibold text-primary">{{ $p['name'] }}</td>
                                    <td class="py-2 text-body">{{ $p['purpose'] }}</td>
                                    <td class="py-2 font-mono text-muted">{{ $p['location'] }}</td>
                                    <td class="py-2 text-muted">{{ $p['basis'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <section class="bg-surface border border-th-border rounded-2xl p-6">
                    <h2 class="text-[18px] font-bold text-primary mb-3">{{ __('privacy.dpa_governing_title') }}</h2>
                    <p class="text-[13px] text-body leading-relaxed">{{ __('privacy.dpa_governing_body') }}</p>
                </section>

            </div>
        </div>
    </section>

    <x-landing.footer />
</div>

<x-privacy.cookie-banner />

@endsection
