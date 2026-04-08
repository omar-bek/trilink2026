@extends('layouts.dashboard', ['active' => 'products'])
@section('title', __('catalog.edit_product'))

@section('content')

<x-dashboard.page-header :title="__('catalog.edit_product')" :subtitle="$product->name" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('dashboard.products.update', $product->id) }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @method('PATCH')
    @include('dashboard.products._form', ['product' => $product, 'categories' => $categories])

    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-th-border">
        <a href="{{ route('dashboard.products.index') }}"
           class="h-11 px-5 rounded-xl bg-page border border-th-border text-[13px] font-semibold text-primary hover:bg-surface-2 transition-colors inline-flex items-center justify-center">
            {{ __('common.cancel') }}
        </a>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-accent text-white text-[13px] font-semibold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            {{ __('common.save') }}
        </button>
    </div>
</form>

@endsection
