@extends('layouts.dashboard', ['active' => 'admin-companies'])
@section('title', $company->name)

@section('content')

<x-dashboard.page-header :title="$company->name" :subtitle="$company->registration_number" :back="route('admin.companies.index')">
    <x-slot:actions>
        <a href="{{ route('admin.companies.edit', $company->id) }}"
           class="inline-flex items-center gap-2 h-12 px-5 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            {{ __('common.edit') }}
        </a>
    </x-slot:actions>
</x-dashboard.page-header>

<x-admin.navbar active="companies" />

@php
    // Documents stored as { field_key: storage_path } by RegisterController.
    $docs = is_array($company->documents) ? $company->documents : [];
    $docLabels = [
        'trade_license_file'   => __('admin.companies.doc_trade_license'),
        'tax_certificate_file' => __('admin.companies.doc_tax_certificate'),
        'company_profile_file' => __('admin.companies.doc_company_profile'),
    ];
    $manager = $company->users->firstWhere('role', \App\Enums\UserRole::COMPANY_MANAGER);

    $palette = ['#4f7cff', '#00d9b5', '#8B5CF6', '#ffb020', '#ff4d7f', '#14B8A6'];
    $brandColor = $palette[$company->id % count($palette)];
@endphp

{{-- ─────────────────────── Hero — company identity strip ─────────────────────── --}}
<div class="bg-surface border border-th-border rounded-[16px] p-[25px] mb-6 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none opacity-[0.05]" style="background: radial-gradient(circle at 100% 0%, {{ $brandColor }} 0%, transparent 60%);"></div>
    <div class="relative flex items-start gap-5 flex-wrap">
        <div class="w-20 h-20 rounded-[16px] font-bold flex items-center justify-center text-[28px] flex-shrink-0"
             style="background: {{ $brandColor }}1a; color: {{ $brandColor }}; border: 1px solid {{ $brandColor }}40;">
            {{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-wrap mb-2">
                <h2 class="text-[24px] font-bold text-primary leading-tight">{{ $company->name }}</h2>
                <x-dashboard.status-badge :status="$company->status?->value ?? 'pending'" />
            </div>
            <p class="text-[13px] text-muted">{{ __('role.' . ($company->type?->value ?? 'buyer')) }} · {{ trim(($company->city ?? '') . ($company->city && $company->country ? ', ' : '') . ($company->country ?? '')) ?: '—' }}</p>
            <div class="mt-4 flex items-center gap-4 flex-wrap text-[12px]">
                @if($company->email)
                <a href="mailto:{{ $company->email }}" class="inline-flex items-center gap-2 text-muted hover:text-accent">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ $company->email }}
                </a>
                @endif
                @if($company->phone)
                <a href="tel:{{ $company->phone }}" class="inline-flex items-center gap-2 text-muted hover:text-accent">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ $company->phone }}
                </a>
                @endif
                @if($company->website)
                <a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-accent hover:underline">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    {{ parse_url($company->website, PHP_URL_HOST) ?: $company->website }}
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Pending review banner — pulls the admin's eye to the approval card. --}}
@if($company->status?->value === 'pending')
<div class="mb-6 rounded-[16px] border border-[#ffb020]/30 bg-[#ffb020]/[0.08] p-[17px] flex items-start gap-3">
    <div class="w-10 h-10 rounded-[10px] bg-[#ffb020]/15 border border-[#ffb020]/30 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    </div>
    <div class="min-w-0">
        <p class="text-[14px] font-bold text-[#ffb020]">{{ __('admin.companies.pending_banner_title') }}</p>
        <p class="text-[12px] text-[#ffb020]/80 mt-0.5">{{ __('admin.companies.pending_banner_text') }}</p>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        {{-- ─────────────────────── Profile card ─────────────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-9 4h12a2 2 0 002-2V7a2 2 0 00-2-2h-2.382a1 1 0 01-.894-.553L11 2H6a2 2 0 00-2 2v15a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('admin.companies.profile') }}</h3>
            </div>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-[13px]">
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.name') }}</dt><dd class="text-primary mt-1.5 font-semibold">{{ $company->name }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.name_ar') }}</dt><dd class="text-primary mt-1.5" dir="rtl">{{ $company->name_ar ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.type') }}</dt><dd class="text-primary mt-1.5">{{ __('role.' . ($company->type?->value ?? 'buyer')) }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.registration_number') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $company->registration_number }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.tax_number') }}</dt><dd class="text-primary mt-1.5 font-mono text-[12px]">{{ $company->tax_number ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.city') }}</dt><dd class="text-primary mt-1.5">{{ $company->city ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.country') }}</dt><dd class="text-primary mt-1.5">{{ $company->country ?? '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.registered_at') }}</dt><dd class="text-primary mt-1.5">{{ $company->created_at?->format('M j, Y · g:i A') ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.address') }}</dt><dd class="text-primary mt-1.5">{{ $company->address ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.companies.description') }}</dt><dd class="text-body mt-1.5 whitespace-pre-line leading-relaxed">{{ $company->description ?? '—' }}</dd></div>
            </dl>
        </div>

        {{-- ─────────────────────── Documents — most important for approval ─────────────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]" id="documents">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#8B5CF6]/10 border border-[#8B5CF6]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#8B5CF6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('admin.companies.documents') }}</h3>
            </div>
            @if(empty($docs))
            <div class="rounded-[12px] border border-dashed border-th-border bg-surface-2/40 px-6 py-8 text-center">
                <p class="text-[13px] text-muted">{{ __('admin.companies.no_documents') }}</p>
            </div>
            @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($docLabels as $key => $label)
                    @php $path = $docs[$key] ?? null; @endphp
                    <div class="bg-surface-2 border border-th-border rounded-[12px] p-[17px] flex flex-col">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            <p class="text-[13px] font-semibold text-primary">{{ $label }}</p>
                        </div>
                        @if($path)
                            @php
                                $url  = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                            @endphp
                            @if($isImage)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="block mb-3 rounded-[10px] overflow-hidden border border-th-border bg-page">
                                <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-32 object-cover" />
                            </a>
                            @else
                            <div class="mb-3 rounded-[10px] border border-th-border bg-page h-32 flex items-center justify-center">
                                <span class="text-[10px] uppercase font-bold text-faint tracking-widest">{{ $ext ?: 'FILE' }}</span>
                            </div>
                            @endif
                            <div class="mt-auto flex items-center gap-2">
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="flex-1 inline-flex items-center justify-center h-9 rounded-[10px] bg-accent text-white text-[11px] font-bold hover:bg-accent-h transition-colors">
                                    {{ __('admin.companies.view_doc') }}
                                </a>
                                <a href="{{ $url }}" download
                                   class="flex-1 inline-flex items-center justify-center h-9 rounded-[10px] bg-surface border border-th-border text-body text-[11px] font-bold hover:text-primary transition-colors">
                                    {{ __('admin.companies.download_doc') }}
                                </a>
                            </div>
                        @else
                            <div class="rounded-[10px] border border-dashed border-th-border bg-page h-32 flex items-center justify-center">
                                <span class="text-[11px] text-faint">{{ __('admin.companies.not_uploaded') }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ─────────────────────── Manager card ─────────────────────── --}}
        @if($manager)
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('admin.companies.applicant_manager') }}</h3>
            </div>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 text-[13px]">
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.users.name') }}</dt><dd class="text-primary mt-1.5 font-semibold">{{ trim(($manager->first_name ?? '') . ' ' . ($manager->last_name ?? '')) ?: '—' }}</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.users.email') }}</dt><dd class="text-primary mt-1.5"><a href="mailto:{{ $manager->email }}" class="hover:text-accent">{{ $manager->email }}</a></dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('admin.users.phone') }}</dt><dd class="text-primary mt-1.5">@if($manager->phone)<a href="tel:{{ $manager->phone }}" class="hover:text-accent">{{ $manager->phone }}</a>@else—@endif</dd></div>
                <div><dt class="text-[10px] font-bold text-faint uppercase tracking-wider">{{ __('common.status') }}</dt><dd class="mt-1.5"><x-dashboard.status-badge :status="$manager->status?->value ?? 'pending'" /></dd></div>
            </dl>
        </div>
        @endif

        {{-- ─────────────────────── Team list ─────────────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-[15px] font-bold text-primary">{{ __('admin.companies.team') }} <span class="text-muted text-[12px] font-medium">({{ $company->users->count() }})</span></h3>
            </div>
            <div class="space-y-2">
                @forelse($company->users as $u)
                @php
                    $userColor = $palette[$u->id % count($palette)];
                @endphp
                <div class="flex items-center gap-3 px-2 py-2 rounded-[10px] hover:bg-surface-2 transition-colors">
                    <div class="w-9 h-9 rounded-full font-bold flex items-center justify-center text-[11px] flex-shrink-0"
                         style="background: {{ $userColor }}1a; color: {{ $userColor }}; border: 1px solid {{ $userColor }}33;">
                        {{ strtoupper(substr($u->first_name ?? 'U', 0, 1) . substr($u->last_name ?? '', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-primary truncate">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                        <p class="text-[11px] text-muted truncate">{{ $u->email }}</p>
                    </div>
                    <span class="text-[10px] font-semibold text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5 flex-shrink-0">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span>
                </div>
                @empty
                <p class="text-[13px] text-muted py-3 text-center">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>

        {{-- ─────────────────────── Request More Info ─────────────────────── --}}
        @if(true)
        @php
            $infoRequestRow  = $company->infoRequest;
            $existingRequest = $infoRequestRow && $infoRequestRow->isPending()
                ? [
                    'items' => $infoRequestRow->items ?? [],
                    'note'  => $infoRequestRow->note ?? '',
                ]
                : null;
            $catalog         = \App\Support\CompanyInfoFields::catalog();
            $fieldEntries    = array_filter($catalog, fn ($e) => ($e['kind'] ?? '') === 'field');
            $docEntries      = array_filter($catalog, fn ($e) => ($e['kind'] ?? '') === 'document');
            $checkedItems    = $existingRequest['items'] ?? [];
        @endphp
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-[10px] bg-[#4f7cff]/10 border border-[#4f7cff]/20 flex items-center justify-center">
                        <svg class="w-[16px] h-[16px] text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-bold text-primary leading-tight">{{ __('admin.companies.request_info_title') }}</h3>
                        <p class="text-[11px] text-muted mt-0.5">{{ __('admin.companies.request_info_help') }}</p>
                    </div>
                </div>
                @if($existingRequest)
                <span class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-bold text-[#ffb020] bg-[#ffb020]/10 border border-[#ffb020]/30 rounded-full px-2.5 py-1 flex-shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ffb020]"></span>
                    {{ __('admin.companies.request_info_active') }}
                </span>
                @endif
            </div>

            @if($existingRequest)
            <div class="bg-surface-2 border border-th-border rounded-[12px] p-[17px] mb-5 text-[12px]">
                <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-3">{{ __('admin.companies.request_info_pending') }}</p>
                <ul class="space-y-1.5 text-body mb-3">
                    @foreach($existingRequest['items'] as $key)
                        @if(isset($catalog[$key]))
                        <li class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __($catalog[$key]['label_key']) }}
                        </li>
                        @endif
                    @endforeach
                </ul>
                @if(!empty($existingRequest['note']))
                <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-1.5">{{ __('admin.companies.request_info_note') }}</p>
                <p class="text-body whitespace-pre-line">{{ $existingRequest['note'] }}</p>
                @endif
                <form method="POST" action="{{ route('admin.companies.cancel-info', $company->id) }}" class="mt-3">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-[11px] font-semibold text-[#ff4d7f] hover:underline">{{ __('admin.companies.request_info_cancel') }}</button>
                </form>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.companies.request-info', $company->id) }}">
                @csrf
                <div class="mb-5">
                    <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('admin.companies.request_info_fields') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($fieldEntries as $key => $entry)
                        <label class="flex items-center gap-2 text-[12px] text-body bg-surface-2 border border-th-border rounded-[10px] px-3 py-2 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="checkbox" name="items[]" value="{{ $key }}" @checked(in_array($key, $checkedItems, true)) />
                            {{ __($entry['label_key']) }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-5">
                    <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2.5">{{ __('admin.companies.request_info_documents') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($docEntries as $key => $entry)
                        <label class="flex items-center gap-2 text-[12px] text-body bg-surface-2 border border-th-border rounded-[10px] px-3 py-2 cursor-pointer hover:border-accent/40 transition-colors">
                            <input type="checkbox" name="items[]" value="{{ $key }}" @checked(in_array($key, $checkedItems, true)) />
                            {{ __($entry['label_key']) }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-faint uppercase tracking-wider mb-2">{{ __('admin.companies.request_info_note_label') }}</label>
                    <textarea name="note" rows="3"
                              placeholder="{{ __('admin.companies.request_info_note_placeholder') }}"
                              class="w-full bg-surface-2 border border-th-border rounded-[12px] px-4 py-3 text-[13px] text-primary focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 resize-none transition-colors">{{ $existingRequest['note'] ?? '' }}</textarea>
                </div>

                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 h-12 px-5 bg-accent text-white rounded-[12px] text-[13px] font-bold hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    {{ $existingRequest ? __('admin.companies.request_info_update') : __('admin.companies.request_info_send') }}
                </button>
            </form>
        </div>
        @endif
    </div>

    {{-- ─────────────────────── Sidebar — actions + activity ─────────────────────── --}}
    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px] sticky top-4">
            <p class="text-[10px] font-bold text-faint uppercase tracking-wider mb-2">{{ __('common.status') }}</p>
            <div class="mb-5">
                <x-dashboard.status-badge :status="$company->status?->value ?? 'pending'" />
            </div>

            <div class="space-y-2">
                @if($company->status?->value === 'pending')
                <form method="POST" action="{{ route('admin.companies.approve', $company->id) }}">@csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] bg-[#00d9b5] text-white text-[13px] font-bold hover:brightness-110 shadow-[0_4px_14px_rgba(0,217,181,0.25)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('admin.companies.approve') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.companies.reject', $company->id) }}">@csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] bg-[#ff4d7f]/10 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[13px] font-bold hover:bg-[#ff4d7f]/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('admin.companies.reject') }}
                    </button>
                </form>
                @elseif($company->status?->value === 'active')
                <form method="POST" action="{{ route('admin.companies.suspend', $company->id) }}">@csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] bg-[#ffb020]/10 border border-[#ffb020]/30 text-[#ffb020] text-[13px] font-bold hover:bg-[#ffb020]/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('admin.companies.suspend') }}
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.companies.reactivate', $company->id) }}">@csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-12 rounded-[12px] bg-[#00d9b5] text-white text-[13px] font-bold hover:brightness-110 shadow-[0_4px_14px_rgba(0,217,181,0.25)] transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('admin.companies.reactivate') }}
                    </button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.companies.destroy', $company->id) }}" onsubmit="return confirm('{{ __('admin.companies.confirm_delete') }}');">@csrf @method('DELETE')
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-[12px] bg-surface-2 border border-[#ff4d7f]/30 text-[#ff4d7f] text-[13px] font-bold hover:bg-[#ff4d7f]/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('common.delete') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- ─────────────────────── Activity counters ─────────────────────── --}}
        <div class="bg-surface border border-th-border rounded-[16px] p-[25px]">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-[10px] bg-[#14B8A6]/10 border border-[#14B8A6]/20 flex items-center justify-center">
                    <svg class="w-[16px] h-[16px] text-[#14B8A6]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <h3 class="text-[14px] font-bold text-primary">{{ __('admin.companies.activity') }}</h3>
            </div>
            <dl class="text-[12px] space-y-3">
                @foreach([
                    ['label' => __('nav.purchase_requests'), 'count' => $company->purchase_requests_count, 'color' => '#4f7cff'],
                    ['label' => __('nav.rfqs'),              'count' => $company->rfqs_count,              'color' => '#8B5CF6'],
                    ['label' => __('nav.bids'),              'count' => $company->bids_count,              'color' => '#00d9b5'],
                    ['label' => __('nav.contracts'),         'count' => $company->buyer_contracts_count,   'color' => '#ffb020'],
                    ['label' => __('nav.payment_management'),'count' => $company->payments_count,          'color' => '#14B8A6'],
                ] as $row)
                <div class="flex items-center justify-between gap-3">
                    <dt class="flex items-center gap-2 text-muted">
                        <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $row['color'] }};"></span>
                        {{ $row['label'] }}
                    </dt>
                    <dd class="text-primary font-bold">{{ number_format($row['count']) }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
    </div>
</div>

@endsection
