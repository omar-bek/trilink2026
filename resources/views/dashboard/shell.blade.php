@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', __('nav.dashboard'))

@php
/**
 * Unified dashboard shell — same layout for every role; only the data differs.
 *
 * Required vars (built by DashboardController::index()):
 *   $user            ['name','company','role']
 *   $headerAction    ['label','route','icon','color'] | null
 *   $stats           array of 4 ['value','label','color','icon']
 *   $primaryList     ['title','subtitle','view_all_route','items'=>[ ... ]]
 *   $notifications   ['count', 'items'=>[['icon','color','title','desc','time']]]
 *   $listLeft        same shape as $primaryList (with progress items)
 *   $listRight       same shape as $primaryList (with progress items)
 *   $bottomSection   ['title','subtitle','view_all_route','items'=>[ ... ]]
 *
 * Visual spec follows the Figma "1990" frame (node 124:453) exactly:
 *   - Page bg #0f1117, card #1a1d29, inner #0f1117
 *   - Border rgba(255,255,255,0.1)
 *   - Accent #4f7cff, teal #00d9b5, orange #ffb020, purple #8b5cf6, red #ff4d7f
 *   - Section heading 20/600, subtitle 14/400 #b4b6c0
 *   - Stat number 30/600, label 14/400 #b4b6c0
 *   - Outer card padding 25, inner padding 17, outer radius 16, inner radius 12
 */

// Stat card colors — exact hex from Figma. The card uses a 2px solid border and a
// 5%-opacity tint on the body so the colored number reads cleanly.
$cardColors = [
    'purple' => ['border' => 'border-[#8b5cf6]', 'text' => 'text-[#8b5cf6]', 'tint' => 'bg-[rgba(139,92,246,0.05)]', 'iconBg' => 'bg-[rgba(139,92,246,0.12)]'],
    'blue'   => ['border' => 'border-[#4f7cff]', 'text' => 'text-[#4f7cff]', 'tint' => 'bg-[rgba(79,124,255,0.05)]', 'iconBg' => 'bg-[rgba(79,124,255,0.12)]'],
    'orange' => ['border' => 'border-[#ffb020]', 'text' => 'text-[#ffb020]', 'tint' => 'bg-[rgba(255,176,32,0.05)]', 'iconBg' => 'bg-[rgba(255,176,32,0.12)]'],
    'green'  => ['border' => 'border-[#00d9b5]', 'text' => 'text-[#00d9b5]', 'tint' => 'bg-[rgba(0,217,181,0.05)]', 'iconBg' => 'bg-[rgba(0,217,181,0.12)]'],
    'red'    => ['border' => 'border-[#ff4d7f]', 'text' => 'text-[#ff4d7f]', 'tint' => 'bg-[rgba(255,77,127,0.05)]', 'iconBg' => 'bg-[rgba(255,77,127,0.12)]'],
];

$notifColors = [
    'blue'   => ['bg' => 'bg-[rgba(79,124,255,0.12)]',  'text' => 'text-[#4f7cff]'],
    'green'  => ['bg' => 'bg-[rgba(0,217,181,0.12)]',   'text' => 'text-[#00d9b5]'],
    'orange' => ['bg' => 'bg-[rgba(255,176,32,0.12)]',  'text' => 'text-[#ffb020]'],
    'purple' => ['bg' => 'bg-[rgba(139,92,246,0.12)]',  'text' => 'text-[#8b5cf6]'],
    'red'    => ['bg' => 'bg-[rgba(255,77,127,0.12)]',  'text' => 'text-[#ff4d7f]'],
];

// Status pill colors — bg is the color at 10% opacity, text is the solid color.
$badgeColors = [
    'open'      => 'text-[#00d9b5] bg-[rgba(0,217,181,0.1)]',
    'draft'     => 'text-[#b4b6c0] bg-[rgba(180,182,192,0.1)]',
    'pending'   => 'text-[#ffb020] bg-[rgba(255,176,32,0.1)]',
    'urgent'    => 'text-[#ff4d7f] bg-[rgba(255,77,127,0.1)]',
    'due_soon'  => 'text-[#ffb020] bg-[rgba(255,176,32,0.1)]',
    'scheduled' => 'text-[#00d9b5] bg-[rgba(0,217,181,0.1)]',
];

// Status dot color used inside the pill — every status now carries a dot
// so the user can pattern-match priorities at a glance.
$dotColors = [
    'open'      => 'bg-[#00d9b5]',
    'draft'     => 'bg-[#b4b6c0]',
    'pending'   => 'bg-[#ffb020]',
    'urgent'    => 'bg-[#ff4d7f]',
    'due_soon'  => 'bg-[#ffb020]',
    'scheduled' => 'bg-[#00d9b5]',
];

