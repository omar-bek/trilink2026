<section id="hero" class="relative min-h-[820px] flex items-center justify-center text-center overflow-hidden pt-[72px] bg-spotlight">
    {{-- Animated starry sky canvas --}}
    <canvas id="starfield" class="absolute inset-0 w-full h-full"></canvas>

    {{-- Central orbital graphic --}}
    <div class="absolute inset-0 pointer-events-none flex items-center justify-center dark:opacity-100 opacity-[0.15] transition-opacity duration-500" style="padding-bottom: 180px;">
        <div class="absolute w-[320px] h-[320px] rounded-full bg-accent/10 blur-[60px]"></div>
        <div class="relative w-[220px] h-[220px]">
            <div class="absolute inset-0 rounded-full border border-accent/20"></div>
            <div class="absolute inset-[22%] rounded-full border border-accent/15"></div>
            <div class="absolute inset-[38%] rounded-full bg-surface border border-accent/15"></div>

            {{-- Floating icons — five stakeholders, exact Lucide markup so
                 they match the Figma 1:1 (every Lucide icon is the same
                 source the designer used). Multi-element icons like truck
                 and landmark need full SVG bodies, so we render each one
                 inline rather than passing a single path string. --}}
            @php
            $iconBoxes = [
                ['pos' => '-top-[32px] left-1/2 -translate-x-1/2', 'svg' => 'truck'],
                ['pos' => 'top-[8%] -left-[38px]',                 'svg' => 'factory'],
                ['pos' => 'top-[8%] -right-[38px]',                'svg' => 'file-check'],
                ['pos' => 'bottom-[8%] -left-[38px]',              'svg' => 'building-2'],
                ['pos' => 'bottom-[8%] -right-[38px]',             'svg' => 'landmark'],
            ];
            @endphp
            @foreach($iconBoxes as $i => $ic)
            <div class="absolute {{ $ic['pos'] }} hidden lg:flex animate-float{{ $i > 0 ? '-delay-' . min($i, 3) : '' }}">
                <div class="flex h-[46px] w-[46px] items-center justify-center rounded-xl border border-slate-200/90 bg-white shadow-md backdrop-blur-sm dark:border-white/[0.08] dark:bg-[#151921] dark:shadow-[0_8px_32px_rgba(0,0,0,0.35)]">
                    @switch($ic['svg'])
                        @case('truck')
                            <svg class="h-5 w-5 text-slate-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                            @break
                        @case('factory')
                            <svg class="h-5 w-5 text-slate-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/></svg>
                            @break
                        @case('file-check')
                            <svg class="h-5 w-5 text-slate-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>
                            @break
                        @case('building-2')
                            <svg class="h-5 w-5 text-slate-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                            @break
                        @case('landmark')
                            <svg class="h-5 w-5 text-slate-700 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>
                            @break
                    @endswitch
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Content --}}
    <div class="relative z-10 max-w-[820px] mx-auto px-6 pt-16">
        <h1 class="h-display reveal">
            <span class="text-gradient">The Complete Digital Trade &<br>Procurement Ecosystem</span>
        </h1>
        <p class="mt-6 t-lead max-w-[620px] mx-auto reveal reveal-delay-1">
            Connect buyers, suppliers, logistics providers, customs clearance, and government authorities in one unified platform. Digitize your entire trade lifecycle from RFQ to delivery.
        </p>
        <div class="mt-9 reveal reveal-delay-2">
            <a href="{{ route('register') }}" class="group relative inline-flex items-center gap-3 px-8 py-3.5 bg-accent hover:bg-accent-h text-white rounded-full text-[15px] font-semibold tracking-[-0.011em] transition-all duration-300 shadow-[0_10px_40px_rgba(59,126,255,0.45)] hover:shadow-[0_14px_50px_rgba(59,126,255,0.6)] hover:-translate-y-0.5 shimmer overflow-hidden">
                <svg class="w-4 h-4 rtl:rotate-0 ltr:rotate-180 transition-transform group-hover:-translate-x-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                <span class="relative z-[2]">Start Now</span>
            </a>
        </div>
    </div>
</section>

