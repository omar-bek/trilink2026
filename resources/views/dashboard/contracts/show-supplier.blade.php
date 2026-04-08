@extends('layouts.dashboard', ['active' => 'contracts'])
@section('title', $contract['id'])

@php
$statusPills = [
    'active'    => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'border' => 'border-[rgba(255,176,32,0.2)]', 'text' => 'text-[#ffb020]', 'label' => 'Manufacturing'],
    'pending'   => ['bg' => 'bg-[rgba(79,124,255,0.1)]', 'border' => 'border-[rgba(79,124,255,0.2)]', 'text' => 'text-[#4f7cff]', 'label' => 'Pending'],
    'completed' => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'border' => 'border-[rgba(0,217,181,0.2)]', 'text' => 'text-[#00d9b5]', 'label' => 'Completed'],
    'closed'    => ['bg' => 'bg-[rgba(180,182,192,0.1)]','border' => 'border-[rgba(180,182,192,0.2)]','text' => 'text-[#b4b6c0]', 'label' => 'Closed'],
];
$pill = $statusPills[$contract['status']] ?? $statusPills['active'];

// Milestone status → icon color
$milestoneColor = [
    'paid'    => ['bg' => 'bg-[rgba(0,217,181,0.1)]',  'text' => 'text-[#00d9b5]', 'label_bg' => 'bg-[rgba(0,217,181,0.1)]',  'label_text' => 'text-[#00d9b5]',  'label' => 'Completed'],
    'pending' => ['bg' => 'bg-[rgba(255,176,32,0.1)]', 'text' => 'text-[#ffb020]', 'label_bg' => 'bg-[rgba(255,176,32,0.1)]', 'label_text' => 'text-[#ffb020]', 'label' => 'In Progress'],
    'future'  => ['bg' => 'bg-[rgba(180,182,192,0.1)]','text' => 'text-[#b4b6c0]', 'label_bg' => 'bg-[rgba(180,182,192,0.1)]','label_text' => 'text-[#b4b6c0]','label' => 'Pending'],
];
@endphp

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
    <div class="flex items-start gap-3 min-w-0">
        <a href="{{ route('dashboard.contracts') }}"
           class="w-10 h-10 rounded-[12px] bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] flex items-center justify-center text-[#b4b6c0] hover:text-white hover:border-[#4f7cff]/40 flex-shrink-0 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[28px] sm:text-[32px] font-bold text-white leading-tight tracking-[-0.02em]">{{ $contract['id'] }}</h1>
                <span class="inline-flex items-center h-[26px] px-3 rounded-full border {{ $pill['bg'] }} {{ $pill['border'] }} {{ $pill['text'] }} text-[12px] font-medium">
                    {{ $contract['progress_label'] ?: $pill['label'] }}
                </span>
            </div>
            <p class="text-[14px] text-[#b4b6c0] mt-1">{{ $contract['title'] }}</p>
        </div>
    </div>
    <a href="{{ route('dashboard.contracts.pdf', ['id' => $contract['numeric_id']]) }}"
       class="inline-flex items-center gap-2 h-12 px-5 rounded-[12px] text-[14px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        Download Contract
    </a>
</div>

