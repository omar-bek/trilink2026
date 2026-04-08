@extends('layouts.dashboard', ['active' => 'branches'])
@section('title', __('branches.title'))

@section('content')

<x-dashboard.page-header :title="__('branches.title')" :subtitle="__('branches.subtitle')" />

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

<div class="mb-4 flex justify-end">
    <a href="{{ route('dashboard.branches.create') }}"
       class="group inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        {{ __('branches.new') }}
    </a>
</div>

<div class="bg-surface border border-th-border rounded-2xl overflow-x-auto">
    <table class="w-full min-w-[760px]">
        <thead class="bg-surface-2 border-b border-th-border">
            <tr class="text-[11px] text-muted uppercase tracking-wider">
                <th class="text-start px-4 py-3">{{ __('branches.name') }}</th>
                <th class="text-start px-4 py-3">{{ __('branches.category') }}</th>
                <th class="text-start px-4 py-3">{{ __('branches.location') }}</th>
                <th class="text-start px-4 py-3">{{ __('branches.manager') }}</th>
                <th class="text-start px-4 py-3">{{ __('branches.status') }}</th>
                <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody class="text-[13px]">
            @forelse($branches as $b)
            <tr class="border-b border-th-border hover:bg-surface-2/50">
                <td class="px-4 py-3 font-semibold text-primary">
                    {{ $b->name }}
                    @if($b->name_ar)
                        <div class="text-[11px] text-muted">{{ $b->name_ar }}</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-muted">{{ $b->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-muted">{{ trim(implode(', ', array_filter([$b->city, $b->country]))) ?: '—' }}</td>
                <td class="px-4 py-3 text-muted">
                    @if($b->manager)
                        {{ trim(($b->manager->first_name ?? '') . ' ' . ($b->manager->last_name ?? '')) }}
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($b->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{{ __('branches.active') }}</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-zinc-500/10 text-zinc-400 border border-zinc-500/20">{{ __('branches.inactive') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-end">
                    <a href="{{ route('dashboard.branches.edit', $b->id) }}" class="text-accent hover:underline text-[12px] font-semibold">{{ __('common.edit') }}</a>
                    <form method="POST" action="{{ route('dashboard.branches.destroy', $b->id) }}" class="inline" onsubmit="return confirm('{{ __('branches.confirm_delete') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[#ff4d7f] hover:underline text-[12px] font-semibold ms-3">{{ __('common.delete') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-12 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center mb-3 text-accent">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    </div>
                    <p class="text-[14px] font-bold text-primary">{{ __('branches.empty') }}</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $branches->links() }}</div>

@endsection
