<section id="how-it-works" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto">
        <div class="grid lg:grid-cols-2 gap-16 items-start">
            {{-- Left --}}
            <div class="lg:sticky lg:top-32 reveal">
                <span class="inline-block text-[12px] uppercase tracking-[0.2em] font-medium text-accent bg-accent/10 border border-accent/20 rounded-full px-4 py-1.5 mb-6">How it works</span>
                <h2 class="text-[38px] sm:text-[46px] font-bold text-gradient leading-[1.08] mb-5">Streamlined trade workflow</h2>
                <p class="text-muted text-[16px] leading-[1.7] max-w-[440px]">From initial RFQ to final payment, TriLink digitizes every step of your procurement and trade lifecycle.</p>
            </div>

            {{-- Right - Vertical timeline card --}}
            <div class="reveal reveal-delay-1">
                <div class="bg-surface border border-th-border rounded-2xl p-8 sm:p-10 relative">
                    {{-- Vertical connecting line --}}
                    <div class="absolute left-1/2 top-[52px] bottom-[52px] w-[2px] bg-th-border -translate-x-1/2 rounded-full"></div>

                    <div class="flex flex-col items-center gap-4 relative z-10">
                        @php
                        $steps = [
                            ['icon' => '<path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>', 'label' => 'Create RFQ', 'last' => false],
                            ['icon' => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>', 'label' => 'Submit Bids', 'last' => false],
                            ['icon' => '<path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'label' => 'Select Providers', 'last' => false],
                            ['icon' => '<path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>', 'label' => 'Generate Contract', 'last' => false],
                            ['icon' => '<path d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-3.379"/>', 'label' => 'Shipment Tracking', 'last' => false],
                            ['icon' => '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622"/>', 'label' => 'Customs Clearance', 'last' => false],
                            ['icon' => '<path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'label' => 'Payment', 'last' => true],
                        ];
                        @endphp

                        @foreach($steps as $step)
                        <div class="flex items-center gap-3 {{ $step['last'] ? 'bg-[#22C55E]/10 border-[#22C55E]/20' : 'bg-surface-2 border-th-border' }} border rounded-xl px-5 py-3 min-w-[200px] hover:scale-[1.02] transition-transform">
                            <div class="w-[32px] h-[32px] rounded-lg {{ $step['last'] ? 'bg-[#22C55E]/20' : 'bg-elevated' }} flex items-center justify-center flex-shrink-0">
                                <svg class="w-[16px] h-[16px] {{ $step['last'] ? 'text-[#22C55E]' : 'text-icon' }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">{!! $step['icon'] !!}</svg>
                            </div>
                            <span class="text-[13px] font-semibold {{ $step['last'] ? 'text-[#22C55E]' : 'text-body' }}">{{ $step['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- End-to-End Visibility --}}
        <div class="mt-28 text-center reveal">
            <h3 class="text-[28px] sm:text-[34px] font-bold text-primary mb-3">End-to-End Visibility</h3>
            <p class="text-muted text-[15px] mb-10">Track every stage of your trade operations with real-time updates and automated notifications.</p>
            <div class="max-w-[600px] mx-auto flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-[#22C55E] flex-shrink-0"></div>
                <div class="flex-1 h-[6px] bg-elevated rounded-full overflow-hidden">
                    <div class="h-full w-[75%] bg-gradient-to-r from-accent to-icon rounded-full"></div>
                </div>
                <div class="w-3 h-3 rounded-full bg-[#F59E0B] flex-shrink-0"></div>
            </div>
        </div>
    </div>
</section>
