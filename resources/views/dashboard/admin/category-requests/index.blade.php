@extends('layouts.dashboard', ['active' => 'admin-category-requests'])
@section('title', __('admin.category_requests.title'))

@section('content')

<x-dashboard.page-header :title="__('admin.category_requests.title')" :subtitle="__('admin.category_requests.subtitle')" />

<x-admin.navbar active="category-requests" />

@php
    $statusTabs = [
        'pending'  => ['label' => __('admin.category_requests.tab_pending'),  'color' => '#ffb020'],
        'approved' => ['label' => __('admin.category_requests.tab_approved'), 'color' => '#00d9b5'],
        'rejected' => ['label' => __('admin.category_requests.tab_rejected'), 'color' => '#ff4d7f'],
    ];
@endphp

<div class="mb-5 flex flex-wrap items-center gap-2">
    @foreach($statusTabs as $key => $tab)
        @php $isActive = $status === $key; @endphp
        <a href="{{ route('admin.category-requests.index', ['status' => $key]) }}"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-[10px] text-[12px] font-bold border transition-colors
                  {{ $isActive
                        ? 'bg-accent text-white border-accent shadow-[0_4px_14px_rgba(79,124,255,0.25)]'
                        : 'bg-surface border-th-border text-muted hover:text-primary hover:bg-surface-2' }}">
            <span class="w-2 h-2 rounded-full" style="background: {{ $tab['color'] }}"></span>
            {{ $tab['label'] }}
            <span class="min-w-[20px] h-[18px] px-1.5 inline-flex items-center justify-center rounded-full text-[10px] font-bold
                         {{ $isActive ? 'bg-white/25 text-white' : 'bg-surface-2 text-muted border border-th-border' }}">
                {{ $counts[$key] ?? 0 }}
            </span>
        </a>
    @endforeach
</div>

<div class="bg-surface border border-th-border rounded-[16px] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-surface-2">
                <tr class="text-faint text-[10px] uppercase tracking-[0.08em]">
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.category_requests.col_company') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.category_requests.col_category') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.category_requests.col_requester') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.category_requests.col_note') }}</th>
                    <th class="text-start px-5 py-4 font-bold">{{ __('admin.category_requests.col_submitted') }}</th>
                    <th class="text-end px-5 py-4 font-bold">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-th-border">
                @forelse($requests as $req)
                    @php
                        $catLabel = $req->category ? (app()->getLocale() === 'ar' && $req->category->name_ar ? $req->category->name_ar : $req->category->name) : '—';
                        $companyLabel = $req->company ? (app()->getLocale() === 'ar' && $req->company->name_ar ? $req->company->name_ar : $req->company->name) : '—';
                        $requesterName = $req->requestedBy ? trim(($req->requestedBy->first_name ?? '').' '.($req->requestedBy->last_name ?? '')) : '—';
                    @endphp
                    <tr class="hover:bg-surface-2/50 transition-colors align-top">
                        <td class="px-5 py-4">
                            @if($req->company)
                                <a href="{{ route('admin.companies.show', $req->company_id) }}" class="font-semibold text-primary hover:text-accent">{{ $companyLabel }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-primary font-semibold">{{ $catLabel }}</td>
                        <td class="px-5 py-4 text-body">
                            <div>{{ $requesterName ?: '—' }}</div>
                            @if($req->requestedBy?->email)
                                <div class="text-[11px] text-muted font-mono">{{ $req->requestedBy->email }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-body max-w-[280px]">
                            @if($req->note)
                                <div class="whitespace-pre-line">{{ $req->note }}</div>
                            @else
                                <span class="text-faint">—</span>
                            @endif
                            @if($req->status === \App\Models\CompanyCategoryRequest::STATUS_REJECTED && $req->rejection_reason)
                                <div class="mt-2 text-[11px] text-[#ff4d7f] italic">{{ __('admin.category_requests.rejected_reason') }}: {{ $req->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-muted text-[12px]">
                            <div>{{ $req->created_at?->diffForHumans() }}</div>
                            @if($req->reviewed_at)
                                <div class="text-[11px] text-faint mt-1">{{ __('admin.category_requests.reviewed') }}: {{ $req->reviewed_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if($req->isPending())
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.category-requests.approve', $req->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/25 text-[#00d9b5] text-[12px] font-bold hover:bg-[#00d9b5]/20 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            {{ __('admin.category_requests.approve') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.category-requests.reject', $req->id) }}" class="inline"
                                          onsubmit="return confirm('{{ __('admin.category_requests.reject_confirm') }}');">
                                        @csrf
                                        <input type="hidden" name="reason" value="" />
                                        <button type="button"
                                                onclick="const r = prompt('{{ __('admin.category_requests.reject_prompt') }}', ''); if (r !== null) { this.form.reason.value = r; this.form.submit(); }"
                                                class="inline-flex items-center gap-1.5 h-9 px-3 rounded-[10px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/25 text-[#ff4d7f] text-[12px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            {{ __('admin.category_requests.reject') }}
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-[11px] text-muted">
                                    {{ __('admin.category_requests.reviewed_by') }}: {{ $req->reviewedBy ? trim(($req->reviewedBy->first_name ?? '').' '.($req->reviewedBy->last_name ?? '')) : '—' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-muted">{{ __('admin.category_requests.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($requests->hasPages())
    <div class="mt-5">{{ $requests->links() }}</div>
@endif

@endsection