@push('scripts')
<script>
(function() {
    const canvas = document.getElementById('starfield');
    const ctx = canvas.getContext('2d');
    let stars = [];
    let shootingStars = [];
    let w, h;
    let animId;

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        w = canvas.width = rect.width;
        h = canvas.height = rect.height;
    }

    function createStars() {
        stars = [];
        const count = Math.floor((w * h) / 2800);
        for (let i = 0; i < count; i++) {
            stars.push({
                x: Math.random() * w,
                y: Math.random() * h,
                r: Math.random() * 1.4 + 0.3,
                baseAlpha: Math.random() * 0.6 + 0.2,
                alpha: 0,
                twinkleSpeed: Math.random() * 0.008 + 0.003,
                twinkleOffset: Math.random() * Math.PI * 2,
            });
        }
    }

    function spawnShootingStar() {
        if (shootingStars.length > 2) return;
        shootingStars.push({
            x: Math.random() * w * 0.7,
            y: Math.random() * h * 0.4,
            len: Math.random() * 60 + 40,
            speed: Math.random() * 4 + 3,
            alpha: 1,
            angle: Math.PI / 5 + Math.random() * 0.3,
        });
    }

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function draw(time) {
        ctx.clearRect(0, 0, w, h);

        const dark = isDark();
        // Particle color flips between mode: white on dark bg, navy on light bg.
        // Same applies to shooting-star tails so they actually contrast.
        const particle = dark ? '255,255,255' : '15,23,42';
        const glow     = dark ? '180,200,255' : '59,126,255';

        // Background gradient — dark navy in dark mode, light cool white
        // (matching --c-page) in light mode so it blends with the rest of
        // the landing page instead of looking like a transplanted dark hero.
        const grad = ctx.createLinearGradient(0, 0, 0, h);
        if (dark) {
            grad.addColorStop(0, '#05070a');
            grad.addColorStop(0.45, '#0b0e14');
            grad.addColorStop(1, '#0f1419');
        } else {
            grad.addColorStop(0, '#fbfcfe');
            grad.addColorStop(0.5, '#f4f7fd');
            grad.addColorStop(1, '#eef2fb');
        }
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, w, h);

        // Stars / particles
        const t = time * 0.001;
        for (const s of stars) {
            const twinkle = Math.sin(t * s.twinkleSpeed * 60 + s.twinkleOffset);
            s.alpha = s.baseAlpha * (0.6 + 0.4 * twinkle);

            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${particle},${s.alpha * (dark ? 1 : 0.35)})`;
            ctx.fill();

            // Glow for brighter stars
            if (s.r > 1.0) {
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r * 3, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${glow},${s.alpha * 0.12})`;
                ctx.fill();
            }
        }

        // Shooting stars
        for (let i = shootingStars.length - 1; i >= 0; i--) {
            const ss = shootingStars[i];
            ss.x += Math.cos(ss.angle) * ss.speed;
            ss.y += Math.sin(ss.angle) * ss.speed;
            ss.alpha -= 0.008;

            if (ss.alpha <= 0 || ss.x > w || ss.y > h) {
                shootingStars.splice(i, 1);
                continue;
            }

            const tailX = ss.x - Math.cos(ss.angle) * ss.len;
            const tailY = ss.y - Math.sin(ss.angle) * ss.len;

            const g = ctx.createLinearGradient(tailX, tailY, ss.x, ss.y);
            g.addColorStop(0, `rgba(${particle},0)`);
            g.addColorStop(1, `rgba(${particle},${ss.alpha * (dark ? 0.7 : 0.4)})`);

            ctx.beginPath();
            ctx.moveTo(tailX, tailY);
            ctx.lineTo(ss.x, ss.y);
            ctx.strokeStyle = g;
            ctx.lineWidth = 1.2;
            ctx.stroke();

            // Head glow
            ctx.beginPath();
            ctx.arc(ss.x, ss.y, 2, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${particle},${ss.alpha * (dark ? 1 : 0.5)})`;
            ctx.fill();
        }

        // Subtle nebula glow — same brand color in both modes
        const nebulaAlpha = dark ? 0.04 : 0.05;
        const nebula = ctx.createRadialGradient(w * 0.5, h * 0.35, 0, w * 0.5, h * 0.35, w * 0.4);
        nebula.addColorStop(0, `rgba(59,126,255,${nebulaAlpha * 2})`);
        nebula.addColorStop(0.5, `rgba(99,102,241,${nebulaAlpha})`);
        nebula.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = nebula;
        ctx.fillRect(0, 0, w, h);

        animId = requestAnimationFrame(draw);
    }

    function init() {
        resize();
        createStars();
        if (animId) cancelAnimationFrame(animId);
        animId = requestAnimationFrame(draw);
    }

    // Shooting star every 4-8 seconds
    setInterval(spawnShootingStar, 4000 + Math.random() * 4000);

    window.addEventListener('resize', () => { resize(); createStars(); });
    init();

    // Re-watch for theme changes (observer on html class)
    const htmlEl = document.documentElement;
    const mo = new MutationObserver(() => {});
    mo.observe(htmlEl, { attributes: true, attributeFilter: ['class'] });
})();
</script>
@endpush
