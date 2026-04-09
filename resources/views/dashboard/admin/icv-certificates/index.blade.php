@extends('layouts.dashboard', ['active' => 'admin-icv-certificates'])
@section('title', __('icv.admin_index_title'))

@section('content')

<div class="mb-6">
    <h1 class="text-[28px] font-bold text-primary leading-tight tracking-[-0.02em]">{{ __('icv.admin_index_title') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('icv.admin_index_subtitle') }}</p>
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

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['pending'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_pending') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#00d9b5]">{{ $stats['verified'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_verified') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['rejected'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_rejected') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-muted">{{ $stats['expired'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('icv.stat_expired') }}</p>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-surface border border-th-border rounded-2xl p-4 mb-6 flex flex-col lg:flex-row gap-3">
    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('icv.search_placeholder') }}"
           class="flex-1 bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/60">
    <select name="status" class="bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/60">
        <option value="">{{ __('common.all') }}</option>
        <option value="pending"  @selected($filters['status'] === 'pending')>{{ __('icv.status_pending') }}</option>
        <option value="verified" @selected($filters['status'] === 'verified')>{{ __('icv.status_verified') }}</option>
        <option value="rejected" @selected($filters['status'] === 'rejected')>{{ __('icv.status_rejected') }}</option>
        <option value="expired"  @selected($filters['status'] === 'expired')>{{ __('icv.status_expired') }}</option>
    </select>
    <button type="submit" class="px-5 h-12 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
</form>

{{-- List --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full">
        <thead class="bg-page border-b border-th-border">
            <tr>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_company') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_issuer') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_number') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_score') }}</th>
                <th class="text-start px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('icv.col_expires') }}</th>
                <th class="text-center px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-end px-5 py-3 text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($certificates as $cert)
                <tr class="border-b border-th-border hover:bg-page/40 transition-colors">
                    <td class="px-5 py-4">
                        <p class="text-[13px] font-semibold text-primary">{{ $cert->company->name ?? '—' }}</p>
                        <p class="text-[11px] text-muted font-mono">{{ $cert->company->registration_number ?? '' }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[13px] uppercase font-semibold text-primary">{{ $cert->issuer }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-[12px] font-mono text-muted">{{ $cert->certificate_number }}</p>
                    </td>
                    <td class="px-5 py-4 text-end">
                        <p class="text-[16px] font-bold text-accent">{{ rtrim(rtrim(number_format((float) $cert->score, 2), '0'), '.') }}%</p>
                    </td>
                    <td class="px-5 py-4 text-[12px] text-muted">{{ $cert->expires_date?->format('d M Y') }}</td>
                    <td class="px-5 py-4 text-center">
                        @php $colors = ['pending' => '#ffb020', 'verified' => '#00d9b5', 'rejected' => '#ff4d7f', 'expired' => '#525252']; $c = $colors[$cert->status] ?? '#525252'; @endphp
                        <span class="inline-flex items-center gap-1.5 h-[24px] px-2.5 rounded-full text-[11px] font-semibold" style="background: {{ $c }}1A; border: 1px solid {{ $c }}33; color: {{ $c }};">
                            {{ __('icv.status_' . $cert->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-end">
                        <div class="inline-flex items-center gap-1">
                            <a href="{{ route('admin.icv-certificates.download', $cert->id) }}" class="px-2 py-1 rounded-md text-[11px] font-medium text-accent hover:bg-accent/10">{{ __('common.view') }}</a>
                            @if($cert->status === 'pending')
                                <form method="POST" action="{{ route('admin.icv-certificates.approve', $cert->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-[#00d9b5] hover:bg-[#00d9b5]/10">{{ __('icv.approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.icv-certificates.reject', $cert->id) }}" class="inline"
                                      onsubmit="const r = prompt('{{ __('icv.rejection_prompt') }}'); if (!r) return false; this.reason.value = r;">
                                    @csrf
                                    <input type="hidden" name="reason">
                                    <button type="submit" class="px-2 py-1 rounded-md text-[11px] font-semibold text-[#ff4d7f] hover:bg-[#ff4d7f]/10">{{ __('icv.reject') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-12 text-center"><p class="text-[14px] text-muted">{{ __('icv.empty_admin') }}</p></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $certificates->links() }}
</div>

@endsection
