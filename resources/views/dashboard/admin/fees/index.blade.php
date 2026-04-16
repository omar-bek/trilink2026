@extends('layouts.dashboard', ['active' => 'admin-fees'])
@section('title', __('admin.fees.title'))

@section('content')

<x-admin.navbar active="fees" />

<div class="mb-6">
    <h2 class="text-[20px] font-bold text-primary">{{ __('admin.fees.title') }}</h2>
    <p class="text-[13px] text-muted mt-1">{{ __('admin.fees.subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">{{ session('status') }}</div>
@endif

{{-- Add form --}}
<form method="POST" action="{{ route('admin.fees.store') }}" class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    @csrf
    <h3 class="text-[14px] font-bold text-primary mb-4">{{ __('admin.fees.add') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <input name="name" required placeholder="{{ __('admin.fees.name') }}" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
        <select name="type" required class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
            <option value="percentage">{{ __('admin.fees.percentage') }}</option>
            <option value="fixed">{{ __('admin.fees.fixed') }}</option>
        </select>
        <input name="value" type="number" step="0.01" required placeholder="{{ __('admin.fees.value') }}" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
        <select name="applies_to" required class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
            <option value="contract">Contract</option>
            <option value="payment">Payment</option>
            <option value="rfq">RFQ</option>
            <option value="escrow">Escrow</option>
        </select>
        <input name="min_amount" type="number" step="0.01" placeholder="Min AED" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
        <button type="submit" class="h-10 rounded-xl text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('admin.fees.add') }}</button>
    </div>
    <input name="description" placeholder="{{ __('admin.fees.description') }}" class="mt-3 w-full bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
</form>

{{-- Fee list --}}
<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <table class="w-full text-[13px]">
        <thead>
            <tr class="border-b border-th-border bg-surface-2">
                <th class="text-left py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.fees.name') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.fees.type') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.fees.value') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('admin.fees.applies') }}</th>
                <th class="text-center py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.status') }}</th>
                <th class="text-right py-3 px-4 text-[11px] font-bold text-muted uppercase tracking-wider">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fees as $f)
            <tr class="border-b border-th-border/50 hover:bg-surface-2">
                <td class="py-3 px-4 font-semibold text-primary">{{ $f->name }}</td>
                <td class="py-3 px-4 text-center text-muted capitalize">{{ $f->type }}</td>
                <td class="py-3 px-4 text-center text-primary font-semibold">{{ $f->type === 'percentage' ? $f->value . '%' : 'AED ' . number_format($f->value, 2) }}</td>
                <td class="py-3 px-4 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-accent/10 text-accent capitalize">{{ $f->applies_to }}</span></td>
                <td class="py-3 px-4 text-center"><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $f->is_active ? 'bg-[#00d9b5]/10 text-[#00d9b5]' : 'bg-page text-muted' }}">{{ $f->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="py-3 px-4 text-right">
                    <form method="POST" action="{{ route('admin.fees.destroy', $f->id) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[11px] text-[#ff4d7f] hover:underline">{{ __('common.delete') }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="py-12 text-center text-[14px] text-muted">{{ __('admin.fees.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $fees->links() }}</div>

@endsection
