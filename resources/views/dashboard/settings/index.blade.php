@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.title'))

@section('content')

<x-dashboard.page-header :title="__('settings.title')" :subtitle="__('settings.subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#10B981]/5 border border-[#10B981]/30 rounded-xl p-4 text-[13px] text-[#10B981]">{{ session('status') }}</div>
@endif

@if($errors->any())
<div class="mb-6 bg-[#EF4444]/5 border border-[#EF4444]/30 rounded-xl p-4 text-[13px] text-[#EF4444]">
    <ul class="list-disc ms-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

    {{-- Tabs --}}
    @php
    $tabs = [
        ['key' => 'company',       'label' => __('settings.company_profile'),
         'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
        ['key' => 'personal',      'label' => __('settings.personal_info'),
         'icon' => 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
        ['key' => 'notifications', 'label' => __('settings.notifications'),
         'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
        ['key' => 'security',      'label' => __('settings.security'),
         'icon' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
        ['key' => 'payment',       'label' => __('settings.payment_methods'),
         'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
    ];
    @endphp
    <nav class="bg-surface border border-th-border rounded-2xl p-3 h-fit space-y-1">
        @foreach($tabs as $t)
        <a href="{{ route('settings.index', ['tab' => $t['key']]) }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-[13px] font-semibold transition-colors {{ $tab === $t['key'] ? 'bg-accent text-white shadow-[0_4px_14px_rgba(37,99,235,0.25)]' : 'text-body hover:bg-surface-2 hover:text-primary' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
            <span>{{ $t['label'] }}</span>
        </a>
        @endforeach
    </nav>

    {{-- Tab content --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6 sm:p-8">
        @include('dashboard.settings.tabs.' . $tab)
    </div>
</div>

@endsection
