@extends('layouts.dashboard', ['active' => 'admin-settings'])
@section('title', __('admin.settings.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.settings.title')" :subtitle="__('admin.settings.subtitle')" />

<x-admin.navbar active="settings" />

@php
    // Color & icon hint for each settings group, mapped by name. Falls back
    // to a neutral slate look for unknown groups.
    $groupMeta = [
        'general'   => ['color' => '#4f7cff', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        'mail'      => ['color' => '#00d9b5', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>'],
        'payments'  => ['color' => '#ffb020', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'],
        'security'  => ['color' => '#ff4d7f', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'],
        'features'  => ['color' => '#8B5CF6', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>'],
    ];
    $defaultMeta = ['color' => '#14B8A6', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'];
@endphp

{{-- Per-setting delete forms (kept outside the main update form) --}}
@foreach($settings as $rows)
    @foreach($rows as $s)
        <form method="POST" action="{{ route('admin.settings.destroy', $s->id) }}" id="delete-setting-{{ $s->id }}" class="hidden">@csrf @method('DELETE')</form>
    @endforeach
@endforeach

<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf

    @forelse($settings as $group => $rows)
    @php
        $meta = $groupMeta[$group] ?? $defaultMeta;
    @endphp
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] flex items-center justify-center flex-shrink-0"
                 style="background: {{ $meta['color'] }}1a; border: 1px solid {{ $meta['color'] }}33;">
                <svg class="w-[16px] h-[16px]" style="color: {{ $meta['color'] }};" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    {!! $meta['icon'] !!}
                </svg>
            </div>
            <div>
                <h3 class="text-[15px] font-bold text-primary leading-tight capitalize">{{ $group }}</h3>
                <p class="text-[11px] text-muted">{{ count($rows) }} {{ __('admin.settings.entries') }}</p>
            </div>
        </div>

        <div class="space-y-3">
            @foreach($rows as $i => $s)
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start bg-surface-2/40 border border-th-border rounded-[12px] p-3">
                <input type="hidden" name="settings[{{ $loop->parent->index }}_{{ $i }}][group]" value="{{ $s->group }}" />
                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('admin.settings.key') }}</label>
                    <input type="text" name="settings[{{ $loop->parent->index }}_{{ $i }}][key]" value="{{ $s->key }}" readonly
                           class="w-full bg-page border border-th-border rounded-[10px] px-3 h-10 text-[12px] font-mono text-muted focus:outline-none" />
                </div>
                <div class="md:col-span-7">
                    <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('admin.settings.value_label') }}</label>
                    <textarea name="settings[{{ $loop->parent->index }}_{{ $i }}][value]" rows="2"
                              class="w-full bg-surface border border-th-border rounded-[10px] px-3 py-2 text-[12px] font-mono text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 resize-y transition-colors">{{ is_array($s->value) || is_object($s->value) ? json_encode($s->value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : (string) $s->value }}</textarea>
                </div>
                <div class="md:col-span-1 flex md:items-end h-full">
                    <button type="button"
                            onclick="if(confirm('{{ __('admin.settings.confirm_delete') }}'))document.getElementById('delete-setting-{{ $s->id }}').submit();"
                            class="w-9 h-10 mx-auto rounded-[10px] flex items-center justify-center text-muted hover:bg-[#ff4d7f]/10 hover:text-[#ff4d7f] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-[16px] p-12 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
        </div>
        <p class="text-[13px] text-muted">{{ __('admin.settings.empty') }}</p>
    </div>
    @endforelse

    {{-- ─────────────────────── New setting ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-accent/10 border border-accent/20 flex items-center justify-center">
                <svg class="w-[16px] h-[16px] text-accent" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <h3 class="text-[15px] font-bold text-primary leading-tight">{{ __('admin.settings.new') }}</h3>
                <p class="text-[11px] text-muted">{{ __('admin.settings.new_help') }}</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <input type="text" name="settings[new][key]" placeholder="{{ __('admin.settings.key') }}"
                   class="md:col-span-3 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] font-mono text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
            <input type="text" name="settings[new][group]" placeholder="{{ __('admin.settings.group') }}" value="general"
                   class="md:col-span-2 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
            <input type="text" name="settings[new][value]" placeholder="{{ __('admin.settings.value_placeholder') }}"
                   class="md:col-span-7 bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors" />
        </div>
    </div>

    {{-- ─────────────────────── Sticky save bar ─────────────────────── --}}
    <div class="sticky bottom-4 z-10 bg-surface border border-th-border rounded-[16px] p-[17px] flex items-center justify-between gap-3 shadow-[0_8px_24px_rgba(0,0,0,0.25)]">
        <p class="text-[12px] text-muted">{{ __('admin.settings.save_hint') }}</p>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 h-12 px-6 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ __('admin.settings.save_all') }}
        </button>
    </div>
</form>

@endsection
