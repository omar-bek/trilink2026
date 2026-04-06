@props(['active' => 'overview'])

@php
$tabs = [
    ['key' => 'overview',   'label' => __('admin.tabs.overview'),   'route' => 'admin.index'],
    ['key' => 'users',      'label' => __('admin.tabs.users'),      'route' => 'admin.users.index'],
    ['key' => 'companies',  'label' => __('admin.tabs.companies'),  'route' => 'admin.companies.index'],
    ['key' => 'categories', 'label' => __('admin.tabs.categories'), 'route' => 'admin.categories.index'],
    ['key' => 'settings',   'label' => __('admin.tabs.settings'),   'route' => 'admin.settings.index'],
    ['key' => 'audit',      'label' => __('admin.tabs.audit'),      'route' => 'admin.audit.index'],
];
@endphp

<div class="mb-6 border-b border-th-border">
    <div class="flex flex-wrap gap-1">
        @foreach($tabs as $t)
        <a href="{{ route($t['route']) }}"
           class="px-4 py-2.5 text-[13px] font-semibold transition-colors border-b-2 -mb-px {{ $active === $t['key'] ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-primary' }}">
            {{ $t['label'] }}
        </a>
        @endforeach
    </div>
</div>

@if(session('status'))
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 px-4 py-3 text-[13px]">
    {{ session('status') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 px-4 py-3 text-[13px]">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif
