@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.diff_title'))

@section('content')

@php
    use App\Enums\ContractStatus;

    $statusKey = function ($status) {
        return match (true) {
            in_array($status, ['removed'], true)   => 'removed',
            in_array($status, ['added'], true)     => 'added',
            in_array($status, ['modified'], true)  => 'modified',
            default => 'unchanged',
        };
    };

    $statusBadge = [
        'added'     => ['text' => 'text-accent-success', 'bg' => 'bg-accent-success/10', 'border' => 'border-accent-success/20', 'label' => __('contracts.diff_added')],
        'removed'   => ['text' => 'text-accent-danger',  'bg' => 'bg-accent-danger/10',  'border' => 'border-accent-danger/20',  'label' => __('contracts.diff_removed')],
        'modified'  => ['text' => 'text-accent-warning', 'bg' => 'bg-accent-warning/10', 'border' => 'border-accent-warning/20', 'label' => __('contracts.diff_modified')],
        'unchanged' => ['text' => 'text-muted',          'bg' => 'bg-surface-2',         'border' => 'border-th-border',          'label' => __('contracts.diff_unchanged')],
    ];

    $totals = [
        'added'    => 0,
        'removed'  => 0,
        'modified' => 0,
    ];
    foreach ($diff as $section) {
        foreach ($section['items'] as $item) {
            if (isset($totals[$item['status']])) {
                $totals[$item['status']]++;
            }
        }
    }
@endphp

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
    <div class="min-w-0">
        <a href="{{ route('dashboard.contracts.show', ['id' => $contract->id]) }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
            {{ __('contracts.back_to_contract') }}
        </a>
        {{-- dir="ltr" prevents RTL from reversing the alphanumeric
             contract ID (e.g. "CTR-12345" → "54321-RTC"). --}}
        <p class="text-[12px] font-mono text-muted mb-1" dir="ltr">{{ $contract->contract_number }}</p>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('contracts.diff_title') }}</h1>
        <p class="text-[13px] text-muted mt-1">{{ __('contracts.diff_subtitle') }}</p>
    </div>

    {{-- Version pickers --}}
    <form method="GET" action="{{ route('dashboard.contracts.diff', ['id' => $contract->id]) }}"
          class="flex items-center gap-2 flex-wrap">
        <label class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('contracts.diff_from') }}</label>
        <select name="from" onchange="this.form.submit()"
                aria-label="{{ __('contracts.diff_from') }}"
                class="bg-surface border border-th-border rounded-xl px-3 h-10 text-[12px] text-primary focus:outline-none focus:border-accent/50">
            @foreach($all_versions as $v)
                <option value="{{ $v['version'] }}" @selected($v['version'] === $from)>v{{ $v['version'] }} · {{ $v['date'] }}</option>
            @endforeach
        </select>
        <label class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('contracts.diff_to') }}</label>
        <select name="to" onchange="this.form.submit()"
                aria-label="{{ __('contracts.diff_to') }}"
                class="bg-surface border border-th-border rounded-xl px-3 h-10 text-[12px] text-primary focus:outline-none focus:border-accent/50">
            @foreach($all_versions as $v)
                <option value="{{ $v['version'] }}" @selected($v['version'] === $to)>v{{ $v['version'] }} · {{ $v['date'] }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- Diff totals strip --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-accent-success/10 text-accent-success flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-muted">{{ __('contracts.diff_added') }}</p>
                <p class="text-[22px] font-bold text-accent-success leading-none">{{ $totals['added'] }}</p>
            </div>
        </div>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-[#ffb020]/10 text-[#ffb020] flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-muted">{{ __('contracts.diff_modified') }}</p>
                <p class="text-[22px] font-bold text-[#ffb020] leading-none">{{ $totals['modified'] }}</p>
            </div>
        </div>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-accent-danger/10 text-accent-danger flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12H4"/></svg>
            </div>
            <div>
                <p class="text-[11px] uppercase tracking-wider text-muted">{{ __('contracts.diff_removed') }}</p>
                <p class="text-[22px] font-bold text-accent-danger leading-none">{{ $totals['removed'] }}</p>
            </div>
        </div>
    </div>
</div>

{{-- Diff sections --}}
<div class="space-y-5">
    @forelse($diff as $i => $section)
        @php $sb = $statusBadge[$section['status']] ?? $statusBadge['unchanged']; @endphp
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                <h3 class="text-[16px] font-bold text-primary">{{ ($i + 1) }}. {{ $section['title'] }}</h3>
                <span class="text-[10px] font-bold rounded-full px-2.5 py-0.5 border {{ $sb['text'] }} {{ $sb['bg'] }} {{ $sb['border'] }}">
                    {{ $sb['label'] }}
                </span>
            </div>

            <div class="space-y-2">
                @foreach($section['items'] as $item)
                    @if($item['status'] === 'unchanged')
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-page border border-th-border">
                            <span class="text-[14px] text-muted flex-shrink-0">•</span>
                            <p class="text-[13px] text-body">{{ $item['from'] }}</p>
                        </div>
                    @elseif($item['status'] === 'added')
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-accent-success/5 border border-accent-success/20">
                            <span class="text-[14px] font-bold text-accent-success flex-shrink-0">+</span>
                            <p class="text-[13px] text-primary">{{ $item['to'] }}</p>
                        </div>
                    @elseif($item['status'] === 'removed')
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-accent-danger/5 border border-accent-danger/20">
                            <span class="text-[14px] font-bold text-accent-danger flex-shrink-0">−</span>
                            <p class="text-[13px] text-primary line-through opacity-80">{{ $item['from'] }}</p>
                        </div>
                    @elseif($item['status'] === 'modified')
                        <div class="rounded-lg overflow-hidden border border-[#ffb020]/30">
                            <div class="flex items-start gap-3 p-3 bg-accent-danger/5 border-b border-[#ffb020]/20">
                                <span class="text-[14px] font-bold text-accent-danger flex-shrink-0">−</span>
                                <p class="text-[13px] text-body line-through opacity-80">{{ $item['from'] }}</p>
                            </div>
                            <div class="flex items-start gap-3 p-3 bg-accent-success/5">
                                <span class="text-[14px] font-bold text-accent-success flex-shrink-0">+</span>
                                <p class="text-[13px] text-primary">{{ $item['to'] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-surface border border-th-border rounded-2xl p-10 text-center">
            <p class="text-[14px] text-muted">{{ __('contracts.diff_no_changes') }}</p>
        </div>
    @endforelse
</div>

@endsection
