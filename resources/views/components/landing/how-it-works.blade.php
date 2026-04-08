<section id="how-it-works" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
            {{-- Left --}}
            <div class="lg:sticky lg:top-32 reveal">
                <span class="inline-block t-eyebrow text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-6">How it works</span>
                <h2 class="h-section text-gradient mb-5">Streamlined trade workflow</h2>
                <p class="t-lead max-w-[440px]">From initial RFQ to final payment, TriLink digitizes every step of your procurement and trade lifecycle.</p>
            </div>

            {{-- Right - Vertical timeline card --}}
            <div class="reveal reveal-delay-1">
                <div class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10 relative shadow-[0_24px_80px_-32px_rgba(0,0,0,0.45)]">
                    {{-- Vertical connecting line --}}
                    <div class="absolute left-1/2 top-[52px] bottom-[52px] w-[2px] bg-th-border -translate-x-1/2 rounded-full"></div>

                    <div class="flex flex-col items-center gap-4 relative z-10">
                        @php
                        // 7 timeline steps — each `svg` is the FULL Lucide
                        // body so multi-element marks (truck, package…) draw
                        // correctly. Last entry gets the green "Payment"
                        // success styling.
                        $steps = [
                            // Lucide: file-text
                            ['svg' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>', 'label' => 'Create RFQ', 'last' => false],
                            // Lucide: users
                            ['svg' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'label' => 'Submit Bids', 'last' => false],
                            // Lucide: check-circle
                            ['svg' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', 'label' => 'Select Providers', 'last' => false],
                            // Lucide: file-pen (contract)
                            ['svg' => '<path d="M12.5 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5v3"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M13.378 15.626a1 1 0 1 0-3.004-3.004l-5.01 5.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z"/>', 'label' => 'Generate Contract', 'last' => false],
                            // Lucide: package (cube/box)
                            ['svg' => '<path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/>', 'label' => 'Shipment Tracking', 'last' => false],
                            // Lucide: shield
                            ['svg' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>', 'label' => 'Customs Clearance', 'last' => false],
                            // Lucide: check-circle (payment success)
                            ['svg' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', 'label' => 'Payment', 'last' => true],
                        ];
                        @endphp

                        @foreach($steps as $step)
                        <div class="flex min-w-[210px] items-center gap-3 rounded-xl border border-slate-200/90 bg-slate-200/90 px-5 py-3 transition-transform duration-300 hover:scale-[1.02] dark:border-white/[0.06] dark:bg-[#334155]">
                            @if($step['last'])
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-[#22C55E]">
                                    <svg class="h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <span class="text-[13px] font-semibold tracking-[-0.011em] text-slate-900 dark:text-white">{{ $step['label'] }}</span>
                            @else
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-300/90 dark:bg-[#475569]">
                                    <svg class="h-4 w-4 text-slate-800 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $step['svg'] !!}</svg>
                                </div>
                                <span class="text-[13px] font-semibold tracking-[-0.011em] text-slate-900 dark:text-white">{{ $step['label'] }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- End-to-End Visibility --}}
        <div class="mt-28 text-center reveal">
            <h3 class="font-display text-[26px] sm:text-[32px] font-bold text-primary tracking-[-0.024em] leading-[1.1] mb-3">End-to-End Visibility</h3>
            <p class="t-lead text-[15px] mb-10">Track every stage of your trade operations with real-time updates and automated notifications.</p>
            <div class="max-w-[600px] mx-auto flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-[#22C55E] flex-shrink-0"></div>
                <div class="h-[6px] flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-[#1e293b]">
                    <div class="h-full w-[75%] rounded-full bg-accent shadow-sm dark:bg-[#3B82F6] dark:shadow-[0_0_12px_rgba(59,130,246,0.45)]"></div>
                </div>
                <div class="w-3 h-3 rounded-full bg-[#F59E0B] flex-shrink-0"></div>
            </div>
        </div>
    </div>
</section>
