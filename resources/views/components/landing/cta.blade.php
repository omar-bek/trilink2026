<section id="cta" class="py-20 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto reveal">
        <div class="cta-banner relative overflow-hidden rounded-[28px] px-10 py-16 ring-1 ring-slate-200/70 sm:px-16 sm:py-20 dark:ring-white/5">
            {{-- Glow orbs — stronger in dark mode --}}
            <div class="absolute -right-[10%] -top-20 h-[480px] w-[480px] rounded-full opacity-40 blur-[110px] dark:opacity-100" style="background: radial-gradient(circle, rgba(59,126,255,0.30) 0%, transparent 60%);"></div>
            <div class="absolute -bottom-20 left-[-5%] h-[420px] w-[420px] rounded-full opacity-30 blur-[100px] dark:opacity-100" style="background: radial-gradient(circle, rgba(90,148,255,0.20) 0%, transparent 60%);"></div>
            <div class="absolute left-[45%] top-[40%] h-[260px] w-[260px] rounded-full opacity-25 blur-[80px] dark:opacity-100" style="background: radial-gradient(circle, rgba(120,170,255,0.15) 0%, transparent 60%);"></div>
            {{-- Tiny dots — light: slate; dark: stars --}}
            <div class="absolute left-[18%] top-[14%] h-1 w-1 rounded-full bg-slate-400/40 dark:bg-white/25"></div>
            <div class="absolute right-[36%] top-[28%] h-1 w-1 rounded-full bg-slate-400/30 dark:bg-white/15"></div>
            <div class="absolute bottom-[22%] left-[32%] h-1 w-1 rounded-full bg-slate-400/30 dark:bg-white/15"></div>
            <div class="absolute left-[10%] top-[60%] h-0.5 w-0.5 rounded-full bg-slate-400/50 dark:bg-white/30"></div>
            <div class="absolute right-[18%] top-[20%] h-0.5 w-0.5 rounded-full bg-slate-400/40 dark:bg-white/25"></div>

            <div class="relative grid lg:grid-cols-[1fr_auto] gap-10 items-center">
                {{-- Left content --}}
                <div>
                    <h2 class="font-display mb-5 text-[32px] font-bold leading-[1.08] tracking-[-0.028em] text-slate-900 sm:text-[44px] dark:text-white">Ready to transform your<br>trade operations?</h2>
                    <p class="mb-9 max-w-[460px] text-[16px] leading-[1.7] tracking-[0.01em] text-slate-600 dark:text-white/55">Join thousands of companies using TriLink Platform to streamline procurement, connect with global partners, and accelerate trade workflows.</p>
                    <a href="{{ route('register') }}" class="group relative inline-flex items-center gap-3 overflow-hidden rounded-full bg-accent px-7 py-3.5 text-[15px] font-semibold tracking-[-0.011em] text-white shadow-[0_10px_40px_rgba(59,126,255,0.35)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-accent-h hover:shadow-[0_14px_50px_rgba(59,126,255,0.45)] dark:shadow-[0_10px_40px_rgba(59,126,255,0.45)] dark:hover:shadow-[0_14px_50px_rgba(59,126,255,0.6)] shimmer">
                        <svg class="w-4 h-4 rtl:rotate-0 ltr:rotate-180 transition-transform group-hover:-translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                        <span class="relative z-[2]">Start Now</span>
                    </a>
                </div>

                {{-- Right logo with multi-color glow --}}
                <div class="hidden lg:flex justify-center items-center">
                    <div class="relative w-[220px] h-[220px] flex items-center justify-center">
                        {{-- Multi-color glow layers --}}
                        <div class="absolute w-[200px] h-[200px] rounded-full blur-[60px]" style="background: radial-gradient(circle, rgba(96,165,250,0.2) 0%, rgba(59,130,246,0.1) 40%, transparent 70%);"></div>
                        <div class="absolute w-[160px] h-[160px] rounded-full blur-[40px]" style="background: radial-gradient(circle, rgba(147,197,253,0.15) 0%, rgba(96,165,250,0.08) 50%, transparent 70%);"></div>
                        <div class="absolute w-[120px] h-[120px] rounded-full blur-[30px] translate-x-2 translate-y-2" style="background: radial-gradient(circle, rgba(250,204,21,0.08) 0%, transparent 60%);"></div>
                        <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" class="relative h-auto w-[180px] opacity-95 brightness-0 dark:brightness-100 dark:opacity-90" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
