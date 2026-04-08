@extends('layouts.dashboard', ['active' => 'notifications'])
@section('title', __('notifications.title'))

@php
$notifColors = [
    'blue'   => ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]'],
    'green'  => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]'],
    'orange' => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]'],
    'purple' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]'],
    'red'    => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]'],
];
@endphp

@section('content')

<x-dashboard.page-header :title="__('notifications.title')" :subtitle="__('notifications.subtitle')">
    @if($unreadCount > 0)
    <x-slot:actions>
        <form method="POST" action="{{ route('notifications.read-all') }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
                {{ __('notifications.mark_all_read') }}
            </button>
        </form>
    </x-slot:actions>
    @endif
</x-dashboard.page-header>

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

@if($unreadCount > 0)
<div class="mb-6 inline-flex items-center gap-2 text-[12px] text-muted">
    <span class="bg-[#ff4d7f] text-white text-[11px] font-bold px-2 py-0.5 rounded-full min-w-[22px] text-center">{{ $unreadCount }}</span>
    {{ __('notifications.unread_count', ['count' => $unreadCount]) }}
</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl overflow-hidden">
    <div class="divide-y divide-th-border">
        @forelse($items as $n)
        @php $c = $notifColors[$n['color']] ?? $notifColors['blue']; @endphp
        <div class="p-5 flex items-start gap-4 {{ $n['read'] ? '' : 'bg-accent/5' }} hover:bg-surface-2 transition-colors">
            <div class="w-10 h-10 rounded-xl {{ $c['bg'] }} flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 {{ $c['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $n['icon'] }}"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <p class="text-[14px] font-bold text-primary">
                        {{ $n['title'] }}
                        @if(!$n['read'])
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-accent ms-1 align-middle"></span>
                        @endif
                    </p>
                    <span class="text-[11px] text-faint flex-shrink-0">{{ $n['time'] }}</span>
                </div>
                <p class="text-[12px] text-muted">{{ $n['desc'] }}</p>
                <div class="flex items-center gap-3 mt-2">
                    @if($n['url'])
                    <form method="POST" action="{{ route('notifications.read', ['id' => $n['id']]) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-[12px] font-semibold text-accent hover:underline">
                            {{ __('common.view') }} →
                        </button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('notifications.destroy', ['id' => $n['id']]) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[12px] text-faint hover:text-[#ff4d7f]">{{ __('notifications.dismiss') }}</button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="p-10 sm:p-14 text-center">
            <div class="w-16 h-16 rounded-2xl bg-accent/10 border border-accent/20 mx-auto mb-4 flex items-center justify-center text-accent">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
            </div>
            <p class="text-[15px] font-bold text-primary">{{ __('notifications.empty') }}</p>
            <p class="text-[12.5px] text-muted mt-1 max-w-[400px] mx-auto">{{ __('notifications.empty_hint') ?? __('notifications.no_recent_activity') ?? __('common.try_again_later') }}</p>
        </div>
        @endforelse
    </div>

    @if($pagination->hasPages())
    <div class="p-4 border-t border-th-border">
        {{ $pagination->links() }}
    </div>
    @endif
</div>

@endsection
