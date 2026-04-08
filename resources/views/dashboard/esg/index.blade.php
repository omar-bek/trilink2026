@extends('layouts.dashboard', ['active' => 'esg'])
@section('title', __('esg.title'))

@section('content')

<x-dashboard.page-header :title="__('esg.title')" :subtitle="__('esg.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">{{ session('status') }}</div>
@endif

{{-- KPI strip — pillar scores + overall grade + Scope 3 total. --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
    @php
        $env = $questionnaire?->environmental_score ?? 0;
        $soc = $questionnaire?->social_score ?? 0;
        $gov = $questionnaire?->governance_score ?? 0;
        $overall = $questionnaire?->overall_score ?? 0;
        $grade = $questionnaire?->grade ?? 'F';
        $gradeColor = match($grade) {
            'A' => 'text-[#00d9b5]', 'B' => 'text-[#34d399]', 'C' => 'text-[#ffb020]', 'D' => 'text-[#f97316]', default => 'text-[#ff4d7f]',
        };
        $gradeBorder = match($grade) {
            'A' => 'border-[#00d9b5]/40', 'B' => 'border-[#34d399]/40', 'C' => 'border-[#ffb020]/40', 'D' => 'border-[#f97316]/40', default => 'border-[#ff4d7f]/40',
        };
    @endphp
    <div class="bg-surface border-2 {{ $gradeBorder }} rounded-[16px] p-5 text-center relative overflow-hidden">
        <div class="absolute top-3 end-3 w-8 h-8 rounded-[10px] bg-current/10 flex items-center justify-center {{ $gradeColor }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
        </div>
        <p class="text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('esg.grade') }}</p>
        <p class="text-[42px] font-bold {{ $gradeColor }} leading-none">{{ $grade }}</p>
        <p class="text-[11px] text-muted mt-1">{{ $overall }}/100</p>
    </div>
    <div class="bg-surface border-2 border-[#00d9b5]/40 rounded-[16px] p-5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('esg.environmental') }}</p>
            <div class="w-8 h-8 rounded-[10px] bg-[#00d9b5]/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
            </div>
        </div>
        <p class="text-[28px] font-bold text-[#00d9b5] leading-none">{{ $env }}</p>
        <div class="h-1.5 bg-elevated rounded-full overflow-hidden mt-3"><div class="h-full bg-[#00d9b5] rounded-full transition-all" style="width: {{ $env }}%"></div></div>
    </div>
    <div class="bg-surface border-2 border-[#4f7cff]/40 rounded-[16px] p-5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('esg.social') }}</p>
            <div class="w-8 h-8 rounded-[10px] bg-[#4f7cff]/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>
        <p class="text-[28px] font-bold text-[#4f7cff] leading-none">{{ $soc }}</p>
        <div class="h-1.5 bg-elevated rounded-full overflow-hidden mt-3"><div class="h-full bg-[#4f7cff] rounded-full transition-all" style="width: {{ $soc }}%"></div></div>
    </div>
    <div class="bg-surface border-2 border-[#8B5CF6]/40 rounded-[16px] p-5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('esg.governance') }}</p>
            <div class="w-8 h-8 rounded-[10px] bg-[#8B5CF6]/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
        </div>
        <p class="text-[28px] font-bold text-[#8B5CF6] leading-none">{{ $gov }}</p>
        <div class="h-1.5 bg-elevated rounded-full overflow-hidden mt-3"><div class="h-full bg-[#8B5CF6] rounded-full transition-all" style="width: {{ $gov }}%"></div></div>
    </div>
    <div class="bg-surface border-2 border-[#ffb020]/40 rounded-[16px] p-5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('esg.scope_3_year') }}</p>
            <div class="w-8 h-8 rounded-[10px] bg-[#ffb020]/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
            </div>
        </div>
        <p class="text-[28px] font-bold text-[#ffb020] leading-none">{{ number_format($total_co2, 0) }}</p>
        <p class="text-[11px] text-muted mt-1">{{ __('esg.kg_co2e') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Questionnaire — 15 questions across 3 pillars. --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-1">{{ __('esg.questionnaire_title') }}</h3>
        <p class="text-[12px] text-muted mb-5">{{ __('esg.questionnaire_subtitle') }}</p>

        @can('esg.manage')
        <form method="POST" action="{{ route('dashboard.esg.questionnaire') }}" class="space-y-5">
            @csrf
            @php
                $existingAnswers = (array) ($questionnaire?->answers ?? []);
                $byPillar = collect($questions)->groupBy('pillar');
            @endphp

            @foreach(['environmental', 'social', 'governance'] as $pillar)
            <div>
                <p class="text-[11px] text-muted uppercase tracking-wider mb-3">{{ __('esg.' . $pillar) }}</p>
                <div class="space-y-3">
                    @foreach($byPillar[$pillar] ?? [] as $key => $q)
                    <div class="bg-page border border-th-border rounded-xl p-4">
                        <label class="block text-[12px] font-semibold text-primary mb-2">{{ __('esg.q_' . $key) }}</label>
                        <select name="answers[{{ $key }}]"
                                class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary">
                            <option value="">— {{ __('common.select') }} —</option>
                            @foreach($q['options'] as $optKey => $points)
                            <option value="{{ $optKey }}" @selected(($existingAnswers[$key] ?? null) === $optKey)>{{ __('esg.opt_' . $optKey) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            <button type="submit" class="w-full h-11 rounded-xl bg-accent text-white text-[13px] font-bold hover:bg-accent/90">
                {{ __('esg.recompute_score') }}
            </button>
        </form>
        @else
        <p class="text-[12px] text-muted italic">{{ __('esg.questionnaire_view_only') }}</p>
        @endcan
    </div>

    <div class="space-y-6">
        {{-- Modern slavery statement. --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[14px] font-bold text-primary mb-3">{{ __('esg.modern_slavery') }}</h3>
            @can('esg.manage')
            <form method="POST" action="{{ route('dashboard.esg.modern-slavery') }}" class="space-y-3">
                @csrf
                <input type="number" name="reporting_year" min="2020" max="2099" required value="{{ $modern_slavery?->reporting_year ?? date('Y') }}"
                       class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                <textarea name="statement" rows="5" maxlength="5000" required
                          class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"
                          placeholder="{{ __('esg.statement_placeholder') }}">{{ $modern_slavery?->statement }}</textarea>
                <input type="text" name="signed_by_name" placeholder="{{ __('esg.signed_by_name') }}" value="{{ $modern_slavery?->signed_by_name }}"
                       class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                <label class="flex items-center gap-2 text-[12px] text-primary">
                    <input type="hidden" name="board_approved" value="0">
                    <input type="checkbox" name="board_approved" value="1" @checked($modern_slavery?->board_approved)>
                    {{ __('esg.board_approved') }}
                </label>
                <button type="submit" class="w-full h-9 rounded-lg bg-accent text-white text-[12px] font-bold hover:bg-accent/90">
                    {{ __('common.save') }}
                </button>
            </form>
            @endcan
        </div>

        {{-- Conflict minerals declaration (3TG). --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[14px] font-bold text-primary mb-3">{{ __('esg.conflict_minerals') }}</h3>
            @can('esg.manage')
            <form method="POST" action="{{ route('dashboard.esg.conflict-minerals') }}" class="space-y-3">
                @csrf
                <input type="number" name="reporting_year" min="2020" max="2099" required value="{{ $conflict?->reporting_year ?? date('Y') }}"
                       class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                @foreach(['tin', 'tungsten', 'tantalum', 'gold'] as $mineral)
                <div>
                    <label class="block text-[10px] text-muted uppercase tracking-wider mb-1">{{ __('esg.mineral_' . $mineral) }}</label>
                    <select name="{{ $mineral }}_status" required class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary">
                        <option value="conflict_free" @selected(($conflict?->{$mineral . '_status'} ?? 'unknown') === 'conflict_free')>{{ __('esg.status_conflict_free') }}</option>
                        <option value="in_progress"   @selected(($conflict?->{$mineral . '_status'} ?? 'unknown') === 'in_progress')>{{ __('esg.status_in_progress') }}</option>
                        <option value="unknown"       @selected(($conflict?->{$mineral . '_status'} ?? 'unknown') === 'unknown')>{{ __('esg.status_unknown') }}</option>
                    </select>
                </div>
                @endforeach
                <input type="url" name="policy_url" placeholder="{{ __('esg.policy_url_placeholder') }}" value="{{ $conflict?->policy_url }}"
                       class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary font-mono"/>
                <button type="submit" class="w-full h-9 rounded-lg bg-accent text-white text-[12px] font-bold hover:bg-accent/90">
                    {{ __('common.save') }}
                </button>
            </form>
            @endcan
        </div>

        {{-- Manual carbon entry — Scope 1 / 2 emissions outside the
             automated shipment calculator. --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[14px] font-bold text-primary mb-3">{{ __('esg.log_carbon') }}</h3>
            @can('esg.manage')
            <form method="POST" action="{{ route('dashboard.esg.carbon') }}" class="space-y-2">
                @csrf
                <select name="scope" required class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary">
                    <option value="1">{{ __('esg.scope_1') }}</option>
                    <option value="2">{{ __('esg.scope_2') }}</option>
                    <option value="3">{{ __('esg.scope_3') }}</option>
                </select>
                <input type="number" step="0.01" name="co2e_kg" required placeholder="{{ __('esg.kg_co2e') }}"
                       class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary"/>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="period_start" required class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary"/>
                    <input type="date" name="period_end" required class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary"/>
                </div>
                <textarea name="notes" rows="2" maxlength="500" placeholder="{{ __('esg.notes') }}"
                          class="w-full bg-page border border-th-border rounded-lg px-3 py-2 text-[11px] text-primary"></textarea>
                <button type="submit" class="w-full h-9 rounded-lg bg-accent text-white text-[12px] font-bold hover:bg-accent/90">
                    {{ __('esg.log_entry') }}
                </button>
            </form>
            @endcan
        </div>
    </div>
</div>

@endsection
