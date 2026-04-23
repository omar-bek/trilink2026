@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('design_partners.title'))

@section('content')

<x-admin.navbar active="design-partners" />

{{-- ────────────────── Header strip ────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] p-5 sm:p-[25px] mb-6">
    <div class="flex items-start sm:items-center justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h2 class="font-display text-[18px] sm:text-[20px] font-bold text-primary mb-1">
                {{ __('design_partners.title') }}
            </h2>
            <p class="text-[13px] text-muted leading-relaxed">
                {{ __('design_partners.subtitle') }}
            </p>
        </div>
        <a href="{{ route('admin.companies.index') }}"
           class="inline-flex items-center gap-2 px-4 h-10 rounded-[10px] bg-accent text-white text-[12px] font-semibold hover:bg-accent/90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('design_partners.enroll_cta') }}
        </a>
    </div>
</div>

{{-- ────────────────── Stat tiles ────────────────── --}}
@php
    $tiles = [
        ['key' => 'suppliers', 'label' => __('design_partners.stat_suppliers'), 'value' => $stats['suppliers'], 'target' => $stats['suppliers_target'], 'color' => '#4f7cff'],
        ['key' => 'buyers',    'label' => __('design_partners.stat_buyers'),    'value' => $stats['buyers'],    'target' => $stats['buyers_target'],    'color' => '#00d9b5'],
        ['key' => 'onboarded', 'label' => __('design_partners.stat_fully_onboarded'), 'value' => $stats['fully_onboarded'], 'target' => null, 'color' => '#00b894'],
        ['key' => 'blocked',   'label' => __('design_partners.stat_blocked'), 'value' => $stats['blocked'], 'target' => null, 'color' => '#ff4d7f'],
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    @foreach($tiles as $tile)
        <div class="bg-surface border border-th-border rounded-[14px] p-4 sm:p-5 relative overflow-hidden">
            <div class="absolute inset-0 pointer-events-none opacity-[0.06]"
                 style="background: radial-gradient(circle at 100% 0%, {{ $tile['color'] }} 0%, transparent 60%);"></div>
            <div class="relative">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-faint mb-2">{{ $tile['label'] }}</p>
                <p class="text-[24px] sm:text-[28px] font-bold text-primary leading-none">
                    {{ $tile['value'] }}
                    @if($tile['target'])
                        <span class="text-[14px] font-semibold text-muted">/ {{ $tile['target'] }}</span>
                    @endif
                </p>
                @if($tile['target'])
                    @php
                        $pct = $tile['target'] > 0 ? min(100, (int) round(($tile['value'] / $tile['target']) * 100)) : 0;
                    @endphp
                    <div class="mt-3 h-[6px] rounded-full bg-surface-2 overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: {{ $pct }}%; background: {{ $tile['color'] }};"></div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- ────────────────── Role filter tabs ────────────────── --}}
<div class="mb-5 bg-surface border border-th-border rounded-[14px] p-[5px] inline-flex items-center gap-1">
    @foreach([
        'all' => __('design_partners.filter_all'),
        'supplier' => __('design_partners.filter_suppliers'),
        'buyer' => __('design_partners.filter_buyers'),
    ] as $key => $label)
        <a href="{{ route('admin.design-partners.index', $key === 'all' ? [] : ['role' => $key]) }}"
           class="px-4 h-9 inline-flex items-center rounded-[10px] text-[12px] font-semibold transition-colors
                  {{ $roleFilter === $key
                        ? 'bg-accent text-white shadow-sm'
                        : 'text-muted hover:text-primary hover:bg-surface-2' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- ────────────────── Rows ────────────────── --}}
@if($rows->isEmpty())
    <div class="bg-surface border border-th-border rounded-[16px] p-12 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-surface-2 mb-4">
            <svg class="w-7 h-7 text-faint" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <h3 class="text-[15px] font-semibold text-primary mb-1">{{ __('design_partners.empty_title') }}</h3>
        <p class="text-[13px] text-muted max-w-md mx-auto">{{ __('design_partners.empty_body') }}</p>
    </div>
