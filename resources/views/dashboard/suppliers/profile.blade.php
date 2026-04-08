@extends('layouts.dashboard', ['active' => 'rfqs'])
@section('title', $company->name)

@php
    $totalReviews = $review_count;
    // Compute the longest bar so we can normalize the histogram widths.
    $maxBar = max($distribution) ?: 1;
@endphp

@section('content')

<a href="{{ url()->previous() }}"
   class="inline-flex items-center gap-2 text-[14px] text-[#b4b6c0] hover:text-white mb-4 transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    {{ __('common.back') }}
</a>

{{-- Header card --}}
<div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px] mb-6">
    <div class="flex items-start justify-between gap-6 flex-wrap">
        <div class="flex items-start gap-4 min-w-0">
            <div class="w-16 h-16 rounded-[12px] bg-[rgba(79,124,255,0.1)] flex items-center justify-center flex-shrink-0">
                @if($company->logo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="" class="w-full h-full object-cover rounded-[12px]">
                @else
                    <span class="text-[20px] font-bold text-[#4f7cff]">{{ strtoupper(substr($company->name, 0, 2)) }}</span>
                @endif
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-[24px] sm:text-[28px] font-bold text-white leading-tight tracking-[-0.02em]">{{ $company->name }}</h1>
                    {{-- Phase 2 / Sprint 8 / task 2.8 — real verification tier badge.
                         Reflects whatever level the admin verification queue
                         most recently promoted the company to. --}}
                    <x-dashboard.verification-badge :level="$company->verification_level" />
                    {{-- Phase 2 / Sprint 10 / task 2.15 — Insured badge sits
                         next to the verification tier when at least one
                         verified, non-expired policy is on file. --}}
                    <x-dashboard.insured-badge :insured="$company->isInsured()" />
                </div>
                <p class="text-[14px] text-[#b4b6c0] mt-1">
                    @if($company->city || $company->country)
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            {{ trim(($company->city ?? '') . ($company->country ? ', ' . $company->country : '')) }}
                        </span>
                    @endif
                    @if($years_active)
                        <span class="inline-flex items-center gap-1 ms-3">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
                            {{ $years_active }} {{ $years_active === 1 ? 'year' : 'years' }} on platform
                        </span>
                    @endif
                </p>
                @if($company->description)
                <p class="text-[14px] text-[#b4b6c0] mt-3 max-w-2xl">{{ $company->description }}</p>
                @endif
            </div>
        </div>

        {{-- Big rating block --}}
        <div class="text-end flex-shrink-0">
            @if($rating)
                <p class="text-[36px] font-bold text-[#ffb020] leading-none">{{ $rating }}</p>
                <div class="flex items-center justify-end gap-0.5 mt-2">
                    @for($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= round($rating) ? 'text-[#ffb020]' : 'text-[#252932]' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    @endfor
                </div>
                <p class="text-[12px] text-[#b4b6c0] mt-1">{{ $totalReviews }} {{ $totalReviews === 1 ? 'review' : 'reviews' }}</p>
            @else
                <p class="text-[14px] text-[#b4b6c0]">No reviews yet</p>
            @endif
        </div>
    </div>

    {{-- Stat row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mt-6 pt-6 border-t border-[rgba(255,255,255,0.08)]">
        <div>
            <p class="text-[24px] font-bold text-[#4f7cff]">{{ $completed_contracts }}</p>
            <p class="text-[12px] text-[#b4b6c0]">Contracts Delivered</p>
        </div>
        <div>
            <p class="text-[24px] font-bold text-[#00d9b5]">{{ $breakdown['quality'] ?? '—' }}</p>
            <p class="text-[12px] text-[#b4b6c0]">Quality Score</p>
        </div>
        <div>
            <p class="text-[24px] font-bold text-[#ffb020]">{{ $breakdown['on_time'] ?? '—' }}</p>
            <p class="text-[12px] text-[#b4b6c0]">On-Time Score</p>
        </div>
        <div>
            <p class="text-[24px] font-bold text-[#8b5cf6]">{{ $breakdown['communication'] ?? '—' }}</p>
            <p class="text-[12px] text-[#b4b6c0]">Communication</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- LEFT: Reviews list --}}
    <div class="lg:col-span-2 bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
        <h3 class="text-[16px] font-semibold text-white mb-5">Reviews</h3>

        @if($totalReviews === 0)
            <div class="text-center py-12 text-[14px] text-[#b4b6c0]">
                No reviews yet. Reviews appear here once contracts with this company are completed.
            </div>
        @else
            <div class="space-y-4">
                @foreach($reviews as $review)
                <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.08)] rounded-[12px] p-5">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <p class="text-[14px] font-medium text-white">{{ $review['rater_company'] }}</p>
                            <p class="text-[12px] text-[#b4b6c0]">{{ $review['contract'] }} · {{ $review['when'] }}</p>
                        </div>
                        <div class="flex items-center gap-0.5 flex-shrink-0">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-3.5 h-3.5 {{ $i <= $review['rating'] ? 'text-[#ffb020]' : 'text-[#252932]' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            @endfor
                        </div>
                    </div>
                    @if($review['comment'])
                    <p class="text-[13px] text-[#b4b6c0] leading-[20px] mt-2">{{ $review['comment'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- RIGHT sidebar --}}
    <div class="space-y-4">
        {{-- Rating distribution --}}
        @if($totalReviews > 0)
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Rating Breakdown</h3>
            <div class="space-y-2">
                @for($i = 5; $i >= 1; $i--)
                <div class="flex items-center gap-3 text-[12px]">
                    <span class="text-[#b4b6c0] w-6">{{ $i }}★</span>
                    <div class="flex-1 h-2 bg-[#252932] rounded-full overflow-hidden">
                        <div class="h-full bg-[#ffb020] rounded-full" style="width: {{ ($distribution[$i] / $maxBar) * 100 }}%"></div>
                    </div>
                    <span class="text-[#b4b6c0] w-6 text-end">{{ $distribution[$i] }}</span>
                </div>
                @endfor
            </div>
        </div>
        @endif

        {{-- Certifications --}}
        @if(!empty($certifications))
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Certifications</h3>
            <div class="space-y-2">
                @foreach($certifications as $cert)
                <div class="flex items-start gap-3 bg-[#0f1117] border border-[rgba(255,255,255,0.08)] rounded-[10px] p-3">
                    <svg class="w-5 h-5 text-[#00d9b5] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium text-white">{{ $cert['name'] }}</p>
                        @if($cert['issuer'])
                        <p class="text-[11px] text-[#b4b6c0]">{{ $cert['issuer'] }}</p>
                        @endif
                        @if($cert['expires_at'])
                        <p class="text-[11px] text-[#b4b6c0]">Valid until {{ \Carbon\Carbon::parse($cert['expires_at'])->format('M Y') }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Contact card --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Contact</h3>
            <dl class="space-y-3 text-[13px]">
                @if($company->email)
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Email</dt>
                    <dd class="text-white font-medium break-all">{{ $company->email }}</dd>
                </div>
                @endif
                @if($company->phone)
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Phone</dt>
                    <dd class="text-white font-medium">{{ $company->phone }}</dd>
                </div>
                @endif
                @if($company->website)
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Website</dt>
                    <dd class="text-white font-medium break-all"><a href="{{ $company->website }}" class="hover:text-[#4f7cff] transition-colors" target="_blank" rel="noopener">{{ $company->website }}</a></dd>
                </div>
                @endif
                @if(!$company->email && !$company->phone && !$company->website)
                <p class="text-[#b4b6c0]">Contact details unavailable.</p>
                @endif
            </dl>
        </div>
    </div>
</div>

@endsection
