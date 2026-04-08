<nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-xl border-b border-th-border/50 transition-colors duration-300" style="background: var(--c-nav-bg);">
    <div class="max-w-[1280px] mx-auto px-6 lg:px-10 h-[72px] flex items-center justify-between">

        {{-- Logo --}}
        <a href="/" class="flex items-center">
            {{-- The source PNG is white-on-transparent. Light mode needs `brightness-0`
                 to invert it to a solid silhouette so it stays visible on the light page;
                 dark mode resets to the original white. --}}
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" class="h-12 w-auto opacity-95 brightness-0 dark:brightness-100" />
        </a>

        {{-- Center Nav (pill shape) --}}
        <div class="hidden md:flex items-center rounded-full px-2 py-1.5 border border-th-border/60 transition-colors" style="background: var(--c-nav-pill);">
            <a href="{{ route('home') }}#hero" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] {{ request()->routeIs('home') ? 'text-primary bg-page/60' : 'text-muted hover:text-primary' }} rounded-full transition-colors">{{ __('landing_nav.home') }}</a>
            <a href="{{ route('home') }}#features" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] text-muted hover:text-primary transition-colors">{{ __('landing_nav.features') }}</a>
            <a href="{{ route('home') }}#how-it-works" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] text-muted hover:text-primary transition-colors">{{ __('landing_nav.how_it_works') }}</a>
            <a href="{{ route('public.demo') }}" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] {{ request()->routeIs('public.demo') ? 'text-primary bg-page/60' : 'text-muted hover:text-primary' }} rounded-full transition-colors inline-flex items-center gap-1.5">
                {{ __('landing_nav.demo') }}
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase rounded-full bg-accent/15 text-accent border border-accent/25">{{ __('landing_nav.new_badge') }}</span>
            </a>
            <a href="{{ route('home') }}#trust" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] text-muted hover:text-primary transition-colors">{{ __('landing_nav.about') }}</a>
            <a href="{{ route('home') }}#cta" class="px-5 py-2 text-[13px] font-medium tracking-[-0.011em] text-muted hover:text-primary transition-colors">{{ __('landing_nav.contact') }}</a>
        </div>

        {{-- Right --}}
        <div class="flex items-center gap-3">
            {{-- Language switcher (EN / AR) --}}
            @php $currentLocale = app()->getLocale(); @endphp
            <div class="hidden sm:flex items-center rounded-full border border-th-border p-1 bg-surface-2/40">
                <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit"
                            class="px-3 py-1 text-[12px] font-semibold tracking-[-0.011em] rounded-full transition-colors {{ $currentLocale === 'en' ? 'bg-accent text-white' : 'text-muted hover:text-primary' }}">
                        EN
                    </button>
                </form>
                <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">
                    @csrf
                    <input type="hidden" name="locale" value="ar">
                    <button type="submit"
                            class="px-3 py-1 text-[12px] font-semibold tracking-[-0.011em] rounded-full transition-colors {{ $currentLocale === 'ar' ? 'bg-accent text-white' : 'text-muted hover:text-primary' }}">
                        AR
                    </button>
                </form>
            </div>

            {{-- Theme toggle --}}
            <button id="theme-toggle" class="w-10 h-10 rounded-full border border-th-border flex items-center justify-center text-muted hover:text-primary transition-colors cursor-pointer">
                {{-- Sun icon (shown in dark → click to go light) --}}
                <svg class="w-[18px] h-[18px] hidden dark:block" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                {{-- Moon icon (shown in light → click to go dark) --}}
                <svg class="w-[18px] h-[18px] block dark:hidden" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
            </button>
            @auth
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full border border-th-border text-primary hover:bg-surface-2 transition-colors">{{ __('landing_nav.dashboard') }}</a>
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:inline-flex">
                    @csrf
                    <button type="submit" class="text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full bg-surface-2 border border-th-border text-primary hover:bg-elevated transition-colors">{{ __('landing_nav.logout') }}</button>
                </form>
            @else
                <a href="{{ route('register') }}" class="hidden sm:inline-flex text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full border border-th-border text-primary hover:bg-surface-2 transition-colors">{{ __('landing_nav.signup') }}</a>
                <a href="{{ route('login') }}" class="hidden sm:inline-flex text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full bg-surface-2 border border-th-border text-primary hover:bg-elevated transition-colors">{{ __('landing_nav.login') }}</a>
            @endauth

            {{-- Mobile menu --}}
            <button id="mobile-menu-btn" class="md:hidden w-10 h-10 rounded-full border border-th-border flex items-center justify-center text-muted">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div id="mobile-menu" class="hidden md:hidden border-t border-th-border bg-page/95 backdrop-blur-xl">
        <div class="px-6 py-5 flex flex-col gap-1">
            <a href="{{ route('home') }}#hero" class="px-4 py-2.5 text-[14px] {{ request()->routeIs('home') ? 'text-primary bg-surface-2/50' : 'text-muted' }} rounded-lg">{{ __('landing_nav.home') }}</a>
            <a href="{{ route('home') }}#features" class="px-4 py-2.5 text-[14px] text-muted">{{ __('landing_nav.features') }}</a>
            <a href="{{ route('home') }}#how-it-works" class="px-4 py-2.5 text-[14px] text-muted">{{ __('landing_nav.how_it_works') }}</a>
            <a href="{{ route('public.demo') }}" class="px-4 py-2.5 text-[14px] {{ request()->routeIs('public.demo') ? 'text-primary bg-surface-2/50' : 'text-muted' }} rounded-lg flex items-center justify-between">
                {{ __('landing_nav.demo') }}
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase rounded-full bg-accent/15 text-accent border border-accent/25">{{ __('landing_nav.new_badge') }}</span>
            </a>
            <a href="{{ route('home') }}#trust" class="px-4 py-2.5 text-[14px] text-muted">{{ __('landing_nav.about') }}</a>
            <a href="{{ route('home') }}#cta" class="px-4 py-2.5 text-[14px] text-muted">{{ __('landing_nav.contact') }}</a>

            {{-- Mobile language switcher --}}
            <div class="flex items-center gap-2 pt-3 border-t border-th-border mt-2">
                <span class="text-[12px] uppercase tracking-[0.14em] font-semibold text-muted">{{ __('landing_nav.language') }}</span>
                <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit" class="px-3 py-1 text-[12px] font-semibold rounded-full transition-colors {{ $currentLocale === 'en' ? 'bg-accent text-white' : 'text-muted border border-th-border' }}">EN</button>
                </form>
                <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex">
                    @csrf
                    <input type="hidden" name="locale" value="ar">
                    <button type="submit" class="px-3 py-1 text-[12px] font-semibold rounded-full transition-colors {{ $currentLocale === 'ar' ? 'bg-accent text-white' : 'text-muted border border-th-border' }}">AR</button>
                </form>
            </div>

            <div class="flex gap-3 pt-3 border-t border-th-border mt-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full border border-th-border text-primary">{{ __('landing_nav.dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full bg-surface-2 text-primary">{{ __('landing_nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('register') }}" class="text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full border border-th-border text-primary">{{ __('landing_nav.signup') }}</a>
                    <a href="{{ route('login') }}" class="text-[13px] font-medium tracking-[-0.011em] px-5 py-2.5 rounded-full bg-surface-2 text-primary">{{ __('landing_nav.login') }}</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

@push('scripts')
<script>
document.getElementById('theme-toggle').addEventListener('click', function() {
    const html = document.documentElement;
    const isDark = html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
});
document.getElementById('mobile-menu-btn').addEventListener('click', function() {
    document.getElementById('mobile-menu').classList.toggle('hidden');
});
</script>
@endpush