// Default icon paths used by section headers when the controller didn't
// provide a per-section icon. These tile in front of the title so each
// dashboard row reads as a real "section" rather than plain text.
$sectionDefaults = [
    'list'         => 'M4 6h16M4 10h16M4 14h16M4 18h16',
    'notifications'=> 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    'contracts'    => 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zM14 2v6h6M9 15l2 2 4-4',
    'shipments'    => 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1',
    'payments'     => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-6 sm:mb-8 flex-wrap">
    <div class="min-w-0">
        <h1 class="text-[24px] sm:text-[28px] lg:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ __('nav.dashboard') }}</h1>
        <p class="text-[13px] sm:text-[14px] text-[#b4b6c0] mt-1 leading-relaxed">
            {{ __('dashboard.welcome') }}, <span class="text-white font-medium">{{ $user['name'] }}</span>@if(!empty($user['company'])) <span class="text-[#6b6e7a] mx-1">·</span> {{ $user['company'] }}@endif
        </p>
    </div>
    @if(!empty($headerAction))
    <a href="{{ route($headerAction['route']) }}"
       class="group inline-flex items-center gap-2 px-4 sm:px-5 h-11 sm:h-12 rounded-[12px] text-[13px] sm:text-[14px] font-semibold text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4f7cff]/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0f1117]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $headerAction['icon'] }}"/></svg>
        {{ $headerAction['label'] }}
    </a>
    @endif
</div>

{{-- Sprint B.6 — onboarding checklist. Rendered only while at least
     one required step is still pending; the service hides itself
     once the company has finished setup. --}}
@if(!empty($onboarding) && ($onboarding['visible'] ?? false))
    @include('dashboard.partials.onboarding-checklist', ['onboarding' => $onboarding])
@endif

{{-- Row 1: 4 KPI cards (colored 2px border + big number + icon) --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6 mb-6">
    @foreach($stats as $stat)
        @php $c = $cardColors[$stat['color']] ?? $cardColors['blue']; @endphp
        <div class="border-2 {{ $c['border'] }} {{ $c['tint'] }} rounded-[16px] p-5 sm:p-[26px] transition-transform hover:-translate-y-0.5">
            <div class="flex items-start justify-between mb-3">
                <p class="text-[26px] sm:text-[30px] font-semibold {{ $c['text'] }} leading-[1.2] tracking-[0.013em]">{{ $stat['value'] }}</p>
                <div class="w-9 h-9 rounded-[10px] {{ $c['iconBg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-[18px] h-[18px] {{ $c['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/></svg>
                </div>
            </div>
            <p class="text-[13px] sm:text-[14px] text-[#b4b6c0] leading-[20px]">{{ $stat['label'] }}</p>
        </div>
    @endforeach
</div>

{{-- Row 2: primary list (2/3) + Notifications (1/3) --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">

    <div class="lg:col-span-2 bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[12px] bg-[rgba(79,124,255,0.1)] border border-[rgba(79,124,255,0.2)] flex items-center justify-center flex-shrink-0">
                    <svg class="w-[18px] h-[18px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $primaryList['icon'] ?? $sectionDefaults['list'] }}"/></svg>
                </div>
                <div>
                    <h3 class="text-[20px] font-semibold text-white leading-[28px] tracking-[-0.022em]">{{ $primaryList['title'] }}</h3>
                    <p class="text-[14px] text-[#b4b6c0] mt-1">{{ $primaryList['subtitle'] }}</p>
                </div>
            </div>
            @if(!empty($primaryList['view_all_route']))
            <a href="{{ route($primaryList['view_all_route']) }}" class="inline-flex items-center gap-1 text-[14px] font-medium text-[#4f7cff] hover:underline whitespace-nowrap">
                {{ __('common.view_all') }}
                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endif
        </div>

        <div class="space-y-3">
            @forelse($primaryList['items'] as $item)
            <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}"
               class="block bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-[17px] hover:border-[#4f7cff]/40 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <p class="text-[12px] text-[#b4b6c0] font-mono">{{ $item['id'] }}</p>
                    @if(!empty($item['status']))
                        @php $st = $item['status']; @endphp
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-medium rounded-full px-2 h-5 {{ $badgeColors[$st] ?? $badgeColors['draft'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColors[$st] ?? $dotColors['draft'] }}"></span>
                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                        </span>
                    @endif
                </div>
                <p class="text-[16px] font-medium text-[#4f7cff] leading-[24px] tracking-[-0.02em] mb-2">{{ $item['title'] }}</p>
                @if(!empty($item['amount']))
                <p class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-[#00d9b5] leading-[20px] mb-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                    {{ $item['amount'] }}
                </p>
                @endif
                <div class="flex items-center gap-4 text-[14px] text-[#b4b6c0]">
                    @if(!empty($item['meta1']))
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        {{ $item['meta1'] }}
                    </span>
                    @endif
                    @if(!empty($item['meta2']))
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $item['meta2'] }}
                    </span>
                    @endif
                </div>
            </a>
            @empty
            <div class="text-center py-10">
                <div class="mx-auto w-12 h-12 rounded-full bg-[rgba(79,124,255,0.08)] border border-[rgba(79,124,255,0.2)] flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <p class="text-[13px] text-[#b4b6c0]">{{ __('common.no_data') }}</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Notifications --}}
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[12px] bg-[rgba(255,77,127,0.1)] border border-[rgba(255,77,127,0.2)] flex items-center justify-center flex-shrink-0">
                    <svg class="w-[18px] h-[18px] text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sectionDefaults['notifications'] }}"/></svg>
                </div>
                <h3 class="text-[20px] font-semibold text-white leading-[28px] tracking-[-0.022em]">{{ __('notifications.title') }}</h3>
            </div>
            @if(!empty($notifications['count']))
            <span class="bg-[#ff4d7f] text-white text-[12px] font-semibold rounded-full min-w-6 h-6 px-1.5 flex items-center justify-center shadow-[0_4px_14px_rgba(255,77,127,0.35)]">{{ $notifications['count'] }}</span>
            @endif
        </div>
        <div class="space-y-3">
            @forelse($notifications['items'] ?? [] as $n)
            @php $nc = $notifColors[$n['color'] ?? 'blue'] ?? $notifColors['blue']; @endphp
            <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-[13px] flex items-start gap-3 hover:border-[rgba(255,255,255,0.2)] transition-colors">
                <div class="w-7 h-7 rounded-[8px] {{ $nc['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-[14px] h-[14px] {{ $nc['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $n['icon'] }}"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[14px] font-medium text-white leading-[20px] truncate">{{ $n['title'] }}</p>
                        <span class="text-[11px] text-[#b4b6c0] flex-shrink-0">{{ $n['time'] }}</span>
                    </div>
                    <p class="text-[12px] text-[#b4b6c0] truncate">{{ $n['desc'] }}</p>
                </div>
            </div>
            @empty
            <div class="text-center py-8">
                <div class="mx-auto w-12 h-12 rounded-full bg-[rgba(255,77,127,0.08)] border border-[rgba(255,77,127,0.2)] flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-[#ff4d7f]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"/></svg>
                </div>
                <p class="text-[12px] text-[#b4b6c0]">{{ __('notifications.empty') }}</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Row 3: two parallel lists with progress bars (Contracts + Shipments) --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6">

    @foreach([$listLeft, $listRight] as $i => $list)
    @php
        // Alternate the section color so the two parallel lists feel like
        // distinct categories — left = teal, right = orange.
        $sectionTone = $i === 0
            ? ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]',  'text' => 'text-[#00d9b5]', 'icon' => $sectionDefaults['contracts']]
            : ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'icon' => $sectionDefaults['shipments']];
    @endphp
    <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
        <div class="flex items-start justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-[12px] {{ $sectionTone['bg'] }} border {{ $sectionTone['border'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-[18px] h-[18px] {{ $sectionTone['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $list['icon'] ?? $sectionTone['icon'] }}"/></svg>
                </div>
                <div>
                    <h3 class="text-[20px] font-semibold text-white leading-[28px] tracking-[-0.022em]">{{ $list['title'] }}</h3>
                    <p class="text-[14px] text-[#b4b6c0] mt-1">{{ $list['subtitle'] }}</p>
                </div>
            </div>
            @if(!empty($list['view_all_route']))
            <a href="{{ route($list['view_all_route']) }}" class="inline-flex items-center gap-1 text-[14px] font-medium text-[#4f7cff] hover:underline whitespace-nowrap">
                {{ __('common.view_all') }}
                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endif
        </div>

        <div class="space-y-3">
            @forelse($list['items'] as $item)
            <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}"
               class="block bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-[17px] hover:border-[#4f7cff]/40 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-[12px] text-[#b4b6c0] font-mono">{{ $item['id'] }}</p>
                        <p class="text-[16px] font-medium text-white leading-[24px] tracking-[-0.02em] mt-1 truncate">{{ $item['title'] }}</p>
                    </div>
                    @if(!empty($item['amount']))
                    <p class="text-[14px] font-semibold text-[#00d9b5] leading-[20px] whitespace-nowrap flex-shrink-0">{{ $item['amount'] }}</p>
                    @endif
                </div>
                @if(isset($item['progress']))
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <span class="text-[#b4b6c0]">{{ $item['progress_label'] ?? '' }}</span>
                        @if(!empty($item['eta']))
                            <span class="inline-flex items-center gap-1 text-[#b4b6c0]">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ $item['eta'] }}
                            </span>
                        @else
                            <span class="font-medium text-white">{{ $item['progress'] }}%</span>
                        @endif
                    </div>
                    <div class="w-full h-2 bg-[#252932] rounded-full overflow-hidden">
                        @php
                            // Color the progress bar by completion: red <30, orange 30-70, teal >70.
                            $p = (int) $item['progress'];
                            $progressColor = $p < 30 ? 'bg-[#ff4d7f]' : ($p < 70 ? 'bg-[#ffb020]' : 'bg-[#00d9b5]');
                        @endphp
                        <div class="h-full {{ $progressColor }} rounded-full transition-all" style="width: {{ $p }}%"></div>
                    </div>
                </div>
                @endif
            </a>
            @empty
            <div class="text-center py-10">
                <div class="mx-auto w-12 h-12 rounded-full {{ $sectionTone['bg'] }} border {{ $sectionTone['border'] }} flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 {{ $sectionTone['text'] }}" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $list['icon'] ?? $sectionTone['icon'] }}"/></svg>
                </div>
                <p class="text-[13px] text-[#b4b6c0]">{{ __('common.no_data') }}</p>
            </div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>

