@extends('layouts.dashboard', ['active' => 'admin-webhooks'])
@section('title', __('admin.webhooks.title'))

@section('content')

<x-admin.navbar active="webhooks" />

<div class="mb-6">
    <h2 class="text-[20px] font-bold text-primary">{{ __('admin.webhooks.title') }}</h2>
    <p class="text-[13px] text-muted mt-1">{{ __('admin.webhooks.subtitle') }}</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ $stats['total_endpoints'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.webhooks.total_endpoints') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#00d9b5]">{{ $stats['active'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.webhooks.active') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ number_format($stats['total_deliveries']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.webhooks.total_deliveries') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold {{ $stats['failed'] > 0 ? 'text-[#ff4d7f]' : 'text-muted' }}">{{ number_format($stats['failed']) }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.webhooks.failed') }}</p>
    </div>
</div>

{{-- Endpoints --}}
<div class="space-y-3">
    @forelse($endpoints as $ep)
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full {{ $ep->is_active ? 'bg-[#00d9b5]' : 'bg-[#525252]' }}"></span>
                    <p class="text-[14px] font-semibold text-primary">{{ $ep->label ?? 'Unnamed' }}</p>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent/10 text-accent">{{ $ep->company?->name ?? '—' }}</span>
                </div>
                <p class="text-[12px] text-muted font-mono break-all">{{ $ep->url }}</p>
                <p class="text-[11px] text-muted mt-1">{{ $ep->deliveries_count }} deliveries &middot; {{ $ep->failure_count }} failures &middot; Last: {{ $ep->last_delivered_at?->diffForHumans() ?? 'never' }}</p>
            </div>
            <a href="{{ route('admin.webhooks.deliveries', $ep->id) }}" class="px-3 h-8 rounded-lg text-[11px] font-semibold text-accent border border-accent/30 hover:bg-accent/5 inline-flex items-center">{{ __('admin.webhooks.view_deliveries') }}</a>
        </div>
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
        <p class="text-[14px] text-muted">{{ __('admin.webhooks.empty') }}</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $endpoints->links() }}</div>

@endsection
