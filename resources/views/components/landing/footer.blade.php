<footer class="relative bg-page transition-colors duration-300 overflow-hidden">
    {{-- Gradient divider --}}
    <div class="h-px w-full" style="background: linear-gradient(90deg, transparent 0%, var(--c-border) 20%, var(--c-accent) 50%, var(--c-border) 80%, transparent 100%);"></div>

    <div class="max-w-[1280px] mx-auto px-6 lg:px-10 pt-20 pb-8 relative">

        {{-- Background decorative glow --}}
        <div class="absolute -bottom-[200px] -left-[100px] w-[500px] h-[500px] rounded-full opacity-[0.03] pointer-events-none" style="background: radial-gradient(circle, var(--c-accent) 0%, transparent 70%);"></div>

        {{-- Main grid: 5 columns --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-10 lg:gap-12 mb-16">

            {{-- Brand column --}}
            <div class="col-span-2 sm:col-span-1">
                <div class="flex items-center gap-2.5 mb-5">
                    <img src="{{ asset('logo/logo.png') }}" alt="TriLink" class="h-9 w-auto dark:brightness-100 brightness-0" />
                </div>
                <p class="text-[14px] text-muted leading-[1.7] mb-6 max-w-[220px]">The Complete Digital Trade & Procurement Ecosystem</p>

                {{-- Newsletter mini --}}
                <div class="flex items-center gap-2">
                    <div class="flex-1 relative">
                        <input type="email" placeholder="Your email" class="w-full bg-surface border border-th-border rounded-lg px-3.5 py-2.5 text-[13px] text-primary placeholder:text-faint focus:outline-none focus:border-accent/40 transition-colors" />
                    </div>
                    <button class="px-4 py-2.5 bg-accent hover:bg-accent-h text-white rounded-lg text-[13px] font-medium transition-colors flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                </div>
            </div>

            {{-- Products --}}
            <div>
                <h4 class="text-[14px] font-semibold text-primary uppercase tracking-[0.08em] mb-5">Products</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Service Providers</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Logistics Providers</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Buyers</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Suppliers</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Customs Clearance</a></li>
                </ul>
            </div>

            {{-- Company --}}
            <div>
                <h4 class="text-[14px] font-semibold text-primary uppercase tracking-[0.08em] mb-5">Company</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Home</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">About</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Features</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">How it works</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Contact</a></li>
                </ul>
            </div>

            {{-- Resources --}}
            <div>
                <h4 class="text-[14px] font-semibold text-primary uppercase tracking-[0.08em] mb-5">Resources</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Documentation</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">API Reference</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Blog</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Support</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h4 class="text-[14px] font-semibold text-primary uppercase tracking-[0.08em] mb-5">Legal</h4>
                <ul class="space-y-3">
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Terms of Service</a></li>
                    <li><a href="#" class="text-[14px] text-muted hover:text-accent transition-colors">Cookie Policy</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="pt-7 border-t border-th-border flex flex-col sm:flex-row items-center justify-between gap-5">
            {{-- Copyright --}}
            <p class="text-[13px] text-faint">&copy; {{ date('Y') }} TriLink Platform. All rights reserved.</p>

            {{-- Social icons --}}
            <div class="flex items-center gap-1">
                @php
                $whatsappNumber = config('app.whatsapp_number', '971500000000');
                $whatsappUrl    = 'https://wa.me/' . preg_replace('/\D/', '', $whatsappNumber);
                $socials = [
                    ['label' => 'WhatsApp', 'href' => $whatsappUrl, 'svg' => '<path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>', 'fill' => true],
                    ['label' => 'Twitter',  'href' => '#', 'svg' => '<path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>', 'fill' => true],
                    ['label' => 'LinkedIn', 'href' => '#', 'svg' => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>', 'fill' => true],
                    ['label' => 'Instagram','href' => '#', 'svg' => '<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor"/>', 'fill' => false],
                ];
                @endphp

                @foreach($socials as $s)
                <a href="{{ $s['href'] }}" {{ $s['href'] !== '#' ? 'target=_blank rel=noopener' : '' }} class="w-9 h-9 rounded-lg flex items-center justify-center text-faint hover:text-accent hover:bg-accent/5 transition-all" title="{{ $s['label'] }}">
                    <svg class="w-[17px] h-[17px]" @if($s['fill']) fill="currentColor" @else fill="none" stroke="currentColor" stroke-width="1.5" @endif viewBox="0 0 24 24">{!! $s['svg'] !!}</svg>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
