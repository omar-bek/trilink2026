@props(['level' => null])

@php
use App\Enums\VerificationLevel;

if (is_string($level)) {
    $level = VerificationLevel::tryFrom($level);
}
$level = $level instanceof VerificationLevel ? $level : VerificationLevel::UNVERIFIED;

$colors = [
    'unverified' => ['bg' => 'bg-zinc-500/10',  'text' => 'text-zinc-400',  'border' => 'border-zinc-500/20'],
    'bronze'     => ['bg' => 'bg-amber-700/10', 'text' => 'text-amber-500', 'border' => 'border-amber-700/20'],
    'silver'     => ['bg' => 'bg-slate-400/10', 'text' => 'text-slate-300', 'border' => 'border-slate-400/30'],
    'gold'       => ['bg' => 'bg-yellow-500/10','text' => 'text-yellow-400','border' => 'border-yellow-500/30'],
    'platinum'   => ['bg' => 'bg-violet-500/10','text' => 'text-violet-400','border' => 'border-violet-500/30'],
];
$c = $colors[$level->value] ?? $colors['unverified'];
@endphp

<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $c['bg'] }} {{ $c['text'] }} {{ $c['border'] }}">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>
    </svg>
    {{ $level->label() }}
</span>