{{-- Row 4: bottom section (e.g. Pending Payments) — full-width 3-column grid --}}
@if(!empty($bottomSection))
<div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
    <div class="flex items-start justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[12px] bg-[rgba(139,92,246,0.1)] border border-[rgba(139,92,246,0.2)] flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $bottomSection['icon'] ?? $sectionDefaults['payments'] }}"/></svg>
            </div>
            <div>
                <h3 class="text-[20px] font-semibold text-white leading-[28px] tracking-[-0.022em]">{{ $bottomSection['title'] }}</h3>
                <p class="text-[14px] text-[#b4b6c0] mt-1">{{ $bottomSection['subtitle'] }}</p>
            </div>
        </div>
        @if(!empty($bottomSection['view_all_route']))
        <a href="{{ route($bottomSection['view_all_route']) }}" class="inline-flex items-center gap-1 text-[14px] font-medium text-[#4f7cff] hover:underline whitespace-nowrap">
            {{ __('common.view_all') }}
            <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($bottomSection['items'] as $item)
        <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}"
           class="block bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-[17px] hover:border-[#4f7cff]/40 transition-colors">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex-1 min-w-0">
                    <p class="text-[12px] text-[#b4b6c0] font-mono">{{ $item['id'] }}</p>
                    @if(!empty($item['supplier']))
                    <p class="inline-flex items-center gap-1.5 text-[14px] font-medium text-white leading-[20px] mt-1 truncate max-w-full">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-[#b4b6c0]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9"/></svg>
                        <span class="truncate">{{ $item['supplier'] }}</span>
                    </p>
                    @endif
                    @if(!empty($item['milestone']))
                    <p class="inline-flex items-center gap-1.5 text-[12px] text-[#b4b6c0] mt-1 truncate max-w-full">
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <span class="truncate">{{ $item['milestone'] }}</span>
                    </p>
                    @endif
                </div>
                @if(!empty($item['status']))
                @php $bs = $item['status']; @endphp
                <span class="inline-flex items-center gap-1.5 text-[12px] rounded-full px-2 h-6 {{ $badgeColors[$bs] ?? $badgeColors['scheduled'] }} flex-shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full {{ $dotColors[$bs] ?? $dotColors['draft'] }}"></span>
                    {{ $item['status_label'] ?? ucfirst(str_replace('_', ' ', $bs)) }}
                </span>
                @endif
            </div>
            <div class="border-t border-[rgba(255,255,255,0.1)] pt-3 flex items-center justify-between gap-2">
                <p class="text-[18px] font-semibold text-[#4f7cff] leading-[28px] tracking-[-0.024em] truncate">{{ $item['amount'] ?? '' }}</p>
                @if(!empty($item['date']))
                <div class="flex items-center gap-1.5 text-[12px] text-[#b4b6c0] flex-shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    {{ $item['date'] }}
                </div>
                @endif
            </div>
        </a>
        @empty
        <div class="sm:col-span-2 xl:col-span-3 text-center py-10">
            <div class="mx-auto w-12 h-12 rounded-full bg-[rgba(139,92,246,0.08)] border border-[rgba(139,92,246,0.2)] flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-[#8b5cf6]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            </div>
            <p class="text-[13px] text-[#b4b6c0]">{{ __('common.no_data') }}</p>
        </div>
        @endforelse
    </div>
</div>
@endif

@endsection
