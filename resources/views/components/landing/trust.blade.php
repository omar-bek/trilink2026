<section id="trust" class="relative py-28 px-6 lg:px-10 bg-page transition-colors duration-300 overflow-hidden">
    {{-- Subtle backdrop spotlight --}}
    <div class="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 w-[1000px] h-[600px] rounded-full bg-accent/5 blur-[120px]"></div>
    <div class="max-w-[1280px] mx-auto">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            {{-- Left - Logo Card with star dots --}}
            <div class="reveal">
                <div class="trust-logo-card border border-th-border rounded-2xl p-12 sm:p-16 flex flex-col items-center justify-center min-h-[460px] relative overflow-hidden">
                    {{-- Star dots --}}
                    <div class="absolute top-[12%] left-[10%] w-1.5 h-1.5 bg-primary/10 rounded-full"></div>
                    <div class="absolute top-[18%] right-[15%] w-1 h-1 bg-primary/8 rounded-full"></div>
                    <div class="absolute top-[35%] left-[22%] w-1 h-1 bg-primary/6 rounded-full"></div>
                    <div class="absolute bottom-[25%] left-[18%] w-1.5 h-1.5 bg-primary/8 rounded-full"></div>
                    <div class="absolute bottom-[18%] right-[12%] w-1 h-1 bg-primary/6 rounded-full"></div>
                    <div class="absolute top-[50%] right-[25%] w-1 h-1 bg-primary/5 rounded-full"></div>
                    <div class="absolute bottom-[40%] left-[35%] w-1 h-1 bg-primary/5 rounded-full"></div>

                    {{-- Logo — cyan → blue glow (Figma) --}}
                    <div class="relative">
                        <div class="absolute inset-0 -m-8 rounded-[40px] bg-gradient-to-br from-sky-400/15 via-blue-600/10 to-indigo-900/20 blur-3xl scale-110 dark:from-sky-400/25 dark:via-blue-600/20 dark:to-indigo-900/35"></div>
                        <div class="absolute inset-0 blur-[70px] bg-gradient-to-t from-blue-600/15 to-sky-400/10 rounded-full scale-[1.6] dark:from-blue-700/25 dark:to-sky-400/15"></div>
                        <img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" class="relative w-[240px] h-auto brightness-0 dark:brightness-100" />
                    </div>
                </div>
            </div>

            {{-- Right - Content --}}
            <div class="reveal reveal-delay-1">
                <span class="inline-block rounded-lg border border-th-border bg-surface-2 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">Trust</span>
                <h2 class="h-section mb-5">Trusted by enterprises worldwide</h2>
                <p class="t-lead mb-10 max-w-[480px]">TriLink Platform is built to meet the highest standards of security, compliance, and reliability required by global enterprises and government organizations.</p>

                <div class="flex flex-col gap-6">
                    @php
                    // Lucide icons rendered inline so the multi-element ones
                    // (award) draw correctly. Each `svg` is the body of an
                    // <svg> element from lucide.dev.
                    $badges = [
                        // Lucide: shield
                        ['svg' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>', 'label' => 'Enterprise Security'],
                        // Lucide: lock
                        ['svg' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', 'label' => 'Data Privacy'],
                        // Lucide: award
                        ['svg' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>', 'label' => 'Industry Leader'],
                        // Lucide: zap
                        ['svg' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>', 'label' => '99.9% Uptime'],
                    ];
                    @endphp

                    @foreach($badges as $b)
                    <div class="flex items-center gap-4 group hover:translate-x-1 transition-transform">
                        {{-- Rounded-square gradient icon (matches the Figma rather than a full circle) --}}
                        <div class="w-[48px] h-[48px] rounded-[12px] flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(180deg, #38bdf8 0%, #1d4ed8 100%); border: 1px solid rgba(56, 189, 248, 0.35); box-shadow: inset 0 1px 0 0 rgba(255,255,255,0.12);">
                            <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $b['svg'] !!}</svg>
                        </div>
                        <span class="text-[16px] font-semibold tracking-[-0.011em] text-primary">{{ $b['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
