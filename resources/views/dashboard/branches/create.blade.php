@extends('layouts.dashboard', ['active' => 'branches'])
@section('title', __('branches.new'))

@section('content')

<x-dashboard.page-header :title="__('branches.new')" :subtitle="__('branches.subtitle')" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('dashboard.branches.store') }}" class="space-y-5">
    @csrf
    @include('dashboard.branches._form', ['branch' => null, 'categories' => $categories, 'candidates' => $candidates])

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('dashboard.branches.index') }}"
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
