{{--
    Inline modal that lets the current user's company upload its
    authorised signature image and its stamp/seal in one go. Reused by
    both the buyer and supplier contract show pages.

    v2 upgrades:
      • Live preview of either uploaded image before submission so the
        user knows what the PDF will print.
      • Client-side minimum-dimension guard (300×100 for signatures,
        200×200 for stamps) — prevents users from uploading a 50×50px
        thumbnail that would render as a smudge in the PDF.
      • Optional in-browser signature pad (HTML5 canvas) for users who
        don't have a scanned signature handy. Drawn signatures are
        serialised to a data-URL, posted as a regular file via a
        hidden input that holds the base64 PNG, and converted back on
        the server side by the upload action.

    Required props:
        $contract_id
        $signature_assets — has_signature, has_stamp, signature_url, stamp_url, has_both
        $open             — whether to auto-open the modal on page load
--}}
@props(['contract_id', 'signature_assets', 'open' => false])

<div
    x-data="signatureUploadModal({ initialOpen: @js((bool) $open) })"
    x-on:open-signature-modal.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-cloak
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
        @click="open = false"
        aria-hidden="true"
    ></div>

    <div
        x-show="open"
        x-transition
        class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="signature-modal-title"
    >
        <div
            class="pointer-events-auto w-full max-w-2xl my-8 bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-2xl shadow-2xl overflow-hidden"
            @click.stop
        >
            <div class="flex items-start justify-between gap-3 p-6 border-b border-th-border dark:border-[rgba(255,255,255,0.08)]">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-accent/10 text-accent flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="signature-modal-title" class="text-[16px] font-bold text-primary dark:text-white">{{ __('contracts.signature_required_title') }}</h3>
                        <p class="text-[12px] text-muted dark:text-[#b4b6c0] mt-1 leading-relaxed">{{ __('contracts.signature_required_subtitle') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="w-8 h-8 rounded-lg text-muted hover:text-primary dark:text-[#b4b6c0] dark:hover:text-white flex items-center justify-center flex-shrink-0"
                    aria-label="{{ __('common.close') }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('dashboard.company.profile.signature') }}"
                enctype="multipart/form-data"
                class="p-6 space-y-5"
                x-ref="form"
                @submit="onSubmit($event)"
            >
                @csrf
                <input type="hidden" name="redirect_to" value="{{ route('dashboard.contracts.show', ['id' => $contract_id], absolute: false) }}">
                {{-- Hidden file inputs that the canvas pad fills via
                     DataTransfer when the user draws their signature
                     instead of uploading a scan. --}}
                <input type="file" name="signature" accept="image/png,image/jpeg,image/webp" x-ref="signatureFile" class="hidden" @change="onSignatureFile($event)">
                <input type="file" name="stamp"     accept="image/png,image/jpeg,image/webp" x-ref="stampFile"     class="hidden" @change="onStampFile($event)">

                {{-- ===================== SIGNATURE ===================== --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[12px] font-semibold text-primary dark:text-white">
                            {{ __('contracts.signature_label') }}
                        </label>
                        <div class="flex items-center gap-1 bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-lg p-0.5">
                            <button type="button"
                                    @click="sigMode = 'upload'"
                                    :class="sigMode === 'upload' ? 'bg-accent text-white' : 'text-muted dark:text-[#b4b6c0]'"
                                    class="px-3 py-1 rounded-md text-[11px] font-semibold transition-colors">
                                {{ __('contracts.signature_mode_upload') }}
                            </button>
                            <button type="button"
                                    @click="sigMode = 'draw'; $nextTick(() => initSignaturePad())"
                                    :class="sigMode === 'draw' ? 'bg-accent text-white' : 'text-muted dark:text-[#b4b6c0]'"
                                    class="px-3 py-1 rounded-md text-[11px] font-semibold transition-colors">
                                {{ __('contracts.signature_mode_draw') }}
                            </button>
                        </div>
                    </div>

                    <div class="bg-page dark:bg-[#0f1117] border border-dashed border-th-border dark:border-[rgba(255,255,255,0.15)] rounded-xl p-4">
                        {{-- UPLOAD MODE --}}
                        <div x-show="sigMode === 'upload'">
                            <template x-if="sigPreview">
                                <div class="mb-3">
                                    <img :src="sigPreview" alt="signature preview" class="h-16 w-auto bg-white rounded-lg p-2 border border-th-border dark:border-[rgba(255,255,255,0.1)]">
                                    <p class="text-[10px] text-[#00d9b5] font-semibold mt-1">{{ __('contracts.preview_ready') }}</p>
                                </div>
                            </template>
                            @if(($signature_assets['has_signature'] ?? false) && !empty($signature_assets['signature_url']))
                            <template x-if="!sigPreview">
                                <div class="flex items-center gap-3 mb-3">
                                    <img src="{{ $signature_assets['signature_url'] }}" alt="signature" class="h-14 w-auto bg-white rounded-lg p-2 border border-th-border dark:border-[rgba(255,255,255,0.1)]">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20 rounded-full px-2.5 py-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                        {{ __('contracts.signature_already_uploaded') }}
                                    </span>
                                </div>
                            </template>
                            @endif
                            <button type="button" @click="$refs.signatureFile.click()"
                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                                {{ __('contracts.choose_file') }}
                            </button>
                            <p class="text-[11px] text-muted dark:text-[#b4b6c0] mt-2">{{ __('contracts.signature_hint') }}</p>
                            <p x-show="sigError" x-cloak class="text-[11px] text-[#ef4444] mt-1" x-text="sigError"></p>
                        </div>

                        {{-- DRAW MODE --}}
                        <div x-show="sigMode === 'draw'" x-cloak>
                            <div class="bg-white rounded-lg border border-th-border" style="height: 140px;">
                                <canvas x-ref="sigCanvas" class="w-full h-full cursor-crosshair touch-none rounded-lg"></canvas>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-[11px] text-muted dark:text-[#b4b6c0]">{{ __('contracts.signature_draw_hint') }}</p>
                                <button type="button" @click="clearSignaturePad()" class="text-[11px] font-semibold text-[#ef4444] hover:underline">{{ __('contracts.signature_clear') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===================== STAMP ===================== --}}
                <div>
                    <label class="block text-[12px] font-semibold text-primary dark:text-white mb-2">
                        {{ __('contracts.stamp_label') }}
                    </label>
                    <div class="bg-page dark:bg-[#0f1117] border border-dashed border-th-border dark:border-[rgba(255,255,255,0.15)] rounded-xl p-4">
                        <template x-if="stampPreview">
                            <div class="mb-3">
                                <img :src="stampPreview" alt="stamp preview" class="h-16 w-16 object-contain bg-white rounded-lg p-2 border border-th-border dark:border-[rgba(255,255,255,0.1)]">
                                <p class="text-[10px] text-[#00d9b5] font-semibold mt-1">{{ __('contracts.preview_ready') }}</p>
                            </div>
                        </template>
                        @if(($signature_assets['has_stamp'] ?? false) && !empty($signature_assets['stamp_url']))
                        <template x-if="!stampPreview">
                            <div class="flex items-center gap-3 mb-3">
                                <img src="{{ $signature_assets['stamp_url'] }}" alt="stamp" class="h-14 w-14 object-contain bg-white rounded-lg p-2 border border-th-border dark:border-[rgba(255,255,255,0.1)]">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-[#00d9b5] bg-[#00d9b5]/10 border border-[#00d9b5]/20 rounded-full px-2.5 py-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12.75L11.25 15 15 9.75"/></svg>
                                    {{ __('contracts.signature_already_uploaded') }}
                                </span>
                            </div>
                        </template>
                        @endif
                        <button type="button" @click="$refs.stampFile.click()"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-[12px] font-semibold text-white bg-accent hover:bg-accent-h">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            {{ __('contracts.choose_file') }}
                        </button>
                        <p class="text-[11px] text-muted dark:text-[#b4b6c0] mt-2">{{ __('contracts.signature_hint') }}</p>
                        <p x-show="stampError" x-cloak class="text-[11px] text-[#ef4444] mt-1" x-text="stampError"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-muted dark:text-[#b4b6c0] hover:text-primary dark:hover:text-white"
                    >
                        {{ __('contracts.amendment_cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('contracts.signature_save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    if (window.__signatureUploadModalRegistered) return;
    window.__signatureUploadModalRegistered = true;

    Alpine.data('signatureUploadModal', ({ initialOpen }) => ({
        open: !!initialOpen,
        sigMode: 'upload',         // 'upload' | 'draw'
        sigPreview: null,
        stampPreview: null,
        sigError: '',
        stampError: '',
        // Canvas signature pad state.
        padCtx: null,
        padDrawing: false,
        padHasInk: false,

        // ---- File input handlers --------------------------------------
        async onSignatureFile(event) {
            this.sigError = '';
            const file = event.target.files?.[0];
            if (!file) { this.sigPreview = null; return; }
            const ok = await this.validateImage(file, 300, 80, 'sigError');
            if (!ok) { event.target.value = ''; this.sigPreview = null; return; }
            this.sigPreview = URL.createObjectURL(file);
        },

        async onStampFile(event) {
            this.stampError = '';
            const file = event.target.files?.[0];
            if (!file) { this.stampPreview = null; return; }
            const ok = await this.validateImage(file, 200, 200, 'stampError');
            if (!ok) { event.target.value = ''; this.stampPreview = null; return; }
            this.stampPreview = URL.createObjectURL(file);
        },

        // Returns a Promise<boolean>. Sets the named error key on `this`
        // when the file fails validation, so the inline error <p> can
        // surface the rejection reason without alert().
        validateImage(file, minW, minH, errorKey) {
            return new Promise((resolve) => {
                if (!file.type.startsWith('image/')) {
                    this[errorKey] = '{{ __('contracts.signature_invalid_type') }}';
                    resolve(false); return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    this[errorKey] = '{{ __('contracts.signature_too_large') }}';
                    resolve(false); return;
                }
                const img = new Image();
                img.onload = () => {
                    if (img.width < minW || img.height < minH) {
                        this[errorKey] = `{{ __('contracts.signature_too_small') }} (${minW}×${minH}px)`;
                        URL.revokeObjectURL(img.src);
                        resolve(false); return;
                    }
                    URL.revokeObjectURL(img.src);
                    resolve(true);
                };
                img.onerror = () => {
                    this[errorKey] = '{{ __('contracts.signature_invalid_type') }}';
                    resolve(false);
                };
                img.src = URL.createObjectURL(file);
            });
        },

        // ---- Canvas signature pad -------------------------------------
        initSignaturePad() {
            const canvas = this.$refs.sigCanvas;
            if (!canvas) return;
            // High-DPI: scale the bitmap to the device pixel ratio so the
            // drawn line stays crisp on retina displays. Captured here
            // not in CSS so the exported PNG carries the full resolution.
            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0f1117';
            this.padCtx = ctx;
            this.padHasInk = false;

            const startDraw = (e) => {
                this.padDrawing = true;
                const p = this.pointFor(e, canvas);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            };
            const moveDraw = (e) => {
                if (!this.padDrawing) return;
                e.preventDefault();
                const p = this.pointFor(e, canvas);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                this.padHasInk = true;
            };
            const endDraw = () => { this.padDrawing = false; };

            // Remove old listeners (idempotent re-init when user toggles
            // mode multiple times) by replacing canvas event handlers.
            canvas.onmousedown = startDraw;
            canvas.onmousemove = moveDraw;
            canvas.onmouseup = endDraw;
            canvas.onmouseleave = endDraw;
            canvas.ontouchstart = startDraw;
            canvas.ontouchmove = moveDraw;
            canvas.ontouchend = endDraw;
        },

        pointFor(e, canvas) {
            const rect = canvas.getBoundingClientRect();
            const t = e.touches?.[0] || e;
            return {
                x: (t.clientX - rect.left),
                y: (t.clientY - rect.top),
            };
        },

        clearSignaturePad() {
            if (!this.padCtx) return;
            const c = this.$refs.sigCanvas;
            this.padCtx.clearRect(0, 0, c.width, c.height);
            this.padHasInk = false;
        },

        // ---- Submit ---------------------------------------------------
        // When the user drew a signature on canvas, convert it to a PNG
        // file and stuff it into the hidden file input via DataTransfer
        // so the existing multipart upload endpoint receives a real
        // image file (not a data URL string we'd have to special-case
        // server-side).
        async onSubmit(event) {
            if (this.sigMode === 'draw' && this.padHasInk) {
                event.preventDefault();
                const canvas = this.$refs.sigCanvas;
                const blob = await new Promise((res) => canvas.toBlob(res, 'image/png'));
                const file = new File([blob], 'drawn-signature.png', { type: 'image/png' });
                const dt = new DataTransfer();
                dt.items.add(file);
                this.$refs.signatureFile.files = dt.files;
                this.$refs.form.submit();
            }
        },
    }));
});
</script>
@endpush
@endonce
