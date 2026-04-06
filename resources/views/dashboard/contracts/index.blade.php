@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', __('contracts.title'))

@section('content')

<x-dashboard.page-header :title="__('contracts.title')" :subtitle="__('contracts.subtitle')" :back="route('dashboard')" />

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <x-dashboard.stat-card :value="$stats['total']"     :label="__('contracts.total')"       color="slate"  icon='<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5"/>' />
    <x-dashboard.stat-card :value="$stats['active']"    :label="__('contracts.active')"      color="orange" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5"/><circle cx="12" cy="12" r="9"/>' />
    <x-dashboard.stat-card :value="$stats['completed']" :label="__('contracts.completed')"   color="green"  icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75"/>' />
    <x-dashboard.stat-card :value="$stats['value']"     :label="__('contracts.total_value')" color="purple" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307"/>' />
</div>

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h3 class="text-[18px] font-bold text-primary">{{ __('contracts.all') }}</h3>
        <div class="flex items-center gap-2">
            <select class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
                <option>All Status</option>
            </select>
            <select class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary focus:outline-none focus:border-accent/40 appearance-none">
                <option>Sort by Date</option>
            </select>
        </div>
    </div>

    <div class="space-y-4">
        @foreach($contracts as $c)
        <a href="{{ route('dashboard.contracts.show', ['id' => $c['id']]) }}" class="block bg-page border border-th-border rounded-xl p-5 hover:border-accent/30 hover:shadow-lg transition-all">
            <div class="flex items-start justify-between gap-4 mb-2 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[12px] font-mono text-muted">{{ $c['id'] }}</span>
                    <x-dashboard.status-badge :status="$c['status']" />
                </div>
                <div class="text-end">
                    <p class="text-[20px] font-bold text-[#10B981]">{{ $c['amount'] }}</p>
                </div>
            </div>

            <h3 class="text-[16px] font-bold text-accent mb-1">{{ $c['title'] }}</h3>
            <p class="text-[12px] text-muted mb-3">{{ __('contracts.supplier') }}: {{ $c['supplier'] }}</p>

            <div class="grid grid-cols-2 gap-4 text-[11px] text-muted mb-3">
                <span>{{ __('common.started') }}: {{ $c['started'] }}</span>
                <span class="text-end">{{ __('common.expected') }}: {{ $c['expected'] }}</span>
            </div>

            <div>
                <div class="flex items-center justify-between text-[11px] text-muted mb-1">
                    <span>{{ $c['progress_label'] }}</span>
                    <span>{{ $c['progress'] }}%</span>
                </div>
                <div class="w-full h-2 bg-elevated rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all" style="width: {{ $c['progress'] }}%; background: {{ $c['progress_color'] }};"></div>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>

@endsection
