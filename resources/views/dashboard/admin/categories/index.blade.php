@extends('layouts.dashboard', ['active' => 'admin-categories'])
@section('title', __('admin.categories.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.categories.title')" :subtitle="__('admin.categories.subtitle')" />

<x-admin.navbar active="categories" />

@php
$inputCls = 'w-full bg-surface-2 border border-th-border rounded-[12px] px-4 h-11 text-[13px] text-primary placeholder-faint focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-colors';
$labelCls = 'block text-[11px] font-bold uppercase tracking-wider text-faint mb-2';
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ─────────────────────── Create form ─────────────────────── --}}
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px] lg:sticky lg:top-4 lg:self-start">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center">
                <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <h3 class="text-[15px] font-bold text-primary leading-tight">{{ __('admin.categories.new') }}</h3>
                <p class="text-[11px] text-muted">{{ __('admin.categories.subtitle') }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.categories.name') }} <span class="text-[#ff4d7f] normal-case">*</span></label>
                <input type="text" name="name" required class="{{ $inputCls }}" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.categories.name_ar') }}</label>
                <input type="text" name="name_ar" class="{{ $inputCls }}" dir="rtl" />
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.categories.parent') }}</label>
                <select name="parent_id" class="{{ $inputCls }}">
                    <option value="">— {{ __('admin.categories.root') }} —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ str_repeat('— ', $cat->level) }}{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $labelCls }}">{{ __('admin.categories.description') }}</label>
                <textarea name="description" rows="3" class="{{ str_replace('h-11', 'min-h-[80px] py-3', $inputCls) }} resize-none"></textarea>
            </div>
            <label class="flex items-center gap-2 text-[12px] text-body bg-surface-2 border border-th-border rounded-[10px] px-3 py-2.5 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded border-th-border bg-surface text-accent focus:ring-accent" />
                {{ __('admin.categories.active') }}
            </label>
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 h-12 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ __('common.save') }}
            </button>
        </form>
    </div>

    {{-- ─────────────────────── Tree table ─────────────────────── --}}
    <div class="lg:col-span-2 bg-surface border border-th-border rounded-[16px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-surface-2">
                    <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.categories.name') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('admin.categories.path') }}</th>
                        <th class="text-start px-5 py-4 font-bold">{{ __('common.status') }}</th>
                        <th class="text-end px-5 py-4 font-bold">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-th-border">
                    @forelse($categories as $cat)
                    <tr class="hover:bg-surface-2/50 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center" style="padding-inline-start: {{ $cat->level * 20 }}px">
                                @if($cat->level > 0)
                                <svg class="w-3.5 h-3.5 text-faint me-2 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                @endif
                                <div class="w-7 h-7 rounded-[8px] bg-accent/10 border border-accent/20 flex items-center justify-center me-2.5 flex-shrink-0">
                                    <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-primary font-semibold truncate">{{ $cat->name }}</p>
                                    @if($cat->name_ar)<p class="text-[11px] text-muted truncate" dir="rtl">{{ $cat->name_ar }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-[11px] text-muted font-mono">{{ $cat->path }}</td>
                        <td class="px-5 py-3"><x-dashboard.status-badge :status="$cat->is_active ? 'active' : 'closed'" /></td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" onclick="document.getElementById('edit-cat-{{ $cat->id }}').classList.toggle('hidden')"
                                        class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-accent/10 hover:text-accent transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" class="inline" onsubmit="return confirm('{{ __('admin.categories.confirm_delete') }}');">@csrf @method('DELETE')
                                    <button type="submit" class="w-9 h-9 rounded-[10px] flex items-center justify-center text-muted hover:bg-[#ff4d7f]/10 hover:text-[#ff4d7f] transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="edit-cat-{{ $cat->id }}" class="hidden bg-surface-2/40">
                        <td colspan="4" class="px-5 py-4">
                            <form method="POST" action="{{ route('admin.categories.update', $cat->id) }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                @csrf @method('PATCH')
                                <input type="text" name="name" value="{{ $cat->name }}" required placeholder="{{ __('admin.categories.name') }}" class="md:col-span-3 bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent" />
                                <input type="text" name="name_ar" value="{{ $cat->name_ar }}" placeholder="{{ __('admin.categories.name_ar') }}" class="md:col-span-3 bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent" dir="rtl" />
                                <select name="parent_id" class="md:col-span-3 bg-surface border border-th-border rounded-[10px] px-3 h-10 text-[13px] text-primary focus:outline-none focus:border-accent">
                                    <option value="">— {{ __('admin.categories.root') }} —</option>
                                    @foreach($categories as $opt)
                                        @if($opt->id !== $cat->id)
                                            <option value="{{ $opt->id }}" @selected($cat->parent_id === $opt->id)>{{ $opt->path }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <label class="md:col-span-2 flex items-center gap-2 text-[12px] text-body">
                                    <input type="checkbox" name="is_active" value="1" @checked($cat->is_active) class="w-4 h-4 rounded border-th-border bg-surface text-accent focus:ring-accent" />
                                    {{ __('admin.categories.active') }}
                                </label>
                                <button type="submit" class="md:col-span-1 inline-flex items-center justify-center bg-accent text-white rounded-[10px] h-10 text-[12px] font-bold hover:bg-accent-h transition-colors">
                                    {{ __('common.save') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center">
                            <div class="mx-auto w-14 h-14 rounded-full bg-surface-2 border border-th-border flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            </div>
                            <p class="text-[13px] text-muted">{{ __('common.no_data') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
