@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.users.new'))

@section('content')

<x-dashboard.page-header :title="__('admin.users.new')" :back="route('admin.users.index')" />

@include('dashboard.admin._tabs', ['active' => 'users'])

<form method="POST" action="{{ route('admin.users.store') }}" class="bg-surface border border-th-border rounded-2xl p-6 max-w-3xl">
    @csrf
    @include('dashboard.admin.users._form', ['user' => null])
    <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="bg-accent text-white px-5 py-2.5 rounded-lg text-[13px] font-semibold">{{ __('common.save') }}</button>
        <a href="{{ route('admin.users.index') }}" class="text-[13px] text-muted hover:text-primary">{{ __('common.cancel') }}</a>
    </div>
</form>

@endsection
