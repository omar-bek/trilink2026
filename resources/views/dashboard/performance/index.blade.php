@extends('layouts.dashboard', ['active' => 'performance'])
@section('title', __('performance.title'))

@section('content')

<x-dashboard.page-header :title="__('performance.title')" :subtitle="__('performance.subtitle')" />

{{-- Top stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-[#3B82F6]/10 border border-[#3B82F6]/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-[#3B82F6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
            </div>
            <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
        </div>
        <p class="text-[36px] font-bold text-primary leading-none">{{ $stats['total_bids'] }}</p>
        <p class="text-[13px] text-muted mt-2">{{ __('performance.total_bids') }}</p>
        <p class="text-[11px] text-[#10B981] mt-1">+12% this month</p>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-[#10B981]/10 border border-[#10B981]/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
        </div>
        <p class="text-[36px] font-bold text-primary leading-none">{{ $stats['bids_won'] }}</p>
        <p class="text-[13px] text-muted mt-2">{{ __('performance.bids_won') }}</p>
        <p class="text-[11px] text-[#10B981] mt-1">{{ __('performance.win_rate') }}: {{ $stats['win_rate'] }}%</p>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-[#F59E0B]/10 border border-[#F59E0B]/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-[#F59E0B]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2.25 18.75a60.07 60.07 0 0115.797 2.101"/></svg>
            </div>
            <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
        </div>
        <p class="text-[28px] font-bold text-primary leading-none">{{ $stats['total_revenue'] }}</p>
        <p class="text-[13px] text-muted mt-2">{{ __('performance.total_revenue') }}</p>
        <p class="text-[11px] text-[#10B981] mt-1">+18% this quarter</p>
    </div>

    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
            </div>
            <svg class="w-4 h-4 text-[#10B981]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"/></svg>
        </div>
        <p class="text-[36px] font-bold text-primary leading-none">{{ $stats['avg_rating'] }}</p>
        <p class="text-[13px] text-muted mt-2">{{ __('performance.avg_rating') }}</p>
        <p class="text-[11px] text-[#F59E0B] mt-1">★★★★★</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Monthly performance --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('performance.monthly') }}</h3>
        <div class="space-y-4">
            @foreach($monthly as $m)
            <div class="bg-page border border-th-border rounded-xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[14px] font-bold text-primary">{{ $m['label'] }}</p>
                    <p class="text-[14px] font-bold text-[#10B981]">{{ $m['revenue'] }}</p>
                </div>
                <div class="grid grid-cols-3 gap-4 mb-3">
                    <div>
                        <p class="text-[11px] text-muted">{{ __('supplier.bids_submitted') }}</p>
                        <p class="text-[16px] font-bold text-primary">{{ $m['submitted'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-muted">{{ __('performance.bids_won') }}</p>
                        <p class="text-[16px] font-bold text-[#10B981]">{{ $m['won'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-muted">{{ __('performance.win_rate') }}</p>
                        <p class="text-[16px] font-bold text-primary">{{ $m['win_rate'] }}%</p>
                    </div>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#10B981] to-[#3B82F6] rounded-full" style="width: {{ $m['win_rate'] }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Quality metrics --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('performance.quality') }}</h3>
        <div class="space-y-5">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[13px] text-muted">{{ __('performance.on_time') }}</p>
                    <p class="text-[16px] font-bold text-primary">{{ $quality['on_time'] }}%</p>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-[#10B981] rounded-full" style="width: {{ $quality['on_time'] }}%"></div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[13px] text-muted">{{ __('performance.customer_satisfaction') }}</p>
                    <p class="text-[16px] font-bold text-primary">{{ $quality['customer_satisfaction'] }}/5.0</p>
                </div>
                <div class="w-full h-1.5 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full bg-[#F59E0B] rounded-full" style="width: {{ ($quality['customer_satisfaction'] / 5) * 100 }}%"></div>
                </div>
            </div>

            <div class="pt-4 border-t border-th-border">
                <p class="text-[13px] text-muted mb-1">{{ __('performance.avg_response') }}</p>
                <p class="text-[20px] font-bold text-primary">{{ $quality['avg_response_hours'] }} hours</p>
                <p class="text-[11px] text-[#10B981] mt-1">Excellent</p>
            </div>
        </div>
    </div>
</div>

@endsection
