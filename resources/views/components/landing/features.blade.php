<section id="features" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto">
        <div class="text-center mb-16 reveal">
            <h2 class="h-display text-gradient">Features</h2>
            <p class="mt-3 t-lead text-[15px] max-w-[640px] mx-auto">
                Everything you need to run procurement and trade in one place—RFQs, bids, contracts, logistics, and compliance.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @php
            // Each entry is the FULL Lucide SVG body so multi-element icons
            // (truck, factory, headphones…) render correctly. Single-path
            // icons still work — they're just inline like the others.
            $features = [
                [
                    'title' => 'Smart Procurement',
                    'desc'  => 'Automated RFQ management, bid comparison, and intelligent supplier matching with AI-powered recommendations.',
                    // Lucide: shopping-cart
                    'svg'   => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
                ],
                [
                    'title' => 'Real-time Analytics',
                    'desc'  => 'Comprehensive dashboards with live trade metrics, performance tracking, and predictive insights.',
                    // Lucide: trending-up
                    'svg'   => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
                ],
                [
                    'title' => 'Compliance & Security',
                    'desc'  => 'Enterprise-grade security with SOC 2, ISO 27001 compliance, and automated regulatory checks.',
                    // Lucide: shield (plain outline)
                    'svg'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
                ],
                [
                    'title' => 'Automated Workflows',
                    'desc'  => 'Streamline operations with customizable automation for contracts, approvals, and documentation.',
                    // Lucide: zap (lightning bolt)
                    'svg'   => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
                ],
                [
                    'title' => 'Global Network',
                    'desc'  => 'Connect with verified suppliers, logistics providers, and service partners across 150+ countries.',
                    // Lucide: globe
                    'svg'   => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
                ],
                [
                    'title' => 'Advanced Reporting',
                    'desc'  => 'Generate detailed reports on procurement performance, cost savings, and supplier metrics.',
                    // Lucide: bar-chart-3
                    'svg'   => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
                ],
                [
                    'title' => 'Smart Contracts',
                    'desc'  => 'Blockchain-enabled smart contracts for transparent, automated, and secure transactions.',
                    // Lucide: lock
                    'svg'   => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
                ],
                [
                    'title' => 'Integration Hub',
                    'desc'  => 'Seamlessly connect with ERPs, CRMs, and existing systems through our robust API platform.',
                    // Lucide: link (two connectors — matches Figma)
                    'svg'   => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
                ],
            ];
            @endphp

            @foreach($features as $i => $f)
            <div class="landing-feature-card relative overflow-hidden bg-surface2 border border-th-border rounded-2xl pt-8 pb-8 px-7 flex flex-col hover:border-accent/30 hover:shadow-[0_18px_50px_-20px_rgba(59,126,255,0.25)] transition-all duration-300 group reveal reveal-delay-{{ $i % 4 < 2 ? '1' : '2' }}">
                {{-- Icon box — dark tile + white strokes (Figma) --}}
                <div class="mb-auto flex h-[48px] w-[48px] items-center justify-center rounded-[14px] border border-slate-200/90 bg-slate-100 transition-colors dark:border-white/[0.06] dark:bg-[#0d1018]">
                    <svg class="h-[20px] w-[20px] text-slate-800 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $f['svg'] !!}</svg>
                </div>

                {{-- Text pushed to bottom --}}
                <div class="mt-8">
                    <h3 class="h-card mb-2">{{ $f['title'] }}</h3>
                    <p class="t-body">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
