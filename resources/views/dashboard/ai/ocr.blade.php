@extends('layouts.dashboard', ['active' => 'ai-ocr'])
@section('title', __('ai.ocr_title'))

@section('content')

<x-dashboard.page-header :title="__('ai.ocr_title')" :subtitle="__('ai.ocr_subtitle')" />

<div x-data="ocrUploader()" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-surface border border-th-border rounded-2xl p-6">
        <h3 class="text-[15px] font-bold text-primary mb-4">{{ __('ai.ocr_upload') }}</h3>

        <form @submit.prevent="upload" class="space-y-4">
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('ai.document_type') }}</label>
                <select x-model="hintType" class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[13px] text-primary">
                    <option value="">{{ __('ai.auto_detect') }}</option>
                    <option value="invoice">{{ __('ai.type_invoice') }}</option>
                    <option value="bill_of_lading">{{ __('ai.type_bl') }}</option>
                    <option value="packing_list">{{ __('ai.type_packing') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] text-muted uppercase tracking-wider mb-1.5">{{ __('ai.file') }}</label>
                <input type="file" @change="file = $event.target.files[0]" accept=".pdf,.png,.jpg,.jpeg,.webp"
                       class="w-full bg-surface-2 border border-th-border rounded-lg px-3 py-2 text-[12px] text-primary" />
                <p class="text-[10px] text-muted mt-1">{{ __('ai.ocr_file_hint') }}</p>
            </div>
            <button type="submit" :disabled="!file || loading"
                    class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-accent text-white text-[13px] font-bold hover:bg-accent-h transition-all shadow-[0_10px_30px_-10px_rgba(79,124,255,0.55)] disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.091z"/></svg>
                <span x-show="!loading">{{ __('ai.extract_fields') }}</span>
                <span x-show="loading">{{ __('common.processing') }}…</span>
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-surface border border-th-border rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-bold text-primary">{{ __('ai.extracted_fields') }}</h3>
            <span x-show="result" x-text="result?.source === 'claude' ? '{{ __('ai.source_claude') }}' : '{{ __('ai.source_mock') }}'"
                  class="text-[10px] font-bold rounded-full px-2 py-0.5 bg-accent/10 border border-accent/20 text-accent"></span>
        </div>

        <template x-if="!result">
            <p class="text-[12px] text-muted italic">{{ __('ai.ocr_empty') }}</p>
        </template>

        <template x-if="result">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-[12px]">
                    <div class="bg-page border border-th-border rounded-lg p-3">
                        <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('ai.document_type') }}</p>
                        <p class="font-semibold text-primary" x-text="result.document_type"></p>
                    </div>
                    <div class="bg-page border border-th-border rounded-lg p-3">
                        <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('ai.document_number') }}</p>
                        <p class="font-mono text-primary" x-text="result.fields?.document_number ?? '—'"></p>
                    </div>
                    <div class="bg-page border border-th-border rounded-lg p-3">
                        <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('ai.date') }}</p>
                        <p class="font-semibold text-primary" x-text="result.fields?.date ?? '—'"></p>
                    </div>
                    <div class="bg-page border border-th-border rounded-lg p-3">
                        <p class="text-[10px] text-muted uppercase tracking-wider">{{ __('ai.total') }}</p>
                        <p class="font-bold text-[#00d9b5]"><span x-text="result.fields?.currency ?? ''"></span> <span x-text="result.fields?.total_amount ?? '—'"></span></p>
                    </div>
                </div>

                <template x-if="result.fields?.line_items?.length">
                    <div>
                        <p class="text-[10px] text-muted uppercase tracking-wider mb-2">{{ __('ai.line_items') }}</p>
                        <div class="bg-page border border-th-border rounded-lg overflow-hidden">
                            <table class="w-full text-[11px]">
                                <thead class="bg-surface-2 text-muted">
                                    <tr>
                                        <th class="px-3 py-2 text-start">{{ __('ai.description') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('ai.qty') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('ai.unit_price') }}</th>
                                        <th class="px-3 py-2 text-end">{{ __('ai.line_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-th-border">
                                    <template x-for="line in result.fields.line_items">
                                        <tr>
                                            <td class="px-3 py-2 text-primary" x-text="line.description"></td>
                                            <td class="px-3 py-2 text-end" x-text="line.qty"></td>
                                            <td class="px-3 py-2 text-end" x-text="line.unit_price"></td>
                                            <td class="px-3 py-2 text-end font-semibold text-[#00d9b5]" x-text="line.total"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
function ocrUploader() {
    return {
        file: null,
        hintType: '',
        loading: false,
        result: null,
        async upload() {
            if (!this.file) return;
            this.loading = true;
            this.result = null;
            const fd = new FormData();
            fd.append('document', this.file);
            if (this.hintType) fd.append('hint_type', this.hintType);
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
                const res = await fetch('{{ route('dashboard.ai.ocr.extract') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                this.result = await res.json();
            } catch (e) {
                alert('{{ __('ai.ocr_error') }}');
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush

@endsection
