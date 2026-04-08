@extends('layouts.dashboard', ['active' => 'suppliers'])
@section('title', __('suppliers.add'))

@section('content')

<x-dashboard.page-header :title="__('suppliers.add')" :subtitle="__('suppliers.add_subtitle')" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="GET" action="{{ route('dashboard.suppliers.create') }}" class="mb-4">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('suppliers.search_placeholder') }}"
           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
</form>

<form method="POST" action="{{ route('dashboard.suppliers.store') }}" class="space-y-5">
    @csrf

    <div class="bg-surface border border-th-border rounded-2xl p-6 space-y-4">
        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('suppliers.choose_supplier') }}</label>
            <select name="supplier_company_id" required
                    class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">
                <option value="">— {{ __('common.select') }} —</option>
                @forelse($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} @if($s->country) ({{ $s->country }}) @endif</option>
                @empty
                    <option value="" disabled>{{ __('suppliers.no_candidates') }}</option>
                @endforelse
            </select>
        </div>

        <div>
            <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('suppliers.notes') }}</label>
            <textarea name="notes" rows="3"
                      class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('dashboard.suppliers.index') }}"
           class="h-10 px-4 rounded-xl bg-page border border-th-border text-[13px] font-semibold text-primary hover:bg-surface-2 transition-colors inline-flex items-center">
            {{ __('common.cancel') }}
        </a>
        <button type="submit"
                class="h-10 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent/90 transition-colors">
            {{ __('common.save') }}
        </button>
    </div>
</form>

@endsection