@else
    <div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-th-border bg-surface-2/50">
                        <th class="text-start px-5 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider">{{ __('design_partners.column_partner') }}</th>
                        <th class="text-start px-3 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider">{{ __('design_partners.column_role') }}</th>
                        <th class="text-start px-3 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider">{{ __('design_partners.column_days') }}</th>
                        <th class="text-start px-3 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider w-[28%]">{{ __('design_partners.column_progress') }}</th>
                        <th class="text-start px-3 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider">{{ __('design_partners.column_next') }}</th>
                        <th class="text-end px-5 py-3 font-semibold text-faint text-[11px] uppercase tracking-wider">{{ __('design_partners.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $company = $row['company'];
                            $roleLabel = $row['role'] === 'buyer' ? __('design_partners.role_buyer') : __('design_partners.role_supplier');
                            $roleColor = $row['role'] === 'buyer' ? '#00d9b5' : '#4f7cff';
                            $progressColor = $row['blocked']
                                ? '#ff4d7f'
                                : ($row['completion'] === 100 ? '#00b894' : '#4f7cff');
                            $nextLabel = $row['next_milestone']
                                ? __('design_partners.milestone_' . $row['next_milestone'])
                                : '—';
                        @endphp
                        <tr class="border-b border-th-border last:border-b-0 hover:bg-surface-2/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-[10px] bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0 text-accent font-bold text-[13px]">
                                        {{ \Illuminate\Support\Str::substr($company->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-primary truncate">{{ $company->name }}</p>
                                        @if($company->registration_number)
                                            <p class="text-[11px] text-muted font-mono truncate">{{ $company->registration_number }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                                      style="background: {{ $roleColor }}1a; color: {{ $roleColor }}; border: 1px solid {{ $roleColor }}40;">
                                    {{ $roleLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <span class="text-body font-mono text-[12px]">
                                    @if($row['days_in'] <= 0)
                                        —
                                    @elseif($row['days_in'] === 1)
                                        {{ __('design_partners.day_one') }}
                                    @else
                                        {{ __('design_partners.days_count', ['count' => $row['days_in']]) }}
                                    @endif
                                </span>
                                @if($row['blocked'])
                                    <span class="ms-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-[#ff4d7f]/10 text-[#ff4d7f] border border-[#ff4d7f]/25">
                                        {{ __('design_partners.blocked_label') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex items-center gap-2 min-w-[160px]">
                                    <div class="flex-1 h-[8px] rounded-full bg-surface-2 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             style="width: {{ $row['completion'] }}%; background: {{ $progressColor }};"></div>
                                    </div>
                                    <span class="text-[11px] font-mono font-semibold text-primary w-[36px] text-end">{{ $row['completion'] }}%</span>
                                </div>
                                <div class="flex items-center gap-1 mt-1.5">
                                    @foreach($row['milestones'] as $m)
                                        <span class="w-2 h-2 rounded-full"
                                              title="{{ __('design_partners.milestone_' . $m['key']) }}"
                                              style="background: {{ $m['done'] ? '#00b894' : 'var(--surface-2)' }}; border: 1px solid {{ $m['done'] ? '#00b894' : 'var(--border)' }};"></span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <span class="text-[12px] font-semibold {{ $row['completion'] === 100 ? 'text-[#00b894]' : 'text-body' }}">
                                    {{ $nextLabel }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.companies.show', $company->id) }}"
                                       class="inline-flex items-center gap-1.5 px-3 h-8 rounded-[8px] bg-surface-2 hover:bg-elevated border border-th-border text-[11px] font-semibold text-body hover:text-primary transition-colors">
                                        {{ __('design_partners.view_company') }}
                                    </a>
                                    <form action="{{ route('admin.design-partners.unenroll', $company->id) }}" method="POST"
                                          onsubmit="return confirm('{{ __('design_partners.unenroll') }}?');">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-[8px] bg-surface-2 hover:bg-[#ff4d7f]/10 border border-th-border hover:border-[#ff4d7f]/30 text-muted hover:text-[#ff4d7f] transition-colors"
                                                title="{{ __('design_partners.unenroll') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @if($row['notes'])
                            <tr class="bg-surface-2/30">
                                <td colspan="6" class="px-5 pb-3 pt-1 text-[12px] text-muted italic">
                                    {{ $row['notes'] }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
