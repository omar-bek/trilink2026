@extends('layouts.dashboard', ['active' => 'admin-verification'])
@section('title', __('verification.queue_title'))

@section('content')

<x-dashboard.page-header
    :title="__('verification.queue_title')"
    :subtitle="__('verification.queue_subtitle')" />

<x-admin.navbar active="verification" />

{{-- ─────────────────────── Stat tiles — clickable filters ─────────────────────── --}}
@php
    $tiles = [
        [
            'key'   => 'all',
            'label' => __('verification.tile_all'),
            'value' => $stats['total'],
            'color' => 'blue',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21V7l9-4 9 4v14M9 21V12h6v9M3 21h18"/>',
        ],
        [
            'key'   => 'sanctions',
            'label' => __('verification.tile_sanctions'),
            'value' => $stats['sanctions_hit'] + $stats['sanctions_review'],
            'color' => 'red',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>',
        ],
        [
            'key'   => 'documents',
            'label' => __('verification.tile_documents'),
            'value' => $stats['pending_documents'],
            'color' => 'orange',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
        ],
        [
            'key'   => 'promotion',
            'label' => __('verification.tile_promotion'),
            'value' => $stats['eligible_promotion'],
            'color' => 'green',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>',
        ],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 mb-6">
    @foreach($tiles as $tile)
        <x-dashboard.stat-card
            :value="$tile['value']"
            :label="$tile['label']"
            :color="$tile['color']"
            :icon="$tile['icon']"
            :href="route('admin.verification.index', $tile['key'] === 'all' ? [] : ['filter' => $tile['key']])"
            :active="$filter === $tile['key']" />
    @endforeach
</div>

{{-- ─────────────────────── Queue rows ─────────────────────── --}}
@if($rows->isEmpty())
<div class="bg-surface border border-th-border rounded-[16px] p-12 text-center">
    <div class="mx-auto w-16 h-16 rounded-full bg-[#00d9b5]/10 border border-[#00d9b5]/30 flex items-center justify-center mb-4">
        <svg class="w-7 h-7 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <p class="text-[15px] font-bold text-primary mb-1">{{ __('verification.queue_empty') }}</p>
    <p class="text-[13px] text-muted">{{ __('verification.queue_empty_subtitle') }}</p>
</div>
@else
<div class="space-y-3">
    @foreach($rows as $row)
    @php
        $company  = $row['company'];
        $current  = $row['current_level'];
        $eligible = $row['eligible_level'];
        $screening = $row['sanctions_screening'];

        $palette = ['#4f7cff', '#00d9b5', '#8B5CF6', '#ffb020', '#ff4d7f', '#14B8A6'];
        $brandColor = $palette[$company->id % count($palette)];

        // Pick a "row tone" based on the highest-priority reason — drives a
        // subtle left-edge accent so the admin can pattern-match priorities.
        $reasonPriority = ['sanctions_hit', 'sanctions_review', 'pending_documents', 'eligible_promotion'];
        $topReason = collect($reasonPriority)->first(fn ($r) => in_array($r, $row['reasons'], true));
        $edgeColor = match ($topReason) {
            'sanctions_hit'      => '#ff4d7f',
            'sanctions_review'   => '#ffb020',
            'pending_documents'  => '#4f7cff',
            'eligible_promotion' => '#00d9b5',
            default              => '#4f7cff',
        };
    @endphp
    <div class="bg-surface border border-th-border rounded-[16px] p-[25px] relative overflow-hidden hover:border-th-border transition-colors">
        <div class="absolute inset-y-0 start-0 w-1" style="background: {{ $edgeColor }};"></div>

        <div class="flex items-start justify-between gap-5 flex-wrap">
            {{-- Left: company identity + reasons --}}
            <div class="min-w-0 flex-1 flex items-start gap-4">
                <div class="w-12 h-12 rounded-[12px] font-bold flex items-center justify-center text-[14px] flex-shrink-0"
                     style="background: {{ $brandColor }}1a; color: {{ $brandColor }}; border: 1px solid {{ $brandColor }}33;">
                    {{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <a href="{{ route('admin.companies.show', $company->id) }}"
                           class="text-[15px] font-bold text-primary hover:text-accent transition-colors">
                            {{ $company->name }}
                        </a>
                        <x-dashboard.verification-badge :level="$current" />
                        @if($company->country)
                            <span class="inline-flex items-center gap-1 text-[11px] text-muted">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $company->country }}
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-1.5 mb-3">
                        @foreach($row['reasons'] as $reason)
                            @php
                                $reasonStyles = match ($reason) {
                                    'sanctions_hit'      => 'bg-[#ff4d7f]/10 text-[#ff4d7f] border-[#ff4d7f]/30',
                                    'sanctions_review'   => 'bg-[#ffb020]/10 text-[#ffb020] border-[#ffb020]/30',
                                    'pending_documents'  => 'bg-[#4f7cff]/10 text-[#4f7cff] border-[#4f7cff]/30',
                                    'eligible_promotion' => 'bg-[#00d9b5]/10 text-[#00d9b5] border-[#00d9b5]/30',
                                    default              => 'bg-surface-2 text-muted border-th-border',
                                };
                                $reasonDot = match ($reason) {
                                    'sanctions_hit'      => 'bg-[#ff4d7f]',
                                    'sanctions_review'   => 'bg-[#ffb020]',
                                    'pending_documents'  => 'bg-[#4f7cff]',
                                    'eligible_promotion' => 'bg-[#00d9b5]',
                                    default              => 'bg-muted',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $reasonStyles }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $reasonDot }}"></span>
                                {{ __('verification.reason_' . $reason) }}
                            </span>
                        @endforeach
                    </div>

                    <div class="text-[11px] text-muted space-y-1">
                        @if($row['pending_doc_count'] > 0)
                            <p class="flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ __('verification.pending_docs_count', ['count' => $row['pending_doc_count']]) }}
                            </p>
                        @endif
                        @if($screening && $screening->match_count > 0)
                            <p class="flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                {{ __('verification.matches', ['count' => $screening->match_count]) }} — {{ optional($screening->created_at)->diffForHumans() }}
                            </p>
                        @endif
                        @if($eligible->rank() > $current->rank())
                            <p class="flex items-center gap-1.5">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                {{ __('verification.eligible_for', ['tier' => $eligible->label()]) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right: action buttons --}}
            <div class="flex flex-col gap-2 flex-shrink-0 min-w-[200px]">
                @if(in_array('sanctions_hit', $row['reasons'], true) || in_array('sanctions_review', $row['reasons'], true))
                    <form method="POST" action="{{ route('admin.companies.rescreen', $company->id) }}">
                        @csrf
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[12px] font-bold text-white bg-[#ff4d7f] hover:brightness-110 shadow-[0_4px_14px_rgba(255,77,127,0.25)] transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 0115.6-6.2M21 12a9 9 0 01-15.6 6.2M21 3v6h-6M3 21v-6h6"/></svg>
                            {{ __('trust.sanctions_rescreen') }}
                        </button>
                    </form>
                @endif

                @if($row['pending_doc_count'] > 0)
                    <a href="{{ route('admin.companies.show', $company->id) }}#documents"
                       class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[12px] font-bold text-[#4f7cff] bg-[#4f7cff]/10 border border-[#4f7cff]/30 hover:bg-[#4f7cff]/20 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        {{ __('verification.review_documents') }}
                    </a>
                @endif

                @if($eligible->rank() > $current->rank())
                    <form method="POST" action="{{ route('admin.verification.auto-promote', $company->id) }}">
                        @csrf
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-[12px] text-[12px] font-bold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/30 hover:bg-[#00d9b5]/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            {{ __('verification.auto_promote_to', ['tier' => $eligible->label()]) }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
