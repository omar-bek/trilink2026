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
 */

// Per-color Tailwind class lookup — cards use a colored *border* + matching value text.
$cardColors = [
    'purple' => ['border' => 'border-[#8B5CF6]/40', 'text' => 'text-[#8B5CF6]', 'bg' => 'bg-[#8B5CF6]/10'],
    'blue'   => ['border' => 'border-[#3B82F6]/40', 'text' => 'text-[#3B82F6]', 'bg' => 'bg-[#3B82F6]/10'],
    'orange' => ['border' => 'border-[#F59E0B]/40', 'text' => 'text-[#F59E0B]', 'bg' => 'bg-[#F59E0B]/10'],
    'green'  => ['border' => 'border-[#10B981]/40', 'text' => 'text-[#10B981]', 'bg' => 'bg-[#10B981]/10'],
    'red'    => ['border' => 'border-[#EF4444]/40', 'text' => 'text-[#EF4444]', 'bg' => 'bg-[#EF4444]/10'],
];

$notifColors = [
    'blue'   => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]'],
    'green'  => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]'],
    'orange' => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]'],
    'purple' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]'],
    'red'    => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]'],
];

$badgeColors = [
    'open'      => 'text-[#10B981] bg-[#10B981]/10 border-[#10B981]/20',
    'draft'     => 'text-muted bg-surface-2 border-th-border',
    'pending'   => 'text-[#F59E0B] bg-[#F59E0B]/10 border-[#F59E0B]/20',
    'urgent'    => 'text-[#EF4444] bg-[#EF4444]/10 border-[#EF4444]/20',
    'due_soon'  => 'text-[#F59E0B] bg-[#F59E0B]/10 border-[#F59E0B]/20',
    'scheduled' => 'text-[#10B981] bg-[#10B981]/10 border-[#10B981]/20',
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <h1 class="text-[28px] sm:text-[36px] font-bold text-primary leading-tight">{{ __('nav.dashboard') }}</h1>
        <p class="text-[14px] text-muted mt-1">
            {{ __('dashboard.welcome') }}, {{ $user['name'] }}@if(!empty($user['company'])) · {{ $user['company'] }}@endif
        </p>
    </div>
    @if(!empty($headerAction))
    <a href="{{ route($headerAction['route']) }}"
       class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $headerAction['icon'] }}"/></svg>
        {{ $headerAction['label'] }}
    </a>
    @endif
</div>

