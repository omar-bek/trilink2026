@extends('layouts.dashboard', ['active' => 'admin-webhooks'])
@section('title', __('admin.webhooks.deliveries_title'))

@section('content')

<x-admin.navbar active="webhooks" />

<div class="mb-6">
    <a href="{{ route('admin.webhooks.index') }}" class="inline-flex items-center gap-2 text-[13px] text-muted hover:text-primary mb-3">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('common.back') }}
    </a>
    <h2 class="text-[20px] font-bold text-primary">{{ $endpoint->label ?? 'Webhook' }} — {{ __('admin.webhooks.deliveries_title') }}</h2>
    <p class="text-[13px] text-muted font-mono">{{ $endpoint->url }}</p>
</div>

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full text-[13px]">
        <thead>
            <tr class="border-b border-th-border bg-surface-2">
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">Event</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">Status</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">HTTP</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">Attempt</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveries as $d)
            @php
                $sc = ['success' => '#00d9b5', 'failed' => '#ff4d7f', 'pending' => '#ffb020'][$d->status] ?? '#525252';
            @endphp
            <tr class="border-b border-th-border/50 hover:bg-surface-2">
                <td class="py-3 px-4 font-mono text-primary">{{ $d->event }}</td>
                <td class="py-3 px-4 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold" style="background: {{ $sc }}1A; color: {{ $sc }};">{{ $d->status }}</span></td>
                <td class="py-3 px-4 text-center text-muted">{{ $d->response_status ?? '—' }}</td>
                <td class="py-3 px-4 text-center text-muted">{{ $d->attempt }}</td>
                <td class="py-3 px-4 text-right text-muted">{{ $d->created_at?->format('d M Y, H:i') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="py-12 text-center text-[14px] text-muted">{{ __('admin.webhooks.no_deliveries') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $deliveries->links() }}</div>

@endsection
