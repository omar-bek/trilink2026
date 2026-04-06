<section id="features" class="py-28 px-6 lg:px-10 bg-page transition-colors duration-300">
    <div class="max-w-[1280px] mx-auto">
        <div class="text-center mb-16 reveal">
            <h2 class="text-[42px] sm:text-[52px] font-bold text-gradient">features</h2>
            <p class="mt-3 text-muted text-[16px]">Cast a spell on your Figma designs.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @php
            $features = [
                ['icon' => '<path d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>', 'title' => 'Smart Procurement', 'desc' => 'Automated RFQ management, bid comparison, and intelligent supplier matching with AI-powered recommendations.'],
                ['icon' => '<path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>', 'title' => 'Real-time Analytics', 'desc' => 'Comprehensive dashboards with live trade metrics, performance tracking, and predictive insights.'],
                ['icon' => '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>', 'title' => 'Compliance & Security', 'desc' => 'Enterprise-grade security with SOC 2, ISO 27001 compliance, and automated regulatory checks.'],
                ['icon' => '<path d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>', 'title' => 'Automated Workflows', 'desc' => 'Streamline operations with customizable automation for contracts, approvals, and documentation.'],
                ['icon' => '<path d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>', 'title' => 'Global Network', 'desc' => 'Connect with verified suppliers, logistics providers, and service partners across 150+ countries.'],
                ['icon' => '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>', 'title' => 'Advanced Reporting', 'desc' => 'Generate detailed reports on procurement performance, cost savings, and supplier metrics.'],
                ['icon' => '<path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>', 'title' => 'Smart Contracts', 'desc' => 'Blockchain-enabled smart contracts for transparent, automated, and secure transactions.'],
                ['icon' => '<path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.717a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>', 'title' => 'Integration Hub', 'desc' => 'Seamlessly connect with ERPs, CRMs, and existing systems through our robust API platform.'],
            ];
            @endphp

            @foreach($features as $i => $f)
            <div class="bg-surface border border-th-border rounded-2xl pt-8 pb-8 px-7 flex flex-col hover:border-accent/20 hover:shadow-lg transition-all duration-300 group reveal reveal-delay-{{ $i % 4 < 2 ? '1' : '2' }}">
                {{-- Icon box --}}
                <div class="w-[48px] h-[48px] rounded-[14px] bg-surface-2 border border-th-border flex items-center justify-center mb-auto group-hover:border-accent/30 transition-colors">
                    <svg class="w-[20px] h-[20px] text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">{!! $f['icon'] !!}</svg>
                </div>

                {{-- Text pushed to bottom --}}
                <div class="mt-8">
                    <h3 class="text-[16px] font-bold text-primary mb-2">{{ $f['title'] }}</h3>
                    <p class="text-[13px] text-muted leading-[1.7]">{{ $f['desc'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
