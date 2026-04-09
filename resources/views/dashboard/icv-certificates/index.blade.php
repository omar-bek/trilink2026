@extends('layouts.dashboard', ['active' => 'icv-certificates'])
@section('title', __('icv.dashboard_title'))

@section('content')

<div class="flex items-start justify-between gap-4 mb-8 flex-wrap">
    <div>
        <h1 class="text-[28px] sm:text-[32px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('icv.dashboard_title') }}</h1>
        <p class="text-[14px] text-muted mt-1">{{ __('icv.dashboard_subtitle') }}</p>
    </div>
    <a href="{{ route('dashboard.icv-certificates.create') }}"
       class="inline-flex items-center gap-2 h-11 px-5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h transition-colors shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        {{ __('icv.upload_certificate') }}
    </a>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">
        {{ session('status') }}
    </div>
@endif
@if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[13px] text-[#ff4d7f]">
        {{ $errors->first() }}
    </div>
@endif

{{-- Stats grid --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-accent">{{ $stats['total'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_total') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#00d9b5]">{{ $stats['active'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_active') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['pending'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_pending') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['expired'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_expired') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-accent">{{ $stats['best'] !== null ? rtrim(rtrim(number_format($stats['best'], 2), '0'), '.') . '%' : '—' }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_best_score') }}</p>
    </div>
</div>

{{-- Certificates table --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full">
        <thead class="bg-page border-b border-th-border">
            <tr>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_issuer') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_number') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_score') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_issued') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_expires') }}</th>
                <th class="text-center px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($certificates as $cert)
                <tr class="border-b border-th-border hover:bg-page/40 transition-colors">
                    <td class="px-5 py-4">
                        <p class="text-[13px] font-semibold text-primary uppercase">{{ $cert->issuer }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[12px] font-mono text-muted">{{ $cert->certificate_number }}</p>
                    </td>
                    <td class="px-5 py-4 text-end">
                        <p class="text-[16px] font-bold text-accent">{{ rtrim(rtrim(number_format((float) $cert->score, 2), '0'), '.') }}%</p>
                    </td>
                    <td class="px-5 py-4 text-[12px] text-muted">{{ $cert->issued_date?->format('d M Y') }}</td>
                    <td class="px-5 py-4">
                        <p class="text-[12px] {{ $cert->isExpired() ? 'text-[#ff4d7f]' : 'text-muted' }}">{{ $cert->expires_date?->format('d M Y') }}</p>
                        @if(!$cert->isExpired() && $cert->daysUntilExpiry() <= 60 && $cert->isActive())
                            <p class="text-[10px] text-[#ffb020]">{{ __('icv.expires_in_days', ['days' => $cert->daysUntilExpiry()]) }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-center">
                        @if($cert->status === 'verified')
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/20 text-[#00d9b5] text-[11px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                                {{ __('icv.status_verified') }}
                            </span>
                        @elseif($cert->status === 'pending')
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#ffb020]/10 border border-[#ffb020]/20 text-[#ffb020] text-[11px] font-semibold">
                                {{ __('icv.status_pending') }}
                            </span>
                        @elseif($cert->status === 'rejected')
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#ff4d7f]/10 border border-[#ff4d7f]/20 text-[#ff4d7f] text-[11px] font-semibold">
                                {{ __('icv.status_rejected') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full bg-[#525252]/10 border border-th-border text-muted text-[11px] font-semibold">
                                {{ __('icv.status_expired') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-end">
                        <div class="inline-flex items-center gap-1">
                            <a href="{{ route('dashboard.icv-certificates.download', $cert->id) }}"
                               class="px-2 py-1 rounded-md text-[11px] font-medium text-accent hover:bg-accent/10">{{ __('common.download') }}</a>
                            @if($cert->status === 'pending')
                                <form method="POST" action="{{ route('dashboard.icv-certificates.destroy', $cert->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-medium text-[#ff4d7f] hover:bg-[#ff4d7f]/10">{{ __('common.delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @if($cert->status === 'rejected' && $cert->rejection_reason)
                    <tr class="border-b border-th-border">
                        <td colspan="7" class="px-5 py-2 bg-[#ff4d7f]/5">
                            <p class="text-[11px] text-[#ff4d7f]"><span class="font-semibold">{{ __('icv.rejection_reason') }}:</span> {{ $cert->rejection_reason }}</p>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <p class="text-[14px] text-muted">{{ __('icv.empty_state') }}</p>
                        <a href="{{ route('dashboard.icv-certificates.create') }}" class="text-[13px] font-semibold text-accent hover:underline mt-2 inline-block">{{ __('icv.upload_first') }} →</a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
