@php
    /**
     * PDPL cookie consent banner. Renders globally on every page until
     * the visitor explicitly accepts or rejects analytics cookies.
     *
     * Visibility logic:
     *   - Logged-in users with an active `cookies_essential` consent → hidden
     *   - Anonymous visitors with `cookie_consent_recorded` in session → hidden
     *   - Everyone else → visible
     *
     * The banner sits at z-40 to stay BELOW the WhatsApp button (z-50)
     * but above all dashboard content.
     */
    use App\Models\Consent;

    $hide = false;
    if (auth()->check()) {
        $hide = \App\Models\Consent::query()
            ->where('user_id', auth()->id())
            ->where('consent_type', Consent::TYPE_COOKIES_ESSENTIAL)
            ->whereNotNull('granted_at')
            ->whereNull('withdrawn_at')
            ->exists();
    } else {
        $hide = (bool) session('cookie_consent_recorded');
    }
@endphp

@if(!$hide)
<div id="pdpl-cookie-banner"
     class="fixed bottom-4 left-4 right-4 lg:left-auto lg:right-6 lg:max-w-[420px] z-40
            bg-surface border border-th-border rounded-2xl shadow-2xl p-5">
    <div class="flex items-start gap-3 mb-4">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center">
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-[14px] font-bold text-primary leading-snug">{{ __('privacy.cookie_banner_title') }}</h3>
            <p class="text-[12px] text-muted mt-1 leading-relaxed">
                {{ __('privacy.cookie_banner_body') }}
                <a href="{{ route('public.privacy') }}" class="text-accent hover:underline">{{ __('privacy.read_policy') }}</a>
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('public.privacy.cookies') }}" class="flex items-center gap-2">
        @csrf
        <input type="hidden" name="analytics" value="0" id="pdpl-analytics-flag">

        <button type="button" id="pdpl-essential-only"
                class="flex-1 h-10 rounded-xl text-[12px] font-semibold text-primary
                       bg-page border border-th-border hover:bg-surface-2 transition-colors">
            {{ __('privacy.essential_only') }}
        </button>
        <button type="button" id="pdpl-accept-all"
                class="flex-1 h-10 rounded-xl text-[12px] font-semibold text-white
                       bg-accent hover:bg-accent-h transition-colors
                       shadow-[0_4px_14px_rgba(79,124,255,0.25)]">
            {{ __('privacy.accept_all') }}
        </button>
    </form>
</div>

<script>
(function () {
    const banner = document.getElementById('pdpl-cookie-banner');
    if (!banner) return;
    const form = banner.querySelector('form');
    const flag = document.getElementById('pdpl-analytics-flag');

    document.getElementById('pdpl-essential-only')?.addEventListener('click', () => {
        flag.value = '0';
        form.submit();
    });
    document.getElementById('pdpl-accept-all')?.addEventListener('click', () => {
        flag.value = '1';
        form.submit();
    });
})();
</script>
@endif
