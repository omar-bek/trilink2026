@extends('layouts.dashboard', ['active' => 'bank-guarantees'])
@section('title', __('bg.title'))

@section('content')

<x-dashboard.page-header :title="__('bg.title')" :subtitle="__('bg.subtitle')">
    <x-slot:actions>
        <button type="button" onclick="document.getElementById('register-bg-modal').classList.remove('hidden')"
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
            {{ __('bg.register') }}
        </button>
    </x-slot:actions>
</x-dashboard.page-header>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <x-dashboard.stat-card :value="number_format($stats['total'])" :label="__('bg.total')" color="blue"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6"/>' />
    <x-dashboard.stat-card :value="number_format($stats['live'])" :label="__('bg.live')" color="green"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' />
    <x-dashboard.stat-card :value="number_format($stats['expiring_soon'])" :label="__('bg.expiring_30d')" color="orange"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/>' />
    <x-dashboard.stat-card :value="number_format($stats['called'])" :label="__('bg.called_count')" color="red"
        icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374"/>' />
</div>

<div class="bg-surface border border-th-border rounded-2xl p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-center">
        <select name="type" class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary">
            <option value="">{{ __('bg.all_types') }}</option>
            @foreach($types as $t)
                <option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ __('bg.type.' . $t->value) }}</option>
            @endforeach
        </select>
        <select name="status" class="bg-page border border-th-border rounded-xl px-3 py-2 text-[12px] text-primary">
            <option value="">{{ __('bg.all_statuses') }}</option>
            @foreach($statuses as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ __('bg.status.' . $s->value) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('common.filter') }}</button>
    </form>
</div>

<div class="space-y-3">
    @forelse($guarantees as $bg)
        @php
            $statusColor = match($bg->status?->value) {
                'live', 'issued' => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/20',
                'called' => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/20',
                'reduced' => 'bg-[#ffb020]/10 text-[#ffb020] border-[#ffb020]/20',
                'expired', 'cancelled', 'returned' => 'bg-page text-muted border-th-border',
                default => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/20',
            };
            $daysToExpiry = $bg->expiry_date?->diffInDays(now(), false);
        @endphp
        <a href="{{ route('dashboard.bank-guarantees.show', ['id' => $bg->id]) }}" class="block bg-surface border border-th-border rounded-2xl p-5 hover:border-accent/30 transition-all">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="font-mono text-[12px] text-muted">{{ $bg->bg_number }}</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border {{ $statusColor }}">
                            {{ __('bg.status.' . $bg->status?->value) }}
                        </span>
                        <span class="text-[11px] text-muted">{{ __('bg.type.' . $bg->type?->value) }}</span>
                        @if($bg->governing_rules)
                            <span class="text-[10px] text-faint">· {{ $bg->governing_rules }}</span>
                        @endif
                    </div>
                    <p class="text-[14px] font-semibold text-primary">
                        {{ $bg->applicant?->name ?? '—' }} <span class="text-muted font-normal">→</span> {{ $bg->beneficiary?->name ?? '—' }}
                    </p>
                    <p class="text-[11px] text-muted mt-1">{{ __('bg.issuing_bank') }}: {{ $bg->issuing_bank_name ?? '—' }}</p>
                </div>
                <div class="text-end">
                    <p class="text-[18px] font-bold text-primary">{{ $bg->currency }} {{ number_format((float) $bg->amount, 2) }}</p>
                    <p class="text-[11px] text-muted">
                        {{ __('bg.expires') }}: {{ $bg->expiry_date?->format('d M Y') }}
                        @if(in_array($bg->status?->value, ['live','issued','reduced'], true) && $daysToExpiry !== null && $daysToExpiry > -30 && $daysToExpiry <= 0)
                            <span class="text-[#ffb020] font-semibold"> · {{ abs($daysToExpiry) }}d</span>
                        @endif
                    </p>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
            <p class="text-[14px] text-muted">{{ __('bg.empty') }}</p>
        </div>
    @endforelse

    {{ $guarantees->links() }}
</div>

{{-- Register BG modal --}}
<div id="register-bg-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="bg-surface border border-th-border rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-5 border-b border-th-border flex items-center justify-between">
            <h3 class="text-[16px] font-bold text-primary">{{ __('bg.register_new') }}</h3>
            <button type="button" onclick="document.getElementById('register-bg-modal').classList.add('hidden')" class="text-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('dashboard.bank-guarantees.store') }}" class="p-5 grid grid-cols-2 gap-3">
            @csrf
            <div class="col-span-2">
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.type') }}</label>
                <select name="type" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
                    @foreach($types as $t)
                        <option value="{{ $t->value }}">{{ __('bg.type.' . $t->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.governing_rules') }}</label>
                <select name="governing_rules" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
                    <option value="URDG_758" selected>URDG 758 (ICC)</option>
                    <option value="URDG_458">URDG 458 (ICC)</option>
                    <option value="ISP_98">ISP 98</option>
                    <option value="local_uae">Local UAE</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.beneficiary') }}</label>
                <input type="number" name="beneficiary_company_id" required placeholder="Company ID"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.amount') }}</label>
                <input type="number" name="amount" required step="0.01" min="0"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('common.currency') }}</label>
                <input type="text" name="currency" value="AED" maxlength="3"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary uppercase">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.validity_start') }}</label>
                <input type="date" name="validity_start_date" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.expiry') }}</label>
                <input type="date" name="expiry_date" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div class="col-span-2">
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.issuing_bank') }}</label>
                <input type="text" name="issuing_bank_name" required maxlength="150"
                       placeholder="e.g. Mashreq Bank / Emirates NBD / FAB"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.swift') }}</label>
                <input type="text" name="issuing_bank_swift" maxlength="16"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary uppercase">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.bank_reference') }}</label>
                <input type="text" name="issuing_bank_reference" maxlength="100"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.contract_id') }}</label>
                <input type="number" name="contract_id" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-primary mb-1.5">{{ __('bg.claim_period_days') }}</label>
                <input type="number" name="claim_period_days" value="30" min="1" max="365"
                       class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px] text-primary">
            </div>
            <div class="col-span-2 flex items-center justify-end gap-2 pt-3 border-t border-th-border">
                <button type="button" onclick="document.getElementById('register-bg-modal').classList.add('hidden')"
                        class="px-4 py-2 rounded-lg text-[12px] font-medium text-primary bg-page border border-th-border">{{ __('common.cancel') }}</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('bg.register') }}</button>
            </div>
        </form>
    </div>
</div>

@if(session('status'))
<div class="fixed bottom-6 right-6 bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[#00d9b5] px-4 py-3 rounded-xl font-semibold text-[13px]">{{ session('status') }}</div>
@endif

@endsection
