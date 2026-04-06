<section id="trust" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            {{-- Left - Logo Card with star dots --}}
            <div class="reveal">
                <div class="bg-surface border border-th-border rounded-2xl p-12 sm:p-16 flex flex-col items-center justify-center min-h-[460px] relative overflow-hidden">
                    {{-- Star dots --}}
                    <div class="absolute top-[12%] left-[10%] w-1.5 h-1.5 bg-primary/10 rounded-full"></div>
                    <div class="absolute top-[18%] right-[15%] w-1 h-1 bg-primary/8 rounded-full"></div>
                    <div class="absolute top-[35%] left-[22%] w-1 h-1 bg-primary/6 rounded-full"></div>
                    <div class="absolute bottom-[25%] left-[18%] w-1.5 h-1.5 bg-primary/8 rounded-full"></div>
                    <div class="absolute bottom-[18%] right-[12%] w-1 h-1 bg-primary/6 rounded-full"></div>
                    <div class="absolute top-[50%] right-[25%] w-1 h-1 bg-primary/5 rounded-full"></div>
                    <div class="absolute bottom-[40%] left-[35%] w-1 h-1 bg-primary/5 rounded-full"></div>

                    {{-- Logo with glow --}}
                    <div class="relative">
                        <div class="absolute inset-0 blur-[70px] bg-accent/20 rounded-full scale-[1.8]"></div>
                        <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" class="w-[240px] h-auto relative dark:brightness-100 brightness-0" />
                    </div>
                </div>
            </div>

            {{-- Right - Content --}}
            <div class="reveal reveal-delay-1">
                <span class="inline-block text-[12px] uppercase tracking-[0.2em] font-medium text-muted bg-surface-2 border border-th-border rounded-lg px-4 py-1.5 mb-6">Trust</span>
                <h2 class="text-[36px] sm:text-[44px] font-bold text-primary leading-[1.1] mb-5">Trusted by enterprises worldwide</h2>
                <p class="text-muted text-[15px] leading-[1.7] mb-10 max-w-[480px]">TriLink Platform is built to meet the highest standards of security, compliance, and reliability required by global enterprises and government organizations.</p>

                <div class="flex flex-col gap-6">
                    @php
                    $badges = [
                        ['icon' => '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622"/>', 'label' => 'Enterprise Security'],
                        ['icon' => '<path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>', 'label' => 'Data Privacy'],
                        ['icon' => '<path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>', 'label' => 'Industry Leader'],
                        ['icon' => '<path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>', 'label' => '99.9% Uptime'],
                    ];
                    @endphp

                    @foreach($badges as $b)
                    <div class="flex items-center gap-4 group hover:translate-x-1 transition-transform">
                        {{-- Gradient circle icon --}}
                        <div class="w-[48px] h-[48px] rounded-full flex items-center justify-center flex-shrink-0 shadow-[0_4px_16px_rgba(37,99,235,0.15)]"
                             style="background: linear-gradient(135deg, rgba(59,130,246,0.25) 0%, rgba(37,99,235,0.4) 100%); border: 1px solid rgba(59,130,246,0.25);">
                            <svg class="w-5 h-5 text-white dark:text-[#93C5FD]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">{!! $b['icon'] !!}</svg>
                        </div>
                        <span class="text-[16px] font-semibold text-primary">{{ $b['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
