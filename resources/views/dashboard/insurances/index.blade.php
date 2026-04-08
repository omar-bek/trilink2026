@extends('layouts.dashboard', ['active' => 'insurances'])
@section('title', __('insurances.title'))

@section('content')

<x-dashboard.page-header :title="__('insurances.title')" :subtitle="__('insurances.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif
@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Stat strip --}}
@php
    $insStats = [
        ['label' => __('insurances.stat_total'),    'value' => $stats['total'],    'border' => 'border-th-border',     'text' => 'text-muted',         'icon' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
        ['label' => __('insurances.stat_verified'), 'value' => $stats['verified'], 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400',   'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => __('insurances.stat_pending'),  'value' => $stats['pending'],  'border' => 'border-amber-500/30',   'text' => 'text-amber-400',     'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => __('insurances.stat_expired'),  'value' => $stats['expired'],  'border' => 'border-red-500/30',     'text' => 'text-red-400',       'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z'],
    ];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    @foreach($insStats as $s)
    <div class="bg-surface border-2 {{ $s['border'] }} rounded-2xl px-4 py-3 transition-transform hover:-translate-y-0.5">
        <div class="flex items-start justify-between mb-1">
            <p class="text-[10px] uppercase tracking-wider {{ $s['text'] }} font-semibold">{{ $s['label'] }}</p>
            <svg class="w-4 h-4 {{ $s['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
        </div>
        <p class="text-[22px] font-bold text-primary">{{ $s['value'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-3">
        @forelse($policies as $policy)
        <div class="bg-surface border border-th-border rounded-2xl p-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <span class="text-[14px] font-bold text-primary">{{ __('insurances.type_' . $policy->type) }}</span>
                        @if($policy->status === \App\Models\CompanyInsurance::STATUS_VERIFIED)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('insurances.status_verified') }}</span>
                        @elseif($policy->status === \App\Models\CompanyInsurance::STATUS_PENDING)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">{{ __('insurances.status_pending') }}</span>
                        @elseif($policy->status === \App\Models\CompanyInsurance::STATUS_REJECTED)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400 border border-red-500/20">{{ __('insurances.status_rejected') }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">{{ __('insurances.status_expired') }}</span>
                        @endif
                    </div>
                    <p class="text-[12px] text-muted mb-1">{{ $policy->insurer }} · {{ $policy->policy_number }}</p>
                    <p class="text-[12px] text-muted">
                        {{ __('insurances.coverage') }}: <span class="text-primary font-semibold">{{ number_format((float) $policy->coverage_amount, 2) }} {{ $policy->currency }}</span>
                        ·
                        {{ __('insurances.expires') }}: <span class="text-primary font-semibold">{{ optional($policy->expires_at)->format('M j, Y') }}</span>
                    </p>
                    @if($policy->status === \App\Models\CompanyInsurance::STATUS_REJECTED && $policy->rejection_reason)
                        <p class="mt-2 text-[12px] text-red-400">{{ __('common.reason') }}: {{ $policy->rejection_reason }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('dashboard.insurances.destroy', $policy->id) }}" onsubmit="return confirm('{{ __('insurances.confirm_delete') }}');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold">{{ __('common.delete') }}</button>
                </form>
            </div>
        </div>
        @empty
        <div class="bg-surface border border-th-border rounded-2xl p-10 sm:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.623 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </div>
            <p class="text-[14px] font-bold text-primary">{{ __('insurances.empty') }}</p>
        </div>
        @endforelse
    </div>

    <div>
        <div class="bg-surface border border-th-border rounded-2xl p-6 sticky top-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('insurances.upload_new') }}</h3>
            <form method="POST" action="{{ route('dashboard.insurances.store') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.type') }}</label>
                    <select name="type" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                        @foreach(\App\Models\CompanyInsurance::TYPES as $type)
                            <option value="{{ $type }}">{{ __('insurances.type_' . $type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.insurer') }}</label>
                    <input type="text" name="insurer" required maxlength="191" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.policy_number') }}</label>
                    <input type="text" name="policy_number" required maxlength="128" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary font-mono focus:outline-none focus:border-accent" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.coverage') }}</label>
                        <input type="number" step="0.01" min="0" name="coverage_amount" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('catalog.currency') }}</label>
                        <select name="currency" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                            @foreach(['AED', 'USD', 'EUR', 'SAR'] as $cur)
                                <option value="{{ $cur }}">{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.starts_at') }}</label>
                        <input type="date" name="starts_at" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                    <div>
                        <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.expires_at') }}</label>
                        <input type="date" name="expires_at" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] text-muted uppercase tracking-wider mb-1">{{ __('insurances.policy_file') }}</label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[12px] text-primary" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.5)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    {{ __('insurances.upload') }}
                </button>
            </form>
        </div>
    </div>
</div>

@endsection
