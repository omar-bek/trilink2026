{{--
    Single source of truth for session flash messages on dashboard pages.
    Reads three Laravel session keys and surfaces them as accessible alerts:

      - 'status'  → success (green)
      - 'error'   → destructive (red)
      - 'warning' → caution (amber)

    Each alert has role="alert" for screen readers, an ARIA-labelled
    dismiss button, and a small inline JS that removes the toast on
    click. Auto-dismisses success messages after 6s so the page does
    not stay cluttered. Validation errors (`$errors`) are intentionally
    NOT shown here — those should be rendered next to the field that
    triggered them via @error blocks.

    Usage: drop `<x-dashboard.flash-messages />` near the top of any
    @yield('content') block. The dashboard layout already includes it
    above <main>.
--}}
@if(session('status') || session('error') || session('warning'))
    <div class="mb-6 space-y-3" id="flash-messages">
        @if($status = session('status'))
            <div role="alert"
                 aria-live="polite"
                 data-flash="status"
                 class="flex items-start gap-3 px-4 py-3 rounded-xl border border-[#10B981]/40 bg-[#10B981]/10 text-[#10B981]">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="flex-1 text-[14px] font-medium leading-snug">{{ $status }}</p>
                <button type="button"
                        aria-label="{{ __('common.dismiss') }}"
                        data-dismiss-flash
                        class="text-[#10B981]/70 hover:text-[#10B981] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#10B981] rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if($error = session('error'))
            <div role="alert"
                 aria-live="assertive"
                 data-flash="error"
                 class="flex items-start gap-3 px-4 py-3 rounded-xl border border-[#EF4444]/40 bg-[#EF4444]/10 text-[#EF4444]">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <p class="flex-1 text-[14px] font-medium leading-snug">{{ $error }}</p>
                <button type="button"
                        aria-label="{{ __('common.dismiss') }}"
                        data-dismiss-flash
                        class="text-[#EF4444]/70 hover:text-[#EF4444] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#EF4444] rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if($warning = session('warning'))
            <div role="alert"
                 aria-live="polite"
                 data-flash="warning"
                 class="flex items-start gap-3 px-4 py-3 rounded-xl border border-[#F59E0B]/40 bg-[#F59E0B]/10 text-[#F59E0B]">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 17.25h.007v.008H12v-.008z"/>
                </svg>
                <p class="flex-1 text-[14px] font-medium leading-snug">{{ $warning }}</p>
                <button type="button"
                        aria-label="{{ __('common.dismiss') }}"
                        data-dismiss-flash
                        class="text-[#F59E0B]/70 hover:text-[#F59E0B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F59E0B] rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif
    </div>

    {{-- Auto-dismiss success toasts after 5s. Errors and warnings stay
         visible until the user dismisses them — they may carry actionable
         information the user needs time to read. Manual dismiss (click X)
         also fades out smoothly instead of popping instantly. --}}
    @push('scripts')
    <script>
    (function () {
        function dismissFlash(el) {
            el.style.transition = 'opacity 0.35s ease, transform 0.35s ease, margin 0.35s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            el.style.marginBottom = '-' + el.offsetHeight + 'px';
            el.style.overflow = 'hidden';
            setTimeout(function () { el.remove(); }, 360);
        }
        // Manual dismiss — any flash toast.
        document.querySelectorAll('[data-dismiss-flash]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                dismissFlash(btn.closest('[data-flash]'));
            });
        });
        // Auto-dismiss success after 5s.
        setTimeout(function () {
            document.querySelectorAll('[data-flash="status"]').forEach(dismissFlash);
        }, 5000);
    })();
    </script>
    @endpush
@endif
