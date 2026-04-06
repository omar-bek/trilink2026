@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.settings.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.settings.title')" :subtitle="__('admin.settings.subtitle')" />

@include('dashboard.admin._tabs', ['active' => 'settings'])

{{-- Per-setting delete forms (kept outside the main update form so they can submit independently) --}}
@foreach($settings as $rows)
    @foreach($rows as $s)
        <form method="POST" action="{{ route('admin.settings.destroy', $s->id) }}" id="delete-setting-{{ $s->id }}" class="hidden">@csrf @method('DELETE')</form>
    @endforeach
@endforeach

<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf

    @forelse($settings as $group => $rows)
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-4 capitalize">{{ $group }}</h3>
        <div class="space-y-3">
            @foreach($rows as $i => $s)
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                <input type="hidden" name="settings[{{ $loop->parent->index }}_{{ $i }}][group]" value="{{ $s->group }}" />
                <div class="md:col-span-4">
                    <input type="text" name="settings[{{ $loop->parent->index }}_{{ $i }}][key]" value="{{ $s->key }}" readonly
                           class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] font-mono text-muted" />
                </div>
                <div class="md:col-span-7">
                    <textarea name="settings[{{ $loop->parent->index }}_{{ $i }}][value]" rows="2"
                              class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent">{{ is_array($s->value) || is_object($s->value) ? json_encode($s->value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : (string) $s->value }}</textarea>
                </div>
                <div class="md:col-span-1">
                    <button type="button"
                            onclick="if(confirm('{{ __('admin.settings.confirm_delete') }}'))document.getElementById('delete-setting-{{ $s->id }}').submit();"
                            class="w-full p-2 rounded hover:bg-red-500/10 text-muted hover:text-red-400">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div class="bg-surface border border-th-border rounded-2xl p-6 text-center text-muted text-[13px]">
        {{ __('admin.settings.empty') }}
    </div>
    @endforelse

    {{-- New setting --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.settings.new') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <input type="text" name="settings[new][key]" placeholder="{{ __('admin.settings.key') }}" class="md:col-span-3 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
            <input type="text" name="settings[new][group]" placeholder="{{ __('admin.settings.group') }}" value="general" class="md:col-span-2 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
            <input type="text" name="settings[new][value]" placeholder="{{ __('admin.settings.value_placeholder') }}" class="md:col-span-7 bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="bg-accent text-white px-6 py-3 rounded-lg text-[13px] font-semibold">{{ __('admin.settings.save_all') }}</button>
    </div>
</form>

@endsection
