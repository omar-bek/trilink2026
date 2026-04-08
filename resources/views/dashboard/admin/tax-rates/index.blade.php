@extends('layouts.dashboard', ['active' => 'admin-tax-rates'])
@section('title', __('admin.tax_rates.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.tax_rates.title')" :subtitle="__('admin.tax_rates.subtitle')">
    <x-slot:actions>
        <a href="{{ route('admin.tax-rates.create') }}"
           class="inline-flex items-center gap-2 h-12 px-5 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('admin.tax_rates.new') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

<x-admin.navbar active="tax-rates" />

<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.tax_rates.name') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.tax_rates.code') }}</th>
                    <th class="text-end px-5 py-4 font-bold">{{ __('admin.tax_rates.rate') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.tax_rates.scope') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.tax_rates.status') }}</th>
                    <th class="text-end px-5 py-4 font-bold">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($taxRates as $r)
                <tr class="hover:bg-surface-2/50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                            <p class="font-semibold text-primary">{{ $r->name }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-4 font-mono text-muted text-[11px]">{{ $r->code }}</td>
                    <td class="px-5 py-4 text-end">
                        <span class="inline-flex items-baseline gap-0.5 text-primary font-bold text-[16px]">
                            {{ rtrim(rtrim(number_format((float) $r->rate, 2), '0'), '.') }}<span class="text-[11px] text-muted font-semibold">%</span>
                        </span>
                    </td>
                    <td class="px-5 py-4 text-muted">
                        <p class="text-body">{{ $r->category?->name ?? __('admin.tax_rates.all_categories') }}</p>
                        @if($r->country)<p class="text-[11px] text-faint mt-0.5">{{ $r->country }}</p>@endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($r->is_default)
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-bold bg-[#00d9b5]/10 text-[#00d9b5] border border-[#00d9b5]/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#00d9b5]"></span>
                                    {{ __('admin.tax_rates.default') }}
                                </span>
                            @endif
                            @if($r->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-bold bg-[#4f7cff]/10 text-[#4f7cff] border border-[#4f7cff]/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#4f7cff]"></span>
                                    {{ __('admin.tax_rates.active') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-bold bg-surface-2 text-muted border border-th-border">
                                    <span class="w-1.5 h-1.5 rounded-full bg-muted"></span>
                                    {{ __('admin.tax_rates.inactive') }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('admin.tax-rates.edit', $r->id) }}" title="{{ __('common.edit') }}"
                               class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-accent/10 hover:text-accent transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.tax-rates.destroy', $r->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.tax_rates.confirm_delete') }}');">
                                @csrf @method('DELETE')
                                <button type="submit" title="{{ __('common.delete') }}"
                                        class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#ff4d7f]/10 hover:text-[#ff4d7f] transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center">
                        <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6M9 8h.01M15 14h.01M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="text-[13px] text-muted">{{ __('admin.tax_rates.empty') }}</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-th-border bg-surface-2/30">{{ $taxRates->links() }}</div>
</div>

@endsection
