@extends('layouts.dashboard', ['active' => 'admin-anti-collusion'])
@section('title', __('anticollusion.admin_title'))

@section('content')

<div class="mb-6">
    <h1 class="text-[28px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('anticollusion.admin_title') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('anticollusion.admin_subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">{{ session('status') }}</div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['open'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('anticollusion.stat_open') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['investigating'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('anticollusion.stat_investigating') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-muted">{{ $stats['false_positive'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('anticollusion.stat_false_positive') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ef4444]">{{ $stats['confirmed'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('anticollusion.stat_confirmed') }}</p>
    </div>
</div>

<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <select name="status" class="flex-1 bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary">
        <option value="">{{ __('common.all') }}</option>
        @foreach(['open', 'investigating', 'false_positive', 'confirmed'] as $s)
            <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('anticollusion.status_' . $s) }}</option>
        @endforeach
    </select>
    <select name="severity" class="flex-1 bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary">
        <option value="">{{ __('anticollusion.all_severities') }}</option>
        @foreach(['critical', 'high', 'medium'] as $sev)
            <option value="{{ $sev }}" @selected($filters['severity'] === $sev)>{{ ucfirst($sev) }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-5 h-12 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
</form>

<div class="space-y-4">
    @forelse($alerts as $alert)
        @php
            $sevColors = ['critical' => '#ef4444', 'high' => '#ffb020', 'medium' => '#525252'];
            $sc = $sevColors[$alert->severity] ?? '#525252';
            $statusColors = ['open' => '#ff4d7f', 'investigating' => '#ffb020', 'false_positive' => '#525252', 'confirmed' => '#ef4444'];
            $stc = $statusColors[$alert->status] ?? '#525252';
        @endphp
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold uppercase tracking-wider" style="background: {{ $sc }}1A; border: 1px solid {{ $sc }}33; color: {{ $sc }};">{{ $alert->severity }}</span>
                        <span class="inline-flex items-center h-[22px] px-2 rounded-full text-[10px] font-bold" style="background: {{ $stc }}1A; border: 1px solid {{ $stc }}33; color: {{ $stc }};">{{ __('anticollusion.status_' . $alert->status) }}</span>
                        <span class="text-[11px] text-muted font-mono">{{ $alert->type }}</span>
                    </div>
                    <p class="text-[14px] font-semibold text-primary">RFQ {{ $alert->rfq_number ?? '#' . $alert->rfq_id }} — {{ $alert->rfq_title ?? '' }}</p>
                    <p class="text-[11px] text-muted">{{ \Carbon\Carbon::parse($alert->created_at)->format('d M Y, H:i') }}</p>
                </div>
                @if($alert->status === 'open' || $alert->status === 'investigating')
                <div class="flex items-center gap-1 flex-shrink-0">
                    <form method="POST" action="{{ route('admin.anti-collusion.update', $alert->id) }}" class="inline">
                        @csrf
                        <input type="hidden" name="status" value="investigating">
                        <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-[#ffb020] hover:bg-[#ffb020]/10">{{ __('anticollusion.label_investigating') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.anti-collusion.update', $alert->id) }}" class="inline">
                        @csrf
                        <input type="hidden" name="status" value="false_positive">
                        <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-muted hover:bg-page">{{ __('anticollusion.label_false_positive') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.anti-collusion.update', $alert->id) }}" class="inline">
                        @csrf
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-[#ef4444] hover:bg-[#ef4444]/10">{{ __('anticollusion.label_confirmed') }}</button>
                    </form>
                </div>
                @endif
            </div>

            <div class="bg-page border border-th-border rounded-xl p-3 text-[12px] font-mono text-muted">
                @foreach($alert->evidence as $key => $val)
                    <div class="flex items-start gap-2 mb-1 last:mb-0">
                        <span class="font-semibold text-primary min-w-[120px]">{{ $key }}:</span>
                        <span class="break-all">{{ is_array($val) ? implode(', ', $val) : $val }}</span>
                    </div>
                @endforeach
            </div>

            @if($alert->admin_notes)
                <p class="mt-3 text-[12px] text-muted"><span class="font-semibold text-primary">{{ __('anticollusion.admin_notes') }}:</span> {{ $alert->admin_notes }}</p>
            @endif
        </div>
    @empty
        <div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
            <p class="text-[14px] text-muted">{{ __('anticollusion.empty_state') }}</p>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $alerts->links() }}</div>

@endsection
