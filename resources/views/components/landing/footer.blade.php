<footer class="relative bg-page text-muted transition-colors duration-300 overflow-hidden">
    {{-- Subtle gradient divider above the footer --}}
    <div class="h-px w-full" style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 20%, var(--c-accent) 50%, var(--c-border) 80%, transparent 100%);"></div>

    <div class="max-w-[1280px] mx-auto px-6 lg:px-10 pt-20 pb-8 relative">

        {{-- Background decorative glow --}}
        <div class="absolute -bottom-[200px] -left-[100px] w-[500px] h-[500px] rounded-full opacity-[0.03] pointer-events-none" style="background: radial-gradient(circle, var(--c-accent) 0%, transparent 70%);"></div>

        {{-- 4-column grid matching the Figma layout --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-10 lg:gap-12 mb-16">

            {{-- Brand column — heading + tagline + "Join the future" pill --}}
            <div>
                <h3 class="font-display text-[18px] font-bold tracking-[-0.014em] text-primary mb-4">Trilink</h3>
                <p class="text-[14px] leading-[1.7] tracking-[0.005em] mb-6 max-w-[220px]">
                    The Complete Digital Trade &amp; Procurement Ecosystem
                </p>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center rounded-full border border-th-border bg-surface-2 px-5 py-2.5 text-[13px] font-semibold tracking-[-0.011em] text-primary transition-colors hover:bg-elevated">
                    Join the future
                </a>
            </div>

            {{-- Products --}}
            <div>
                <h4 class="mb-5 text-[14px] font-semibold tracking-[-0.011em] text-primary">Products</h4>
                <ul class="space-y-3">
                    <li><a href="#ecosystem" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Service Providers</a></li>
                    <li><a href="#ecosystem" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Logistics Providers</a></li>
                    <li><a href="#ecosystem" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Buyers</a></li>
                    <li><a href="#ecosystem" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Suppliers</a></li>
                    <li><a href="#ecosystem" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Customs Clearance</a></li>
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="mb-5 text-[14px] font-semibold tracking-[-0.011em] text-primary">Company</h4>
                <ul class="space-y-3">
                    <li><a href="#hero" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Home</a></li>
                    <li><a href="#trust" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">About</a></li>
                    <li><a href="#features" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Features</a></li>
                    <li><a href="#how-it-works" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">How it works</a></li>
                    <li><a href="#cta" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Contact</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="mb-5 text-[14px] font-semibold tracking-[-0.011em] text-primary">Legal</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Privacy</a></li>
                    <li><a href="#" class="text-[14px] tracking-[0.005em] text-muted transition-colors hover:text-accent">Terms</a></li>
                </ul>
            </div>
        </div>

        {{-- Large TriLink logo with concentric rings (matches the Figma's
             oversized brand mark below the columns) --}}
        <div class="relative mb-12 inline-block">
            <div class="pointer-events-none absolute -inset-12">
                <div class="absolute inset-0 rounded-full border border-th-border dark:border-white/[0.08]"></div>
                <div class="absolute inset-6 rounded-full border border-th-border dark:border-white/[0.06]"></div>
                <div class="absolute inset-12 rounded-full border border-th-border dark:border-white/[0.05]"></div>
            </div>
            <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading"
                 class="relative h-20 w-auto brightness-0 dark:brightness-100" />
        </div>

        {{-- Bottom bar: copyright centered + social icons right --}}
        <div class="pt-7 border-t border-th-border flex flex-col sm:flex-row items-center justify-between gap-5">
            <span class="hidden sm:block w-20"></span>
            <p class="order-2 text-center text-[13px] text-faint sm:order-none">
                &copy; {{ date('Y') }} TriLink Platform. All rights reserved.
            </p>

            <div class="flex items-center gap-1">
                @php
                $socials = [
                    ['label' => 'Twitter', 'href' => '#', 'svg' => '<path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>'],
                    ['label' => 'Substack', 'href' => '#', 'svg' => '<path d="M22.539 8.242H1.461V5.406h21.078v2.836zM1.461 10.812V24L12 18.11 22.539 24V10.812H1.461zM22.539 0H1.461v2.836h21.078V0z"/>'],
                    ['label' => 'Discord', 'href' => '#', 'svg' => '<path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.331c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>'],
                    ['label' => 'Instagram', 'href' => '#', 'svg' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>'],
                ];
                @endphp

                @foreach($socials as $s)
                <a href="{{ $s['href'] }}"
                   class="flex h-9 w-9 items-center justify-center rounded-lg text-muted transition-all hover:bg-accent/5 hover:text-accent"
                   title="{{ $s['label'] }}">
                    <svg class="w-[17px] h-[17px]" fill="currentColor" viewBox="0 0 24 24">{!! $s['svg'] !!}</svg>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
