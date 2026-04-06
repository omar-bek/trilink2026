@extends('layouts.dashboard', ['active' => 'dashboard'])
@section('title', __('dashboard.title') . ' · ' . __('app.name'))

@section('content')

<x-dashboard.page-header
    :title="__('dashboard.welcome') . ', ' . $user['name']"
    :subtitle="__('dashboard.title')"
/>

{{-- Top stats grid (role-aware) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    @foreach($stats as $stat)
        <x-dashboard.stat-card :value="$stat['value']" :label="$stat['label']" :color="$stat['color']" />
    @endforeach
</div>

{{-- Quick actions / activity --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Recent activity --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">Recent Activity</h3>
        <div class="space-y-4">
            @foreach($activity as $item)
            @php
            $colors = [
                'green' => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]'],
                'blue' => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]'],
                'purple' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]'],
                'orange' => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]'],
            ];
            $c = $colors[$item['color']];
            @endphp
            <div class="flex items-start gap-4 p-3 rounded-xl hover:bg-surface-2 transition-colors">
                <div class="w-10 h-10 rounded-xl {{ $c['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 {{ $c['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[14px] font-semibold text-primary">{{ $item['title'] }}</p>
                    <p class="text-[12px] text-muted truncate">{{ $item['desc'] }}</p>
                </div>
                <span class="text-[11px] text-faint flex-shrink-0">{{ $item['time'] }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Quick links (role-aware) --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[16px] font-bold text-primary mb-5">{{ __('dashboard.quick_actions') ?? 'Quick Actions' }}</h3>
        <p class="text-[11px] text-muted mb-4">{{ __('role.' . $role) }}</p>
        <div class="space-y-3">
            @foreach($quickActions as $i => $action)
            <a href="{{ route($action['route']) }}" class="flex items-center gap-3 p-3 rounded-xl {{ $i === 0 ? 'bg-accent/10 border border-accent/20 hover:bg-accent/15' : 'bg-surface-2 hover:bg-elevated' }} transition-colors">
                <div class="w-9 h-9 rounded-lg {{ $i === 0 ? 'bg-accent text-white' : 'bg-' . $action['color'] . '-500/10 text-' . $action['color'] . '-500' }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/></svg>
                </div>
                <p class="text-[13px] font-bold text-primary">{{ $action['label'] }}</p>
            </a>
            @endforeach
        </div>
    </div>
</div>

@endsection
