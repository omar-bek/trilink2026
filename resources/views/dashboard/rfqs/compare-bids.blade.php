@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', __('bids.compare_title'))

@section('content')

<div class="mb-6">
    <a href="{{ route('dashboard.rfqs.show', ['id' => $rfqId]) }}" class="inline-flex items-center gap-2 text-[13px] font-medium text-muted hover:text-primary mb-3">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
        {{ __('common.back') }}
    </a>
    <h1 class="text-[28px] sm:text-[36px] font-bold text-primary">{{ __('bids.compare_title') }}</h1>
    <p class="text-[14px] text-muted mt-1">{{ __('bids.compare_subtitle') }}</p>
</div>

<livewire:bid-comparison :rfq-id="(int) $rfqId" />

@endsection
