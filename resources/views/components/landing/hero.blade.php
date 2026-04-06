<section id="hero" class="relative min-h-[820px] flex items-center justify-center text-center overflow-hidden pt-[72px]">
    {{-- Animated starry sky canvas --}}
    <canvas id="starfield" class="absolute inset-0 w-full h-full"></canvas>

    {{-- Central orbital graphic --}}
    <div class="absolute inset-0 pointer-events-none flex items-center justify-center dark:opacity-100 opacity-[0.15] transition-opacity duration-500" style="padding-bottom: 180px;">
        <div class="absolute w-[320px] h-[320px] rounded-full bg-accent/10 blur-[60px]"></div>
        <div class="relative w-[220px] h-[220px]">
            <div class="absolute inset-0 rounded-full border border-accent/20"></div>
            <div class="absolute inset-[22%] rounded-full border border-accent/15"></div>
            <div class="absolute inset-[38%] rounded-full bg-surface border border-accent/15"></div>

            {{-- Floating icons --}}
            @php
            $icons = [
                ['-top-[32px] left-1/2 -translate-x-1/2', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
                ['top-[8%] -left-[38px]', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'],
                ['top-[8%] -right-[38px]', 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
                ['bottom-[8%] -left-[38px]', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                ['bottom-[8%] -right-[38px]', 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21'],
            ];
            @endphp
            @foreach($icons as $ic)
            <div class="absolute {{ $ic[0] }} hidden lg:flex">
                <div class="w-[46px] h-[46px] rounded-xl bg-surface border border-th-border flex items-center justify-center shadow-lg">
                    <svg class="w-5 h-5 text-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="{{ $ic[1] }}"/></svg>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Content --}}
    <div class="relative z-10 max-w-[780px] mx-auto px-6 pt-16">
        <h1 class="text-[42px] sm:text-[52px] lg:text-[62px] font-bold leading-[1.05] tracking-[-0.03em] reveal">
            <span class="text-gradient">The Complete Digital Trade &<br>Procurement Ecosystem</span>
        </h1>
        <p class="mt-6 text-muted text-[16px] sm:text-[18px] leading-[1.7] max-w-[620px] mx-auto reveal reveal-delay-1">
            Connect buyers, suppliers, logistics providers, customs clearance, and government authorities in one unified platform. Digitize your entire trade lifecycle from RFQ to delivery.
        </p>
        <div class="mt-9 reveal reveal-delay-2">
            <a href="#" class="inline-flex items-center gap-3 px-8 py-4 bg-accent hover:bg-accent-h text-white rounded-full text-[15px] font-semibold transition-all shadow-[0_6px_32px_rgba(37,99,235,0.3)] hover:shadow-[0_8px_40px_rgba(37,99,235,0.4)]">
                <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Start Now
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

        // Background gradient
        const grad = ctx.createLinearGradient(0, 0, 0, h);
        if (dark) {
            grad.addColorStop(0, '#06080F');
            grad.addColorStop(0.4, '#0B0F1A');
            grad.addColorStop(1, '#0F1629');
        } else {
            grad.addColorStop(0, '#0F172A');
            grad.addColorStop(0.5, '#1E293B');
            grad.addColorStop(1, '#334155');
        }
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, w, h);

        // Stars
        const t = time * 0.001;
        for (const s of stars) {
            const twinkle = Math.sin(t * s.twinkleSpeed * 60 + s.twinkleOffset);
            s.alpha = s.baseAlpha * (0.6 + 0.4 * twinkle);

            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,255,255,${s.alpha})`;
            ctx.fill();

            // Glow for brighter stars
            if (s.r > 1.0) {
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r * 3, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(180,200,255,${s.alpha * 0.12})`;
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
            g.addColorStop(0, `rgba(255,255,255,0)`);
            g.addColorStop(1, `rgba(255,255,255,${ss.alpha * 0.7})`);

            ctx.beginPath();
            ctx.moveTo(tailX, tailY);
            ctx.lineTo(ss.x, ss.y);
            ctx.strokeStyle = g;
            ctx.lineWidth = 1.2;
            ctx.stroke();

            // Head glow
            ctx.beginPath();
            ctx.arc(ss.x, ss.y, 2, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255,255,255,${ss.alpha})`;
            ctx.fill();
        }

        // Subtle nebula glow
        const nebulaAlpha = dark ? 0.04 : 0.03;
        const nebula = ctx.createRadialGradient(w * 0.5, h * 0.35, 0, w * 0.5, h * 0.35, w * 0.4);
        nebula.addColorStop(0, `rgba(59,130,246,${nebulaAlpha * 2})`);
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