{{-- Row 1: 4 KPI cards (colored border + big number + icon) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    @foreach($stats as $stat)
        @php $c = $cardColors[$stat['color']] ?? $cardColors['blue']; @endphp
        <div class="bg-surface border-2 {{ $c['border'] }} rounded-2xl p-6">
            <div class="flex items-start justify-between mb-4">
                <p class="text-[44px] font-bold {{ $c['text'] }} leading-none">{{ $stat['value'] }}</p>
                <div class="w-9 h-9 rounded-lg {{ $c['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/></svg>
                </div>
            </div>
            <p class="text-[13px] text-muted">{{ $stat['label'] }}</p>
        </div>
    @endforeach
</div>

{{-- Row 2: primary list (2/3) + Notifications (1/3) --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-1">
            <h3 class="text-[18px] font-bold text-primary">{{ $primaryList['title'] }}</h3>
            @if(!empty($primaryList['view_all_route']))
            <a href="{{ route($primaryList['view_all_route']) }}" class="text-[13px] font-semibold text-accent hover:underline">View All →</a>
            @endif
        </div>
        <p class="text-[12px] text-muted mb-5">{{ $primaryList['subtitle'] }}</p>

        <div class="space-y-3">
            @forelse($primaryList['items'] as $item)
            <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}" class="block bg-page border border-th-border rounded-xl p-5 hover:border-accent/30 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-2 flex-wrap">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="text-[11px] font-mono text-muted">{{ $item['id'] }}</span>
                        @if(!empty($item['status']))
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold rounded-full px-2 py-0.5 border {{ $badgeColors[$item['status']] ?? $badgeColors['draft'] }}">
                                ● {{ ucfirst(str_replace('_', ' ', $item['status'])) }}
                            </span>
                        @endif
                    </div>
                </div>
                <p class="text-[15px] font-bold text-accent mb-2">{{ $item['title'] }}</p>
                @if(!empty($item['amount']))
                <p class="text-[16px] font-bold text-[#10B981] mb-1">{{ $item['amount'] }}</p>
                @endif
                <div class="flex items-center gap-4 text-[11px] text-muted">
                    @if(!empty($item['meta1']))<span>{{ $item['meta1'] }}</span>@endif
                    @if(!empty($item['meta2']))
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $item['meta2'] }}
                    </span>
                    @endif
                </div>
            </a>
            @empty
            <p class="text-[13px] text-muted text-center py-8">{{ __('common.no_data') }}</p>
            @endforelse
        </div>
    </div>

    {{-- Notifications --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[18px] font-bold text-primary">{{ __('notifications.title') }}</h3>
            @if(!empty($notifications['count']))
            <span class="bg-[#EF4444] text-white text-[11px] font-bold px-2 py-0.5 rounded-full min-w-[22px] text-center">{{ $notifications['count'] }}</span>
            @endif
        </div>
        <div class="space-y-3">
            @forelse($notifications['items'] ?? [] as $n)
            @php $nc = $notifColors[$n['color'] ?? 'blue'] ?? $notifColors['blue']; @endphp
            <div class="bg-page border border-th-border rounded-xl p-3 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg {{ $nc['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 {{ $nc['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $n['icon'] }}"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[12px] font-bold text-primary leading-tight">{{ $n['title'] }}</p>
                        <span class="text-[10px] text-faint flex-shrink-0">{{ $n['time'] }}</span>
                    </div>
                    <p class="text-[11px] text-muted truncate mt-0.5">{{ $n['desc'] }}</p>
                </div>
            </div>
            @empty
            <p class="text-[12px] text-muted text-center py-4">{{ __('notifications.empty') }}</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Row 3: two parallel lists with progress bars --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    @foreach([$listLeft, $listRight] as $list)
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-1">
            <h3 class="text-[18px] font-bold text-primary">{{ $list['title'] }}</h3>
            @if(!empty($list['view_all_route']))
            <a href="{{ route($list['view_all_route']) }}" class="text-[13px] font-semibold text-accent hover:underline">View All →</a>
            @endif
        </div>
        <p class="text-[12px] text-muted mb-5">{{ $list['subtitle'] }}</p>

        <div class="space-y-3">
            @forelse($list['items'] as $item)
            <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}" class="block bg-page border border-th-border rounded-xl p-4 hover:border-accent/30 transition-colors">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="text-[11px] font-mono text-muted">{{ $item['id'] }}</p>
                    @if(!empty($item['amount']))
                    <p class="text-[14px] font-bold text-[#10B981]">{{ $item['amount'] }}</p>
                    @endif
                </div>
                <p class="text-[14px] font-bold text-primary mb-3">{{ $item['title'] }}</p>
                @if(isset($item['progress']))
                <div>
                    <div class="flex items-center justify-between text-[11px] mb-1">
                        <span class="text-muted">{{ $item['progress_label'] ?? '' }}</span>
                        @if(!empty($item['eta']))
                            <span class="inline-flex items-center gap-1 text-muted">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ $item['eta'] }}
                            </span>
                        @else
                            <span class="font-bold text-primary">{{ $item['progress'] }}%</span>
                        @endif
                    </div>
                    <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-[#10B981] to-[#3B82F6] rounded-full" style="width: {{ $item['progress'] }}%"></div>
                    </div>
                </div>
                @endif
            </a>
            @empty
            <p class="text-[13px] text-muted text-center py-8">{{ __('common.no_data') }}</p>
            @endforelse
        </div>
    </div>
    @endforeach
</div>

{{-- Row 4: bottom section (e.g. Pending Payments) — full-width 3-column grid --}}
@if(!empty($bottomSection))
<div class="bg-surface border border-th-border rounded-2xl p-6">
    <div class="flex items-start justify-between mb-1">
        <h3 class="text-[18px] font-bold text-primary">{{ $bottomSection['title'] }}</h3>
        @if(!empty($bottomSection['view_all_route']))
        <a href="{{ route($bottomSection['view_all_route']) }}" class="text-[13px] font-semibold text-accent hover:underline">View All →</a>
        @endif
    </div>
    <p class="text-[12px] text-muted mb-5">{{ $bottomSection['subtitle'] }}</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @forelse($bottomSection['items'] as $item)
        <a href="{{ !empty($item['href']) ? $item['href'] : '#' }}" class="block bg-page border border-th-border rounded-xl p-5 hover:border-accent/30 transition-colors">
            <div class="flex items-start justify-between gap-2 mb-3 flex-wrap">
                <p class="text-[11px] font-mono text-muted">{{ $item['id'] }}</p>
                @if(!empty($item['status']))
                <span class="text-[10px] font-bold rounded-full px-2 py-0.5 border {{ $badgeColors[$item['status']] ?? $badgeColors['scheduled'] }}">
                    {{ $item['status_label'] ?? ucfirst(str_replace('_', ' ', $item['status'])) }}
                </span>
                @endif
            </div>
            @if(!empty($item['supplier']))
            <p class="text-[13px] font-bold text-primary mb-1">{{ $item['supplier'] }}</p>
            @endif
            @if(!empty($item['milestone']))
            <p class="text-[11px] text-muted mb-3">{{ $item['milestone'] }}</p>
            @endif
            <p class="text-[18px] font-bold text-accent mb-1">{{ $item['amount'] ?? '' }}</p>
            @if(!empty($item['date']))
            <div class="flex items-center gap-1 text-[11px] text-muted">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                {{ $item['date'] }}
            </div>
            @endif
        </a>
        @empty
        <p class="md:col-span-3 text-[13px] text-muted text-center py-8">{{ __('common.no_data') }}</p>
        @endforelse
    </div>
</div>
@endif

@endsection
