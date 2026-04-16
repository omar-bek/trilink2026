@extends('layouts.dashboard', ['active' => 'admin-blacklist'])
@section('title', __('admin.blacklist.title'))

@section('content')

<x-admin.navbar active="blacklist" />

<div class="mb-6">
    <h2 class="text-[20px] font-bold text-primary">{{ __('admin.blacklist.title') }}</h2>
    <p class="text-[13px] text-muted mt-1">{{ __('admin.blacklist.subtitle') }}</p>
</div>

@if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/30 text-[13px] text-[#00d9b5] font-semibold">{{ session('status') }}</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ff4d7f]">{{ $stats['active'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.blacklist.active') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-primary">{{ $stats['total'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.blacklist.total') }}</p>
    </div>
    <div class="bg-surface border border-th-border rounded-2xl p-5">
        <p class="text-[24px] font-bold text-[#ffb020]">{{ $stats['expired'] }}</p>
        <p class="text-[12px] text-muted mt-1">{{ __('admin.blacklist.expired') }}</p>
    </div>
</div>

{{-- Add form --}}
<form method="POST" action="{{ route('admin.blacklist.store') }}" class="bg-surface border border-th-border rounded-2xl p-5 mb-6">
    @csrf
    <h3 class="text-[14px] font-bold text-primary mb-4">{{ __('admin.blacklist.add') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        <select name="company_id" required class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary">
            <option value="">{{ __('admin.blacklist.select_company') }}</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        <input name="reason" required placeholder="{{ __('admin.blacklist.reason') }}" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary placeholder:text-muted" />
        <input name="expires_at" type="date" placeholder="{{ __('admin.blacklist.expires') }}" class="bg-page border border-th-border rounded-xl px-4 h-10 text-[13px] text-primary" />
        <button type="submit" class="h-10 rounded-xl text-[12px] font-semibold text-white bg-[#ff4d7f] hover:bg-[#ff4d7f]/80">{{ __('admin.blacklist.add') }}</button>
    </div>
    <textarea name="notes" rows="2" placeholder="{{ __('admin.blacklist.notes') }}" class="mt-3 w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary placeholder:text-muted"></textarea>
</form>

{{-- List --}}
<div class="space-y-3">
    @forelse($entries as $e)
    <div class="bg-surface border border-th-border rounded-2xl p-5 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <p class="text-[14px] font-semibold text-primary">{{ $e->company_name }}</p>
                <span class="inline-flex items-center h-[20px] px-2 rounded-full text-[10px] font-bold {{ $e->is_active ? 'bg-[#ff4d7f]/10 text-[#ff4d7f]' : 'bg-page text-muted' }}">{{ $e->is_active ? __('admin.blacklist.active') : __('admin.blacklist.inactive') }}</span>
            </div>
            <p class="text-[12px] text-muted"><strong>{{ __('admin.blacklist.reason') }}:</strong> {{ $e->reason }}</p>
            @if($e->notes)
                <p class="text-[12px] text-muted mt-1">{{ $e->notes }}</p>
            @endif
            <p class="text-[11px] text-muted mt-1">By {{ $e->admin_first_name }} {{ $e->admin_last_name }} &middot; {{ \Carbon\Carbon::parse($e->created_at)->format('d M Y') }}{{ $e->expires_at ? ' &middot; Expires: ' . \Carbon\Carbon::parse($e->expires_at)->format('d M Y') : '' }}</p>
        </div>
        @if($e->is_active)
        <form method="POST" action="{{ route('admin.blacklist.remove', $e->id) }}">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 h-8 rounded-lg text-[11px] font-semibold text-[#00d9b5] border border-[#00d9b5]/30 hover:bg-[#00d9b5]/5">{{ __('admin.blacklist.remove') }}</button>
        </form>
        @endif
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-2xl p-12 text-center">
        <p class="text-[14px] text-muted">{{ __('admin.blacklist.empty') }}</p>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $entries->links() }}</div>

@endsection
