@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', __('admin.categories.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.categories.title')" :subtitle="__('admin.categories.subtitle')" />

@include('dashboard.admin._tabs', ['active' => 'categories'])

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Create form --}}
    <div class="bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.categories.new') }}</h3>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.categories.name') }} *</label>
                <input type="text" name="name" required class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.categories.name_ar') }}</label>
                <input type="text" name="name_ar" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent" />
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.categories.parent') }}</label>
                <select name="parent_id" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                    <option value="">— {{ __('admin.categories.root') }} —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ str_repeat('— ', $cat->level) }}{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-semibold text-body mb-1.5">{{ __('admin.categories.description') }}</label>
                <textarea name="description" rows="3" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary focus:outline-none focus:border-accent"></textarea>
            </div>
            <label class="flex items-center gap-2 text-[12px] text-body">
                <input type="checkbox" name="is_active" value="1" checked /> {{ __('admin.categories.active') }}
            </label>
            <button type="submit" class="w-full bg-accent text-white px-4 py-2.5 rounded-lg text-[13px] font-semibold">{{ __('common.save') }}</button>
        </form>
    </div>

    {{-- Category tree --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-surface-2 text-faint text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="text-start px-4 py-3">{{ __('admin.categories.name') }}</th>
                        <th class="text-start px-4 py-3">{{ __('admin.categories.path') }}</th>
                        <th class="text-start px-4 py-3">{{ __('common.status') }}</th>
                        <th class="text-end px-4 py-3">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-th-border">
                    @forelse($categories as $cat)
                    <tr class="hover:bg-surface-2/50">
                        <td class="px-4 py-3">
                            <span style="padding-inline-start: {{ $cat->level * 16 }}px">
                                <span class="text-primary font-semibold">{{ $cat->name }}</span>
                                @if($cat->name_ar)<span class="text-[11px] text-muted ms-2">{{ $cat->name_ar }}</span>@endif
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[11px] text-muted font-mono">{{ $cat->path }}</td>
                        <td class="px-4 py-3"><x-dashboard.status-badge :status="$cat->is_active ? 'active' : 'closed'" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" onclick="document.getElementById('edit-cat-{{ $cat->id }}').classList.toggle('hidden')"
                                        class="p-1.5 rounded hover:bg-surface-2 text-muted hover:text-primary">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.categories.confirm_delete') }}');">@csrf @method('DELETE')
                                    <button class="p-1.5 rounded hover:bg-red-500/10 text-muted hover:text-red-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="edit-cat-{{ $cat->id }}" class="hidden bg-surface-2/30">
                        <td colspan="4" class="px-4 py-4">
                            <form method="POST" action="{{ route('admin.categories.update', $cat->id) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                @csrf @method('PATCH')
                                <input type="text" name="name" value="{{ $cat->name }}" required placeholder="{{ __('admin.categories.name') }}" class="bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
                                <input type="text" name="name_ar" value="{{ $cat->name_ar }}" placeholder="{{ __('admin.categories.name_ar') }}" class="bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary" />
                                <select name="parent_id" class="bg-surface border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                                    <option value="">— {{ __('admin.categories.root') }} —</option>
                                    @foreach($categories as $opt)
                                        @if($opt->id !== $cat->id)
                                            <option value="{{ $opt->id }}" @selected($cat->parent_id === $opt->id)>{{ $opt->path }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-[12px] text-body">
                                        <input type="checkbox" name="is_active" value="1" @checked($cat->is_active) />
                                        {{ __('admin.categories.active') }}
                                    </label>
                                    <button type="submit" class="ms-auto bg-accent text-white px-3 py-1.5 rounded-lg text-[12px] font-semibold">{{ __('common.save') }}</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-8">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
