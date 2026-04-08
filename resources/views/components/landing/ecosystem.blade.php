<section id="ecosystem" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto text-center">
        <h2 class="h-display text-gradient mb-4 reveal">One platform, entire ecosystem</h2>
        <p class="t-lead max-w-[600px] mx-auto reveal reveal-delay-1">TriLink connects all stakeholders in the trade lifecycle, creating a seamless digital ecosystem for global commerce.</p>

        {{-- Ecosystem grid: 3 cols, center spans 2 rows --}}
        <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-5 max-w-[1100px] mx-auto">

            {{-- Service Providers (top-left) — icon tile #2D1B36 --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all group reveal">
                <div class="w-[52px] h-[52px] rounded-[14px] flex items-center justify-center mb-5 bg-fuchsia-100 border border-fuchsia-200/70 dark:bg-[#2D1B36] dark:border-transparent">
                    {{-- Lucide: headphones --}}
                    <svg class="w-6 h-6 text-fuchsia-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-7a9 9 0 0 1 18 0v7a1 1 0 0 1-1 1h-2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>
                </div>
                <h3 class="h-card mb-1">Service Providers</h3>
                <p class="t-body">Additional trade support services</p>
            </div>

            {{-- Buyers (center, 2-row span) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-10 flex flex-col items-center justify-center min-h-[220px] sm:row-span-2 relative overflow-hidden hover:border-accent/30 hover:shadow-[0_24px_70px_-20px_rgba(59,126,255,0.30)] transition-all reveal reveal-delay-1">
                {{-- Concentric circles — adapt across themes --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-50">
                    <div class="w-[320px] h-[320px] rounded-full border border-slate-200 dark:border-white/[0.08]"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-40">
                    <div class="w-[230px] h-[230px] rounded-full border border-slate-200 dark:border-white/[0.07]"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30">
                    <div class="w-[140px] h-[140px] rounded-full border border-slate-200 dark:border-white/[0.06]"></div>
                </div>

                <div class="relative z-10">
                    <div class="w-[64px] h-[64px] rounded-[16px] flex items-center justify-center mb-5 mx-auto bg-blue-100 border border-blue-200/70 dark:bg-[#1E293B] dark:border-transparent">
                        {{-- Lucide: building (Buyers) --}}
                        <svg class="w-7 h-7 text-blue-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                    </div>
                    <h3 class="font-display text-[26px] font-bold text-primary tracking-[-0.022em] leading-[1.15] mb-2">Buyers</h3>
                    <p class="t-lead text-[14px] leading-[1.6]">Companies seeking products<br>and services</p>
                </div>
            </div>

            {{-- Suppliers (top-right) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all reveal reveal-delay-2">
                <div class="w-[52px] h-[52px] rounded-[14px] flex items-center justify-center mb-5 bg-fuchsia-100 border border-fuchsia-200/70 dark:bg-[#2D1B36] dark:border-transparent">
                    {{-- Lucide: factory (suppliers) --}}
                    <svg class="w-6 h-6 text-fuchsia-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/></svg>
                </div>
                <h3 class="h-card mb-1">Suppliers</h3>
                <p class="t-body">Product and service providers</p>
            </div>

            {{-- Logistics Providers (bottom-left) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all reveal">
                <div class="w-[52px] h-[52px] rounded-[14px] flex items-center justify-center mb-5 bg-emerald-100 border border-emerald-200/70 dark:bg-[#062D24] dark:border-transparent">
                    {{-- Lucide: truck --}}
                    <svg class="w-6 h-6 text-emerald-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                </div>
                <h3 class="h-card mb-1">Logistics Providers</h3>
                <p class="t-body">Shipping and freight companies</p>
            </div>

            {{-- Customs Clearance (bottom-right) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.22)] transition-all reveal reveal-delay-1">
                <div class="w-[52px] h-[52px] rounded-[14px] flex items-center justify-center mb-5 bg-amber-100 border border-amber-200/70 dark:bg-[#3F2611] dark:border-transparent">
                    {{-- Lucide: file-check (customs) --}}
                    <svg class="w-6 h-6 text-amber-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>
                </div>
                <h3 class="h-card mb-1">Customs Clearance</h3>
                <p class="t-body">Customs and compliance experts</p>
            </div>
        </div>
    </div>
</section>
