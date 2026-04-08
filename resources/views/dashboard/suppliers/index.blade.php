@extends('layouts.dashboard', ['active' => 'suppliers'])
@section('title', __('suppliers.title'))

@section('content')

<x-dashboard.page-header :title="__('suppliers.title')" :subtitle="__('suppliers.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

<div class="mb-4 flex justify-end">
    <a href="{{ route('dashboard.suppliers.create') }}"
       class="group inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        {{ __('suppliers.add') }}
    </a>
</div>

<div class="bg-surface border border-th-border rounded-2xl overflow-x-auto">
    <table class="w-full min-w-[680px]">
        <thead class="bg-surface-2 border-b border-th-border">
            <tr class="text-[11px] text-muted uppercase tracking-wider">
                <th class="text-start px-4 py-3">{{ __('suppliers.supplier') }}</th>
                <th class="text-start px-4 py-3">{{ __('suppliers.country') }}</th>
                <th class="text-start px-4 py-3">{{ __('suppliers.added_by') }}</th>
                <th class="text-start px-4 py-3">{{ __('suppliers.added_at') }}</th>
                <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody class="text-[13px]">
            @forelse($links as $l)
            <tr class="border-b border-th-border hover:bg-surface-2/50">
                <td class="px-4 py-3 font-semibold text-primary">{{ $l->supplierCompany?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-muted">{{ $l->supplierCompany?->country ?? '—' }}</td>
                <td class="px-4 py-3 text-muted">{{ trim(($l->addedBy?->first_name ?? '') . ' ' . ($l->addedBy?->last_name ?? '')) ?: '—' }}</td>
                <td class="px-4 py-3 text-muted">{{ optional($l->created_at)->format('M j, Y') }}</td>
                <td class="px-4 py-3 text-end">
                    <form method="POST" action="{{ route('dashboard.suppliers.destroy', $l->id) }}" class="inline" onsubmit="return confirm('{{ __('suppliers.confirm_remove') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold">{{ __('suppliers.remove') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-12 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497"/></svg>
                    </div>
                    <p class="text-[14px] font-bold text-primary">{{ __('suppliers.empty') }}</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $links->links() }}</div>

@endsection
