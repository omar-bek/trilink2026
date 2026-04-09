<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Authenticated app pages: keep them out of search engine indexes. --}}
    <meta name="robots" content="noindex, nofollow">
    @include('partials.seo')
    <link rel="preconnect" href="https://fonts.bunny.net">
    @if(app()->getLocale() === 'ar')
        <link href="https://fonts.bunny.net/css?family=cairo:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @else
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @endif
    <script>
    (function(){
        const t = localStorage.getItem('theme');
        if (t === 'light') document.documentElement.classList.remove('dark');
    })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        @if(app()->getLocale() === 'ar')
        body { font-family: 'Cairo', system-ui, sans-serif; }
        @endif
    </style>
</head>
<body class="bg-page text-primary antialiased min-h-screen">

    @php
        // Admin pages get a topbar-less layout — they have their own _tabs
        // navigation and full-bleed content, so the topbar is just visual
        // noise there. Detected via the active key the page passes in
        // (admin overview = 'admin', sub-pages = 'admin-*').
        $activeKey  = $active ?? 'dashboard';
        $isAdminPage = $activeKey === 'admin' || str_starts_with($activeKey, 'admin-');
    @endphp

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <x-dashboard.sidebar :active="$activeKey" />

        {{-- Main area --}}
        <div class="flex-1 flex flex-col min-w-0 lg:ms-[300px]">
            @if($isAdminPage)
                {{-- Admin layout — no topbar. A small floating button on
                     small screens is the only trigger left to open the
                     sidebar drawer on mobile. --}}
                <button type="button"
                        onclick="toggleSidebar()"
                        aria-label="{{ __('common.toggle_menu') }}"
                        aria-controls="sidebar"
                        class="lg:hidden fixed top-4 start-4 z-30 w-11 h-11 rounded-[12px] border border-th-border bg-surface flex items-center justify-center text-muted hover:text-primary shadow-[0_4px_14px_rgba(0,0,0,0.25)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <main class="flex-1 px-6 lg:px-10 py-8 lg:pt-8 pt-20">
                    <x-dashboard.flash-messages />
                    @yield('content')
                </main>
            @else
                <x-dashboard.topbar />

                <main class="flex-1 px-6 lg:px-10 py-8">
                    <x-dashboard.flash-messages />
                    @yield('content')
                </main>
            @endif
        </div>
    </div>

    {{-- Floating WhatsApp button --}}
    @php
        $waNumber = config('app.whatsapp_number', '971500000000');
        $waUrl    = 'https://wa.me/' . preg_replace('/\D/', '', $waNumber);
    @endphp
    <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
       aria-label="{{ __('common.contact_whatsapp') ?? 'Contact us on WhatsApp' }}"
       class="fixed bottom-6 end-6 z-50 w-14 h-14 rounded-full bg-[#25D366] hover:bg-[#128C7E] text-white flex items-center justify-center shadow-[0_8px_24px_rgba(37,211,102,0.4)] transition-all hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366] focus-visible:ring-offset-2">
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
        </svg>
    </a>

    {{-- Phase 2 (UAE Compliance Roadmap) — PDPL cookie banner. Visible
         globally until the user records consent (or essential-only). --}}
    <x-privacy.cookie-banner />

    @stack('scripts')
    @livewireScripts
</body>
</html>
