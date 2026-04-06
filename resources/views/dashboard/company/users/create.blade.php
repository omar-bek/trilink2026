@extends('layouts.dashboard', ['active' => 'company-users'])
@section('title', __('company.users.invite'))

@section('content')

<x-dashboard.page-header :title="__('company.users.invite')" :back="route('company.users')" />

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('company.users.store') }}" id="user-form" class="space-y-6">
    @csrf
    @include('dashboard.company.users._form', [
        'user' => null,
        'assignableRoles' => $assignableRoles,
        'permissionCatalog' => $permissionCatalog,
        'roleDefaults' => $roleDefaults,
    ])
    <div class="flex items-center gap-3">
        <button type="submit" class="bg-accent text-white px-6 py-3 rounded-lg text-[13px] font-semibold">{{ __('common.save') }}</button>
        <a href="{{ route('company.users') }}" class="text-[13px] text-muted hover:text-primary">{{ __('common.cancel') }}</a>
    </div>
</form>

@endsection
