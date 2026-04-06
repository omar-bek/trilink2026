<section id="ecosystem" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto text-center">
        <h2 class="text-[42px] sm:text-[52px] font-bold text-gradient mb-4 reveal">One platform entire ecosystem</h2>
        <p class="text-muted text-[16px] max-w-[600px] mx-auto reveal reveal-delay-1">TriLink connects all stakeholders in the trade lifecycle, creating a seamless digital ecosystem for global commerce.</p>

        {{-- Ecosystem grid: 3 cols, center spans 2 rows --}}
        <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-5 max-w-[1100px] mx-auto">

            {{-- Service Providers (top-left) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/20 transition-all group reveal"
                 style="background: linear-gradient(160deg, var(--c-surface) 60%, rgba(155,142,196,0.06) 100%);">
                <div class="w-[52px] h-[52px] rounded-[14px] bg-[#9B8EC4]/10 border border-[#9B8EC4]/20 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-[#9B8EC4]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/></svg>
                </div>
                <h3 class="text-[16px] font-bold text-primary mb-1">Service Providers</h3>
                <p class="text-[13px] text-muted">Additional trade support services</p>
            </div>

            {{-- Buyers (center, 2-row span) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-10 flex flex-col items-center justify-center min-h-[220px] sm:row-span-2 relative overflow-hidden hover:border-accent/20 transition-all reveal reveal-delay-1">
                {{-- Concentric circles --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-40">
                    <div class="w-[320px] h-[320px] rounded-full border border-th-border/50"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30">
                    <div class="w-[230px] h-[230px] rounded-full border border-th-border/40"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-20">
                    <div class="w-[140px] h-[140px] rounded-full border border-th-border/30"></div>
                </div>

                <div class="relative z-10">
                    <div class="w-[64px] h-[64px] rounded-[16px] bg-accent/10 border border-accent/20 flex items-center justify-center mb-5 mx-auto shadow-[0_0_30px_rgba(37,99,235,0.08)]">
                        <svg class="w-7 h-7 text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <h3 class="text-[24px] font-bold text-primary mb-2">Buyers</h3>
                    <p class="text-[14px] text-muted">Companies seeking products<br>and services</p>
                </div>
            </div>

            {{-- Suppliers (top-right) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/20 transition-all reveal reveal-delay-2">
                <div class="w-[52px] h-[52px] rounded-[14px] bg-surface-2 border border-th-border flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z"/></svg>
                </div>
                <h3 class="text-[16px] font-bold text-primary mb-1">Suppliers</h3>
                <p class="text-[13px] text-muted">Product and service providers</p>
            </div>

            {{-- Logistics Providers (bottom-left) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/20 transition-all reveal"
                 style="background: linear-gradient(160deg, var(--c-surface) 60%, rgba(155,142,196,0.06) 100%);">
                <div class="w-[52px] h-[52px] rounded-[14px] bg-surface-2 border border-th-border flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-3.379a2.25 2.25 0 01-1.59-.659l-2.122-2.121"/></svg>
                </div>
                <h3 class="text-[16px] font-bold text-primary mb-1">Logistics Providers</h3>
                <p class="text-[13px] text-muted">Shipping and freight companies</p>
            </div>

            {{-- Customs Clearance (bottom-right) --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8 flex flex-col items-center justify-center min-h-[220px] hover:border-accent/20 transition-all reveal reveal-delay-1">
                <div class="w-[52px] h-[52px] rounded-[14px] bg-[#7B4B6B]/10 border border-[#7B4B6B]/20 flex items-center justify-center mb-5">
                    <svg class="w-6 h-6 text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622"/></svg>
                </div>
                <h3 class="text-[16px] font-bold text-primary mb-1">Customs Clearance</h3>
                <p class="text-[13px] text-muted">Customs and compliance experts</p>
            </div>
        </div>
    </div>
</section>
