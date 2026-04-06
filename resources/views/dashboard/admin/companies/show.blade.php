@extends('layouts.dashboard', ['active' => 'admin'])
@section('title', $company->name)

@section('content')

<x-dashboard.page-header :title="$company->name" :subtitle="$company->registration_number" :back="route('admin.companies.index')">
    <x-slot:actions>
        <a href="{{ route('admin.companies.edit', $company->id) }}" class="bg-accent text-white px-4 py-2.5 rounded-lg text-[13px] font-semibold">{{ __('common.edit') }}</a>
    </x-slot:actions>
</x-dashboard.page-header>

@include('dashboard.admin._tabs', ['active' => 'companies'])

@php
    // Documents stored as { field_key: storage_path } by RegisterController.
    $docs = is_array($company->documents) ? $company->documents : [];
    $docLabels = [
        'trade_license_file'   => __('admin.companies.doc_trade_license'),
        'tax_certificate_file' => __('admin.companies.doc_tax_certificate'),
        'company_profile_file' => __('admin.companies.doc_company_profile'),
    ];
    $manager = $company->users->firstWhere('role', \App\Enums\UserRole::COMPANY_MANAGER);
@endphp

{{-- Pending review banner — pulls the admin's eye to the approval card. --}}
@if($company->status?->value === 'pending')
<div class="mb-6 rounded-2xl border border-orange-500/30 bg-orange-500/10 p-4 flex items-start gap-3">
    <svg class="w-5 h-5 text-orange-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    <div>
        <p class="text-[13px] font-bold text-orange-400">{{ __('admin.companies.pending_banner_title') }}</p>
        <p class="text-[12px] text-orange-400/80 mt-0.5">{{ __('admin.companies.pending_banner_text') }}</p>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.companies.profile') }}</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.name') }}</dt><dd class="text-primary mt-1 font-semibold">{{ $company->name }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.name_ar') }}</dt><dd class="text-primary mt-1" dir="rtl">{{ $company->name_ar ?? '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.type') }}</dt><dd class="text-primary mt-1">{{ __('role.' . ($company->type?->value ?? 'buyer')) }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.registration_number') }}</dt><dd class="text-primary mt-1 font-mono text-[12px]">{{ $company->registration_number }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.tax_number') }}</dt><dd class="text-primary mt-1 font-mono text-[12px]">{{ $company->tax_number ?? '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.users.email') }}</dt><dd class="text-primary mt-1">@if($company->email)<a href="mailto:{{ $company->email }}" class="hover:text-accent">{{ $company->email }}</a>@else—@endif</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.users.phone') }}</dt><dd class="text-primary mt-1">@if($company->phone)<a href="tel:{{ $company->phone }}" class="hover:text-accent">{{ $company->phone }}</a>@else—@endif</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.website') }}</dt><dd class="text-primary mt-1">@if($company->website)<a href="{{ $company->website }}" target="_blank" rel="noopener noreferrer" class="text-accent hover:underline break-all">{{ $company->website }}</a>@else—@endif</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.city') }}</dt><dd class="text-primary mt-1">{{ $company->city ?? '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.country') }}</dt><dd class="text-primary mt-1">{{ $company->country ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.address') }}</dt><dd class="text-primary mt-1">{{ $company->address ?? '—' }}</dd></div>
                <div class="md:col-span-2"><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.description') }}</dt><dd class="text-body mt-1 whitespace-pre-line">{{ $company->description ?? '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.companies.registered_at') }}</dt><dd class="text-primary mt-1">{{ $company->created_at?->format('M j, Y · g:i A') ?? '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('common.status') }}</dt><dd class="mt-1"><x-dashboard.status-badge :status="$company->status?->value ?? 'pending'" /></dd></div>
            </dl>
        </div>

        {{-- Documents — most important for the approval decision. --}}
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.companies.documents') }}</h3>
            @if(empty($docs))
            <p class="text-[13px] text-muted py-3">{{ __('admin.companies.no_documents') }}</p>
            @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($docLabels as $key => $label)
                    @php $path = $docs[$key] ?? null; @endphp
                    <div class="bg-surface-2 border border-th-border rounded-xl p-4 flex flex-col">
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
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="block mb-3 rounded-lg overflow-hidden border border-th-border bg-page">
                                <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-32 object-cover" />
                            </a>
                            @else
                            <div class="mb-3 rounded-lg border border-th-border bg-page h-32 flex items-center justify-center">
                                <span class="text-[10px] uppercase font-bold text-faint tracking-widest">{{ $ext ?: 'FILE' }}</span>
                            </div>
                            @endif
                            <div class="mt-auto flex items-center gap-2">
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="flex-1 text-center bg-accent text-white text-[11px] font-semibold rounded-lg py-1.5 hover:opacity-90">
                                    {{ __('admin.companies.view_doc') }}
                                </a>
                                <a href="{{ $url }}" download
                                   class="flex-1 text-center bg-surface border border-th-border text-body text-[11px] font-semibold rounded-lg py-1.5 hover:text-primary">
                                    {{ __('admin.companies.download_doc') }}
                                </a>
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-th-border bg-page h-32 flex items-center justify-center">
                                <span class="text-[11px] text-faint">{{ __('admin.companies.not_uploaded') }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Manager (the human Admin should contact for any clarification). --}}
        @if($manager)
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.companies.applicant_manager') }}</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.users.name') }}</dt><dd class="text-primary mt-1 font-semibold">{{ trim(($manager->first_name ?? '') . ' ' . ($manager->last_name ?? '')) ?: '—' }}</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.users.email') }}</dt><dd class="text-primary mt-1"><a href="mailto:{{ $manager->email }}" class="hover:text-accent">{{ $manager->email }}</a></dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('admin.users.phone') }}</dt><dd class="text-primary mt-1">@if($manager->phone)<a href="tel:{{ $manager->phone }}" class="hover:text-accent">{{ $manager->phone }}</a>@else—@endif</dd></div>
                <div><dt class="text-faint text-[11px] uppercase">{{ __('common.status') }}</dt><dd class="mt-1"><x-dashboard.status-badge :status="$manager->status?->value ?? 'pending'" /></dd></div>
            </dl>
        </div>
        @endif

        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('admin.companies.team') }} ({{ $company->users->count() }})</h3>
            <div class="divide-y divide-th-border">
                @forelse($company->users as $u)
                <div class="py-2.5 flex items-center justify-between">
                    <div>
                        <p class="text-[13px] font-semibold text-primary">{{ trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) }}</p>
                        <p class="text-[11px] text-muted">{{ $u->email }}</p>
                    </div>
                    <span class="text-[10px] text-accent bg-accent/10 border border-accent/20 rounded-full px-2 py-0.5">{{ __('role.' . ($u->role?->value ?? 'buyer')) }}</span>
                </div>
                @empty
                <p class="text-[13px] text-muted py-3">{{ __('common.no_data') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <p class="text-[11px] uppercase text-faint mb-2">{{ __('common.status') }}</p>
            <x-dashboard.status-badge :status="$company->status?->value ?? 'pending'" />

            <div class="mt-4 space-y-2">
                @if($company->status?->value === 'pending')
                <form method="POST" action="{{ route('admin.companies.approve', $company->id) }}">@csrf
                    <button class="w-full bg-emerald-500 text-white px-4 py-2 rounded-lg text-[13px] font-semibold">{{ __('admin.companies.approve') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.companies.reject', $company->id) }}">@csrf
                    <button class="w-full bg-red-500 text-white px-4 py-2 rounded-lg text-[13px] font-semibold">{{ __('admin.companies.reject') }}</button>
                </form>
                @elseif($company->status?->value === 'active')
                <form method="POST" action="{{ route('admin.companies.suspend', $company->id) }}">@csrf
                    <button class="w-full bg-orange-500 text-white px-4 py-2 rounded-lg text-[13px] font-semibold">{{ __('admin.companies.suspend') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.companies.reactivate', $company->id) }}">@csrf
                    <button class="w-full bg-emerald-500 text-white px-4 py-2 rounded-lg text-[13px] font-semibold">{{ __('admin.companies.reactivate') }}</button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.companies.destroy', $company->id) }}" onsubmit="return confirm('{{ __('admin.companies.confirm_delete') }}');">@csrf @method('DELETE')
                    <button class="w-full bg-surface-2 border border-red-500/30 text-red-400 px-4 py-2 rounded-lg text-[13px] font-semibold">{{ __('common.delete') }}</button>
                </form>
            </div>
        </div>

        <div class="bg-surface border border-th-border rounded-2xl p-6">
            <h3 class="text-[13px] font-bold text-primary mb-3">{{ __('admin.companies.activity') }}</h3>
            <dl class="text-[12px] space-y-2">
                <div class="flex justify-between"><dt class="text-muted">{{ __('nav.purchase_requests') }}</dt><dd class="text-primary font-semibold">{{ $company->purchase_requests_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('nav.rfqs') }}</dt><dd class="text-primary font-semibold">{{ $company->rfqs_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('nav.bids') }}</dt><dd class="text-primary font-semibold">{{ $company->bids_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('nav.contracts') }}</dt><dd class="text-primary font-semibold">{{ $company->buyer_contracts_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('nav.payment_management') }}</dt><dd class="text-primary font-semibold">{{ $company->payments_count }}</dd></div>
            </dl>
        </div>
    </div>
</div>

@endsection
