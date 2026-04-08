@extends('layouts.dashboard', ['active' => 'purchase-requests'])
@section('title', __('pr.create_title'))

@section('content')

<div class="mb-6 sm:mb-8 flex items-center gap-3 sm:gap-4">
    <a href="{{ route('dashboard.purchase-requests') }}" class="w-10 h-10 rounded-xl bg-surface border border-th-border flex items-center justify-center text-muted hover:text-primary transition-colors flex-shrink-0" aria-label="{{ __('common.go_back') }}">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
    </a>
    <div class="min-w-0">
        <h1 class="text-[22px] sm:text-[28px] lg:text-[32px] font-bold text-primary leading-tight">{{ __('pr.create_title') }}</h1>
        <p class="text-[13px] sm:text-[14px] text-muted mt-1">{{ __('pr.create_subtitle') }}</p>
    </div>
</div>

{{-- Stepper --}}
<div class="bg-surface border border-th-border rounded-2xl p-5 sm:p-8 mb-6">
    @php
    $steps = [
        ['n' => 1, 'key' => 'general',  'label' => __('pr.general_info'),  'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
        ['n' => 2, 'key' => 'items',    'label' => __('pr.line_items'),    'icon' => 'M12 4.5v15m7.5-7.5h-15'],
        ['n' => 3, 'key' => 'locations','label' => __('pr.locations'),     'icon' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
        ['n' => 4, 'key' => 'review',   'label' => __('pr.review'),        'icon' => 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ];
    @endphp
    <div class="flex items-start justify-between">
        @foreach($steps as $i => $step)
        <div class="flex flex-col items-center text-center flex-1 min-w-0">
            <div data-step-circle="{{ $step['n'] }}" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center transition-all flex-shrink-0 {{ $i === 0 ? 'bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]' : 'bg-surface-2 border border-th-border text-muted' }}">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}"/></svg>
            </div>
            <span data-step-label="{{ $step['n'] }}" class="mt-2 sm:mt-3 text-[11px] sm:text-[12px] font-semibold leading-tight {{ $i === 0 ? 'text-primary' : 'text-muted' }}">{{ $step['label'] }}</span>
        </div>
        @if(!$loop->last)
        <div class="flex-shrink-0 w-6 sm:flex-1 sm:w-auto h-0.5 bg-th-border mt-5 sm:mt-6 mx-1 sm:mx-2 relative">
            <div data-step-line="{{ $step['n'] }}" class="absolute inset-y-0 start-0 bg-accent rounded-full transition-all duration-500" style="width: 0;"></div>
        </div>
        @endif
        @endforeach
    </div>
</div>

@if ($errors->any())
<div class="mb-6 bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-xl p-4 text-[13px] text-[#ff4d7f]">
    <ul class="list-disc ms-5 space-y-1">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<form id="pr-form" action="{{ route('dashboard.purchase-requests.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf

    {{-- STEP 1: General Information --}}
    <div data-step-panel="1" class="bg-surface border border-th-border rounded-2xl p-5 sm:p-8">
        <h2 class="text-[20px] font-bold text-primary mb-6">{{ __('pr.general_information') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.request_title') }} <span class="text-[#ff4d7f]">*</span></label>
                <input type="text" name="title" placeholder="{{ __('pr.title_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50" required>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.category') }} <span class="text-[#ff4d7f]">*</span></label>
                <select name="category_id" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none" required>
                    <option value="">--</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.description') }} <span class="text-[#ff4d7f]">*</span></label>
                <textarea name="description" rows="4" placeholder="{{ __('pr.description_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none" required></textarea>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.currency') }} <span class="text-[#ff4d7f]">*</span></label>
                <select name="currency" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none" required>
                    <option value="">--</option>
                    @foreach($currencies as $cur)
                        <option value="{{ $cur }}">{{ $cur }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.estimated_budget') }}</label>
                <input type="number" name="budget" placeholder="0.00" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.priority_level') }}</label>
                <select name="priority" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                    <option value="">--</option>
                    <option value="low">{{ __('pr.priority_low') }}</option>
                    <option value="medium">{{ __('pr.priority_medium') }}</option>
                    <option value="high">{{ __('pr.priority_high') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.expected_delivery') }}</label>
                <input type="date" name="expected_delivery" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-th-border flex items-center justify-between">
            <button type="button" disabled class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-muted bg-surface-2 border border-th-border opacity-50 cursor-not-allowed inline-flex items-center gap-2">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('common.previous') }}
            </button>
            <div class="flex items-center gap-3">
                <button type="button" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.save_draft') }}
                </button>
                <button type="button" onclick="nextStep(2)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] inline-flex items-center gap-2">
                    {{ __('common.next') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- STEP 2: Line Items --}}
    <div data-step-panel="2" class="bg-surface border border-th-border rounded-2xl p-5 sm:p-8 hidden">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-[20px] font-bold text-primary">{{ __('pr.line_items') }}</h2>
            <button type="button" onclick="addItem()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('pr.add_item') }}
            </button>
        </div>

        <div id="items-container">
            <div class="bg-page border border-th-border rounded-xl p-5 sm:p-6">
                <p class="text-[12.5px] sm:text-[13px] text-muted mb-4 font-mono">{{ __('pr.item_n', ['n' => 1]) }}</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.product_name') }} <span class="text-[#ff4d7f]">*</span></label>
                        <input type="text" name="items[0][name]" placeholder="{{ __('pr.product_name_placeholder') }}" class="w-full bg-surface border border-th-border rounded-lg px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.quantity') }} <span class="text-[#ff4d7f]">*</span></label>
                            <input type="number" name="items[0][qty]" placeholder="0" class="w-full bg-surface border border-th-border rounded-lg px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.unit') }} <span class="text-[#ff4d7f]">*</span></label>
                            <select name="items[0][unit]" class="w-full bg-surface border border-th-border rounded-lg px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                                <option value="pieces">{{ __('pr.unit_pieces') }}</option>
                                <option value="units">{{ __('pr.unit_units') }}</option>
                                <option value="boxes">{{ __('pr.unit_boxes') }}</option>
                                <option value="kg">{{ __('pr.unit_kg') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.tech_specs') }}</label>
                        <textarea name="items[0][spec]" rows="3" placeholder="{{ __('pr.tech_specs_placeholder') }}" class="w-full bg-surface border border-th-border rounded-lg px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.attachments_optional') }}</label>
                        <label class="block cursor-pointer">
                            <div class="border-2 border-dashed border-th-border hover:border-accent/40 rounded-xl py-10 px-6 text-center transition-colors">
                                <svg class="w-7 h-7 text-muted mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                <p class="text-[13px] text-primary font-medium">{{ __('pr.upload_hint') }}</p>
                                <p class="text-[11px] text-faint mt-1">{{ __('pr.upload_hint_files') }}</p>
                            </div>
                            <input type="file" class="hidden" multiple>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-th-border flex items-center justify-between">
            <button type="button" onclick="prevStep(1)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('common.previous') }}
            </button>
            <div class="flex items-center gap-3">
                <button type="button" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.save_draft') }}
                </button>
                <button type="button" onclick="nextStep(3)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] inline-flex items-center gap-2">
                    {{ __('common.next') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- STEP 3: Locations & Logistics --}}
    <div data-step-panel="3" class="bg-surface border border-th-border rounded-2xl p-5 sm:p-8 hidden">
        <h2 class="text-[20px] font-bold text-primary mb-6">{{ __('pr.locations_logistics') }}</h2>

        <div class="space-y-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.delivery_location') }} <span class="text-[#ff4d7f]">*</span></label>
                <input type="text" name="delivery_address" placeholder="{{ __('pr.delivery_address') }}" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50 mb-3">
                <input type="text" name="delivery_city" placeholder="{{ __('pr.delivery_city_placeholder') }}" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/50">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.expected_delivery') }}</label>
                    <input type="date" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('pr.delivery_terms') }}</label>
                    <select class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary focus:outline-none focus:border-accent/50 appearance-none">
                        <option value="">--</option>
                        <option>FOB</option>
                        <option>CIF</option>
                        <option>EXW</option>
                        <option>DDP</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <label class="flex items-start gap-3 p-4 bg-page border border-th-border rounded-xl cursor-pointer hover:border-accent/40 transition-colors">
                    <input type="checkbox" name="needs_logistics" value="1" class="mt-0.5 w-4 h-4 accent-accent">
                    <span class="text-[13px] text-body leading-relaxed">{{ __('pr.want_logistics') }}</span>
                </label>
                <label class="flex items-start gap-3 p-4 bg-page border border-th-border rounded-xl cursor-pointer hover:border-accent/40 transition-colors">
                    <input type="checkbox" name="needs_clearance" value="1" class="mt-0.5 w-4 h-4 accent-accent">
                    <span class="text-[13px] text-body leading-relaxed">{{ __('pr.want_customs') }}</span>
                </label>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-th-border flex items-center justify-between">
            <button type="button" onclick="prevStep(2)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('common.previous') }}
            </button>
            <div class="flex items-center gap-3">
                <button type="button" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.save_draft') }}
                </button>
                <button type="button" onclick="nextStep(4)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] inline-flex items-center gap-2">
                    {{ __('common.next') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14m0 0l-7-7m7 7l-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- STEP 4: Review --}}
    <div data-step-panel="4" class="bg-surface border border-th-border rounded-2xl p-5 sm:p-8 hidden">
        <h2 class="text-[20px] font-bold text-primary mb-6">{{ __('pr.review_submit') }}</h2>

        <div class="bg-accent/5 border border-accent/15 rounded-xl p-5 sm:p-6 mb-5">
            <h3 class="text-[14px] font-bold text-accent mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                {{ __('pr.general_information') }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-[13px]">
                <div><span class="text-muted">{{ __('pr.request_title') }}:</span><p id="review-title" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted">{{ __('pr.category') }}:</span><p id="review-category" class="font-semibold text-primary mt-0.5">—</p></div>
                <div><span class="text-muted">{{ __('pr.estimated_budget') }}:</span><p id="review-budget" class="font-semibold text-[#00d9b5] mt-0.5">—</p></div>
                <div><span class="text-muted">{{ __('pr.expected_delivery') }}:</span><p id="review-delivery" class="font-semibold text-primary mt-0.5">—</p></div>
                <div class="col-span-2"><span class="text-muted">{{ __('pr.delivery_location') }}:</span><p id="review-location" class="font-semibold text-primary mt-0.5">—</p></div>
            </div>
        </div>

        <div class="bg-[#00d9b5]/5 border border-[#00d9b5]/15 rounded-xl p-5 sm:p-6 mb-5">
            <h3 class="text-[14px] font-bold text-[#00d9b5] mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                {{ __('pr.line_items') }}
            </h3>
            <div id="review-items" class="space-y-3 text-[13px]">
                <p class="text-muted">{{ __('common.no_data') }}</p>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-th-border flex items-center justify-between">
            <button type="button" onclick="prevStep(3)" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5m0 0l7 7m-7-7l7-7"/></svg>
                {{ __('common.previous') }}
            </button>
            <div class="flex items-center gap-3">
                <button type="button" class="px-5 py-2.5 rounded-xl text-[13px] font-semibold text-primary bg-page border border-th-border hover:bg-surface-2 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.save_draft') }}
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-[#00d9b5] hover:bg-[#00c9a5] shadow-[0_4px_14px_rgba(0,217,181,0.3)] inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    {{ __('pr.submit_request') }}
                </button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
let currentStep = 1;
const TOTAL = 4;

function setStep(step) {
    document.querySelectorAll('[data-step-panel]').forEach(p => p.classList.toggle('hidden', +p.dataset.stepPanel !== step));

    for (let i = 1; i <= TOTAL; i++) {
        const c = document.querySelector(`[data-step-circle="${i}"]`);
        const l = document.querySelector(`[data-step-label="${i}"]`);
        if (!c) continue;
        c.className = 'w-12 h-12 rounded-full flex items-center justify-center transition-all';
        if (i < step) {
            c.className += ' bg-[#00d9b5] text-white';
            c.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>';
            l.className = 'mt-3 text-[12px] font-semibold text-primary';
        } else if (i === step) {
            c.className += ' bg-accent text-white shadow-[0_0_0_4px_rgba(79,124,255,0.15)]';
            l.className = 'mt-3 text-[12px] font-semibold text-primary';
        } else {
            c.className += ' bg-surface-2 border border-th-border text-muted';
            l.className = 'mt-3 text-[12px] font-semibold text-muted';
        }
    }
    for (let i = 1; i < TOTAL; i++) {
        const line = document.querySelector(`[data-step-line="${i}"]`);
        if (!line) continue;
        line.style.width = (i < step) ? '100%' : '0';
        line.style.background = (i < step) ? '#00d9b5' : '#4f7cff';
    }
    currentStep = step;
    document.getElementById('pr-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function nextStep(n) {
    if (n === 4) populateReview();
    setStep(n);
}
function prevStep(n) { setStep(n); }

function populateReview() {
    const form = document.getElementById('pr-form');
    const fd = new FormData(form);

    document.getElementById('review-title').textContent = fd.get('title') || '—';

    const catSelect = form.querySelector('select[name="category_id"]');
    document.getElementById('review-category').textContent =
        (catSelect && catSelect.options[catSelect.selectedIndex])
            ? catSelect.options[catSelect.selectedIndex].text
            : '—';

    const currency = fd.get('currency') || 'AED';
    const budget = fd.get('budget');
    document.getElementById('review-budget').textContent = budget ? `${currency} ${Number(budget).toLocaleString()}` : '—';

    document.getElementById('review-delivery').textContent = fd.get('expected_delivery') || '—';

    const addr = fd.get('delivery_address') || '';
    const city = fd.get('delivery_city') || '';
    document.getElementById('review-location').textContent = [addr, city].filter(Boolean).join(', ') || '—';

    // Items: scan items[i][name|qty|unit]
    const items = [];
    let i = 0;
    while (true) {
        const name = fd.get(`items[${i}][name]`);
        if (name === null) break;
        if (name) {
            items.push({
                name,
                qty: fd.get(`items[${i}][qty]`) || '0',
                unit: fd.get(`items[${i}][unit]`) || '',
            });
        }
        i++;
    }
    const container = document.getElementById('review-items');
    if (items.length === 0) {
        container.innerHTML = `<p class="text-muted">{{ __('common.no_data') }}</p>`;
    } else {
        container.innerHTML = items.map((it, idx) =>
            `<div><p class="font-semibold text-primary">${idx + 1}. ${escapeHtml(it.name)}</p><p class="text-muted">{{ __('pr.quantity') }}: ${escapeHtml(it.qty)} ${escapeHtml(it.unit)}</p></div>`
        ).join('');
    }
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
}

function addItem() {
    const container = document.getElementById('items-container');
    const idx = container.children.length;
    const baseLabel = container.querySelector('p[class*="font-mono"]')?.textContent || '#1';
    let html = container.children[0].outerHTML
        .replace(baseLabel, baseLabel.replace(/\d+/, idx + 1))
        .replace(/items\[0\]/g, `items[${idx}]`);
    container.insertAdjacentHTML('beforeend', '<div class="mt-4">' + html + '</div>');
}

// Form submits naturally — server-side validation handles errors.
</script>
@endpush

@endsection
