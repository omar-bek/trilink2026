@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.cost_centers_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.cost_centers_title')" :subtitle="__('settings.cost_centers_subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <h3 class="text-[16px] font-bold text-primary mb-4">{{ __('settings.existing_cost_centers') }}</h3>
    @if($centers->isEmpty())
        <p class="text-[13px] text-muted mb-5">{{ __('settings.no_cost_centers') }}</p>
    @else
    <table class="w-full text-[13px] mb-6">
        <thead class="text-muted border-b border-th-border">
            <tr>
                <th class="text-start py-2">{{ __('settings.code') }}</th>
                <th class="text-start py-2">{{ __('settings.name') }}</th>
                <th class="text-start py-2">{{ __('settings.parent') }}</th>
                <th class="text-start py-2">{{ __('settings.owner') }}</th>
                <th class="text-end py-2">{{ __('settings.annual_budget') }}</th>
                <th class="text-end py-2">{{ __('settings.committed') }}</th>
                <th class="text-end py-2">{{ __('settings.remaining') }}</th>
                <th class="text-end py-2"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($centers as $c)
            <tr class="border-b border-th-border/50">
                <td class="py-3 font-mono text-primary">{{ $c->code }}</td>
                <td class="py-3 text-primary">{{ $c->name }}</td>
                <td class="py-3 text-muted">{{ $c->parent?->code }}</td>
                <td class="py-3 text-muted">{{ $c->owner?->full_name }}</td>
                <td class="py-3 text-end text-primary">{{ $c->annual_budget_aed ? number_format($c->annual_budget_aed, 2) : '—' }}</td>
                <td class="py-3 text-end text-muted">{{ number_format($c->committed_aed, 2) }}</td>
                <td class="py-3 text-end font-semibold {{ ($c->remainingBudget() ?? 0) < 0 ? 'text-[#ff4d7f]' : 'text-[#00d9b5]' }}">
                    {{ $c->remainingBudget() !== null ? number_format($c->remainingBudget(), 2) : '—' }}
                </td>
                <td class="py-3 text-end">
                    <form method="POST" action="{{ route('settings.cost-centers.destroy', $c->id) }}" class="inline"
                          onsubmit="return confirm('{{ __('settings.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button class="text-[#ff4d7f] text-[12px] hover:underline">{{ __('settings.delete') }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <h4 class="text-[14px] font-bold text-primary mb-3">{{ __('settings.add_cost_center') }}</h4>
    <form method="POST" action="{{ route('settings.cost-centers.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @csrf
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.code') }}</label>
            <input type="text" name="code" maxlength="32" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
        </div>
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.name') }}</label>
            <input type="text" name="name" required class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
        </div>
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.name_ar') }}</label>
            <input type="text" name="name_ar" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
        </div>
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.parent') }}</label>
            <select name="parent_id" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
                <option value="">—</option>
                @foreach($centers as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.owner') }}</label>
            <select name="owner_user_id" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
                <option value="">—</option>
                @foreach($owners as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach
            </select>
        </div>
        <div><label class="block text-[12px] text-muted mb-1">{{ __('settings.fiscal_year') }}</label>
            <input type="number" name="fiscal_year" min="2020" max="2100" value="{{ date('Y') }}" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
        </div>
        <div class="md:col-span-2"><label class="block text-[12px] text-muted mb-1">{{ __('settings.annual_budget_aed') }}</label>
            <input type="number" step="0.01" min="0" name="annual_budget_aed" class="w-full bg-page border border-th-border rounded-xl px-3 py-2 text-[13px]">
        </div>
        <div class="md:col-span-3">
            <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                {{ __('settings.add_cost_center') }}
            </button>
        </div>
    </form>
</div>
@endsection