{{-- Progress bar card --}}
<div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px] mb-6">
    <div class="flex items-start justify-between gap-4 mb-3 flex-wrap">
        <div>
            <p class="text-[14px] text-[#b4b6c0] mb-1">Contract Progress</p>
            <p class="text-[32px] font-bold text-white leading-none">{{ $contract['progress'] }}%</p>
        </div>
        @if($contract['days_remaining'] !== null)
        <div class="text-end">
            <p class="text-[14px] text-[#b4b6c0] mb-1">Days Remaining</p>
            <p class="text-[32px] font-bold text-[#ffb020] leading-none">{{ $contract['days_remaining'] }}</p>
        </div>
        @endif
    </div>
    <div class="w-full h-2 bg-[#252932] rounded-full overflow-hidden mt-4">
        <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $contract['progress'] }}%"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- LEFT: Tabs + content --}}
    <div class="lg:col-span-2">
        <div x-data="{ tab: 'overview' }" class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <div class="flex items-center gap-6 border-b border-[rgba(255,255,255,0.1)] mb-6 -mx-[25px] px-[25px]">
                @foreach(['overview' => 'Overview', 'items' => 'Items', 'payments' => 'Payments', 'documents' => 'Documents'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'text-[#4f7cff] border-[#4f7cff]' : 'text-[#b4b6c0] border-transparent hover:text-white'"
                        class="pb-3 text-[14px] font-medium border-b-2 transition-colors">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Overview tab --}}
            <div x-show="tab === 'overview'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Buyer</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['buyer_contact']['name'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Contract Value</p>
                        <p class="text-[14px] font-medium text-[#00d9b5]">{{ $contract['total_amount'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Start Date</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['start_date'] }}</p>
                    </div>
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4">
                        <p class="text-[12px] text-[#b4b6c0] mb-1">Expected Delivery</p>
                        <p class="text-[14px] font-medium text-white">{{ $contract['end_date'] }}</p>
                    </div>
                </div>

                @if(!empty($contract['payment_schedule']))
                <div class="mb-6">
                    <x-payment-schedule
                        :rows="$contract['payment_schedule']"
                        :total="$contract['total_amount']"
                        title="Payment Schedule"
                        subtitle="Milestone breakdown for this contract." />
                </div>
                @endif

                <h3 class="text-[16px] font-semibold text-white mb-3">Contract Milestones</h3>
                <div class="space-y-3">
                    @forelse($contract['milestones'] as $m)
                    @php $mc = $milestoneColor[$m['status']] ?? $milestoneColor['future']; @endphp
                    <div class="bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-full {{ $mc['bg'] }} flex items-center justify-center flex-shrink-0">
                                @if($m['status'] === 'paid')
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($m['status'] === 'pending')
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                                @else
                                <svg class="w-5 h-5 {{ $mc['text'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-[14px] font-medium text-white">{{ $m['name'] }}</p>
                                <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $m['paid_date'] ?? $m['due_date'] ?? '—' }}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center h-6 px-3 rounded-full {{ $mc['label_bg'] }} {{ $mc['label_text'] }} text-[12px] font-medium flex-shrink-0">
                            {{ $mc['label'] }}
                        </span>
                    </div>
                    @empty
                    <p class="text-[13px] text-[#b4b6c0] text-center py-6">No milestones defined.</p>
                    @endforelse
                </div>
            </div>

            <div x-show="tab === 'items'" x-cloak>
                <p class="text-[14px] text-[#b4b6c0]">Contract line items will be shown here.</p>
            </div>
            <div x-show="tab === 'payments'" x-cloak>
                <p class="text-[14px] text-[#b4b6c0]">Payment schedule and history.</p>
            </div>
            <div x-show="tab === 'documents'" x-cloak>
                {{-- Contract PDF + amendments (buyer-side contract files). --}}
                <div class="space-y-3 mb-5">
                    <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider">Contract Files</p>
                    @forelse($contract['documents'] as $doc)
                    <a href="{{ $doc['url'] ?? '#' }}" class="flex items-center gap-3 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 hover:border-[#4f7cff]/40 transition-colors">
                        <svg class="w-5 h-5 text-[#4f7cff]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <p class="text-[14px] font-medium text-white flex-1">{{ $doc['name'] }}</p>
                    </a>
                    @empty
                    <p class="text-[13px] text-[#b4b6c0] text-center py-4">No contract files.</p>
                    @endforelse
                </div>

                {{-- Supplier-uploaded documents (production photos, QC, etc.) --}}
                @if(!empty($contract['supplier_documents']))
                <div class="space-y-3">
                    <p class="text-[12px] font-medium text-[#b4b6c0] uppercase tracking-wider">Production Documents</p>
                    @foreach($contract['supplier_documents'] as $doc)
                    <a href="{{ $doc['url'] }}" class="flex items-center justify-between gap-3 bg-[#0f1117] border border-[rgba(255,255,255,0.1)] rounded-[12px] p-4 hover:border-[#4f7cff]/40 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-[10px] bg-[rgba(0,217,181,0.1)] flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[14px] font-medium text-white truncate">{{ $doc['name'] }}</p>
                                <p class="text-[12px] text-[#b4b6c0]">{{ $doc['type'] }} · {{ $doc['size'] }} · {{ $doc['uploaded_at'] }}</p>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-[#b4b6c0] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Phase 3 — Escrow visibility for the supplier side. Read-only:
             the supplier sees how much has been deposited / released and a
             stream of recent events, but the action buttons stay hidden
             because escrow.deposit / escrow.release are buyer-only. --}}
        @if(!empty($contract['escrow']))
        <div class="mt-4">
            @include('dashboard.contracts._escrow-panel', ['escrow' => $contract['escrow'], 'contract_id' => $contract['numeric_id']])
        </div>
        @endif

        {{-- Progress update log (visible when there are entries) --}}
        @if(!empty($contract['progress_log']))
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px] mt-4">
            <h3 class="text-[16px] font-semibold text-white mb-4">Progress Updates</h3>
            <div class="space-y-3">
                @foreach($contract['progress_log'] as $entry)
                <div class="flex items-start gap-3 pb-3 border-b border-[rgba(255,255,255,0.06)] last:border-b-0 last:pb-0">
                    <div class="w-8 h-8 rounded-full bg-[rgba(0,217,181,0.15)] flex items-center justify-center flex-shrink-0 text-[11px] font-semibold text-[#00d9b5]">{{ $entry['percent'] }}%</div>
                    <div class="flex-1 min-w-0">
                        @if($entry['note'])
                        <p class="text-[14px] text-white leading-[20px]">{{ $entry['note'] }}</p>
                        @else
                        <p class="text-[14px] text-[#b4b6c0] italic">Progress updated to {{ $entry['percent'] }}%</p>
                        @endif
                        <p class="text-[12px] text-[#b4b6c0] mt-0.5">{{ $entry['when'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Rate this contract (only after completion) --}}
        @if($contract['can_review'])
        <div class="mt-4">
            <x-contract-review :contract-id="$contract['numeric_id']" :existing="$contract['existing_review']" />
        </div>
        @endif
    </div>

    {{-- RIGHT: supplier-side action panels --}}
    <div class="space-y-4">
        {{-- Quick Actions (supplier-specific). Each action expands an inline
             form using Alpine — no modal infrastructure needed. --}}
        <div x-data="{ open: null }" class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Quick Actions</h3>
            <div class="space-y-3">
                {{-- Update Progress --}}
                <button type="button" @click="open = open === 'progress' ? null : 'progress'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5v9"/></svg>
                    Update Progress
                </button>
                <form x-show="open === 'progress'" x-cloak method="POST"
                      action="{{ route('dashboard.contracts.progress', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Progress (%)</label>
                        <input type="number" name="progress_percentage" min="0" max="100" required
                               value="{{ $contract['progress'] }}"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Note (optional)</label>
                        <textarea name="note" rows="2" placeholder="What changed?"
                                  class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 py-2 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#00d9b5] hover:bg-[#00c9a5] transition-colors">Save</button>
                </form>

                {{-- Upload Documents --}}
                <button type="button" @click="open = open === 'documents' ? null : 'documents'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    Upload Documents
                </button>
                <form x-show="open === 'documents'" x-cloak method="POST" enctype="multipart/form-data"
                      action="{{ route('dashboard.contracts.documents.upload', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <input type="file" name="documents[]" multiple required
                           class="block w-full text-[12px] text-[#b4b6c0] file:bg-[#4f7cff] file:text-white file:border-0 file:rounded-[8px] file:px-3 file:py-2 file:me-3 file:cursor-pointer file:text-[12px]">
                    <p class="text-[11px] text-[#b4b6c0]">PDF, DOC, XLS, Images. Max 10MB each, 10 files.</p>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">Upload</button>
                </form>

                {{-- Schedule Shipment --}}
                <button type="button" @click="open = open === 'shipment' ? null : 'shipment'"
                        class="w-full inline-flex items-center gap-2 h-11 px-4 rounded-[12px] text-[14px] font-medium text-white bg-[#0f1117] border border-[rgba(255,255,255,0.1)] hover:border-[#4f7cff]/40 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0H2.25"/></svg>
                    Schedule Shipment
                </button>
                <form x-show="open === 'shipment'" x-cloak method="POST"
                      action="{{ route('dashboard.contracts.shipments.schedule', ['id' => $contract['numeric_id']]) }}"
                      class="space-y-3 p-4 bg-[#0f1117] rounded-[12px] border border-[rgba(255,255,255,0.08)]">
                    @csrf
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Tracking Number</label>
                        <input type="text" name="tracking_number" placeholder="auto-generated if blank"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Carrier</label>
                        <input type="text" name="carrier" placeholder="DHL, Aramex, FedEx..."
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="origin" placeholder="Origin"
                               class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                        <input type="text" name="destination" placeholder="Destination"
                               class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white placeholder:text-[rgba(255,255,255,0.4)] focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[12px] text-[#b4b6c0] mb-1.5">Estimated Delivery</label>
                        <input type="date" name="estimated_delivery"
                               class="w-full bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[10px] px-3 h-10 text-[13px] text-white focus:outline-none focus:border-[#4f7cff]/50 transition-colors">
                    </div>
                    <button type="submit" class="w-full h-10 rounded-[10px] text-[13px] font-medium text-white bg-[#4f7cff] hover:bg-[#6b91ff] transition-colors">Schedule</button>
                </form>
            </div>
        </div>

        {{-- Payment Summary --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">{{ __('contracts.payment_summary') }}</h3>
            <dl class="space-y-3 text-[13px]">
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.total_contract') }}</dt>
                    <dd class="text-white font-medium">{{ $contract['total_amount'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.received') }}</dt>
                    <dd class="text-[#00d9b5] font-semibold">{{ $contract['paid_amount'] }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-[#b4b6c0]">{{ __('contracts.pending') }}</dt>
                    <dd class="text-[#ffb020] font-semibold">{{ $contract['pending_amount'] }}</dd>
                </div>
            </dl>
            <div class="mt-4 pt-4 border-t border-[rgba(255,255,255,0.1)]">
                <div class="flex items-center justify-between text-[12px] mb-2">
                    <span class="text-[#b4b6c0]">{{ __('common.progress') }}</span>
                    <span class="text-white font-medium">{{ $contract['progress'] }}%</span>
                </div>
                <div class="w-full h-1.5 bg-[#252932] rounded-full overflow-hidden">
                    <div class="h-full bg-[#00d9b5] rounded-full" style="width: {{ $contract['progress'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Buyer Contact --}}
        <div class="bg-[#1a1d29] border border-[rgba(255,255,255,0.1)] rounded-[16px] p-5 sm:p-[25px]">
            <h3 class="text-[16px] font-semibold text-white mb-4">Buyer Contact</h3>
            <dl class="space-y-3 text-[13px]">
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Company</dt>
                    <dd class="text-white font-medium">{{ $contract['buyer_contact']['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Email</dt>
                    <dd class="text-white font-medium break-all">{{ $contract['buyer_contact']['email'] }}</dd>
                </div>
                <div>
                    <dt class="text-[#b4b6c0] mb-1">Phone</dt>
                    <dd class="text-white font-medium">{{ $contract['buyer_contact']['phone'] }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

@endsection
