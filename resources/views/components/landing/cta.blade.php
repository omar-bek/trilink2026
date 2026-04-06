<section id="cta" class="py-20 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto reveal">
        <div class="relative overflow-hidden rounded-[28px] px-10 sm:px-16 lg:px-20 py-16 sm:py-20" style="background: linear-gradient(135deg, #152046 0%, #1A3070 30%, #1E3A8A 50%, #162B66 100%);">
            {{-- Glow orbs --}}
            <div class="absolute top-0 right-[20%] w-[400px] h-[400px] bg-[#3B82F6]/6 rounded-full blur-[100px]"></div>
            <div class="absolute bottom-0 left-[5%] w-[300px] h-[300px] bg-[#1D4ED8]/8 rounded-full blur-[80px]"></div>
            {{-- Subtle stars in CTA --}}
            <div class="absolute top-[15%] left-[20%] w-1 h-1 bg-white/15 rounded-full"></div>
            <div class="absolute top-[30%] right-[40%] w-1 h-1 bg-white/10 rounded-full"></div>
            <div class="absolute bottom-[25%] left-[35%] w-1 h-1 bg-white/8 rounded-full"></div>

            <div class="relative grid lg:grid-cols-[1fr_auto] gap-10 items-center">
                {{-- Left content --}}
                <div>
                    <h2 class="text-[34px] sm:text-[44px] font-bold leading-[1.1] text-white mb-5">Ready to transform your<br>trade operations?</h2>
                    <p class="text-white/50 text-[16px] leading-[1.7] max-w-[440px] mb-9">Join thousands of companies using TriLink Platform to streamline procurement, connect with global partners, and accelerate trade workflows.</p>
                    <a href="#" class="inline-flex items-center gap-3 px-8 py-4 bg-[#4B83F0] hover:bg-[#3B6FE0] text-white rounded-full text-[15px] font-semibold transition-all shadow-[0_6px_32px_rgba(75,131,240,0.35)]">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        Start Now
                    </a>
                </div>

                {{-- Right logo with multi-color glow --}}
                <div class="hidden lg:flex justify-center items-center">
                    <div class="relative w-[220px] h-[220px] flex items-center justify-center">
                        {{-- Multi-color glow layers --}}
                        <div class="absolute w-[200px] h-[200px] rounded-full blur-[60px]" style="background: radial-gradient(circle, rgba(96,165,250,0.2) 0%, rgba(59,130,246,0.1) 40%, transparent 70%);"></div>
                        <div class="absolute w-[160px] h-[160px] rounded-full blur-[40px]" style="background: radial-gradient(circle, rgba(147,197,253,0.15) 0%, rgba(96,165,250,0.08) 50%, transparent 70%);"></div>
                        <div class="absolute w-[120px] h-[120px] rounded-full blur-[30px] translate-x-2 translate-y-2" style="background: radial-gradient(circle, rgba(250,204,21,0.08) 0%, transparent 60%);"></div>
                        <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" class="w-[180px] h-auto relative opacity-90" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
