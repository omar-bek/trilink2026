@props([
    'value' => '',
    'label' => '',
    'color' => 'blue', // blue, green/emerald, orange, red, purple, pink, teal, slate
    'icon' => null,
    // When `href` is set the card renders as an <a> instead of a <div>, so
    // it can act as a clickable filter on index pages.
    'href' => null,
    // Highlight the card with an accent ring when it represents the
    // currently-selected filter.
    'active' => false,
])

@php
// Figma palette — each stat card has a colored border + matching icon bubble.
$colorMap = [
    'blue'    => ['border' => 'border-[#4f7cff]/40', 'text' => 'text-[#4f7cff]', 'bg' => 'bg-[#4f7cff]/[0.04]', 'iconBg' => 'bg-[#4f7cff]/10', 'iconColor' => 'text-[#4f7cff]'],
    'green'   => ['border' => 'border-[#00d9b5]/40', 'text' => 'text-[#00d9b5]', 'bg' => 'bg-[#00d9b5]/[0.04]', 'iconBg' => 'bg-[#00d9b5]/10', 'iconColor' => 'text-[#00d9b5]'],
    'emerald' => ['border' => 'border-[#00d9b5]/40', 'text' => 'text-[#00d9b5]', 'bg' => 'bg-[#00d9b5]/[0.04]', 'iconBg' => 'bg-[#00d9b5]/10', 'iconColor' => 'text-[#00d9b5]'],
    'teal'    => ['border' => 'border-[#14B8A6]/40', 'text' => 'text-[#14B8A6]', 'bg' => 'bg-[#14B8A6]/[0.04]', 'iconBg' => 'bg-[#14B8A6]/10', 'iconColor' => 'text-[#14B8A6]'],
    'orange'  => ['border' => 'border-[#ffb020]/40', 'text' => 'text-[#ffb020]', 'bg' => 'bg-[#ffb020]/[0.04]', 'iconBg' => 'bg-[#ffb020]/10', 'iconColor' => 'text-[#ffb020]'],
    'red'     => ['border' => 'border-[#ff4d7f]/40', 'text' => 'text-[#ff4d7f]', 'bg' => 'bg-[#ff4d7f]/[0.04]', 'iconBg' => 'bg-[#ff4d7f]/10', 'iconColor' => 'text-[#ff4d7f]'],
    'pink'    => ['border' => 'border-[#ff4d7f]/40', 'text' => 'text-[#ff4d7f]', 'bg' => 'bg-[#ff4d7f]/[0.04]', 'iconBg' => 'bg-[#ff4d7f]/10', 'iconColor' => 'text-[#ff4d7f]'],
    'purple'  => ['border' => 'border-[#8B5CF6]/40', 'text' => 'text-[#8B5CF6]', 'bg' => 'bg-[#8B5CF6]/[0.04]', 'iconBg' => 'bg-[#8B5CF6]/10', 'iconColor' => 'text-[#8B5CF6]'],
    'slate'   => ['border' => 'border-th-border', 'text' => 'text-primary', 'bg' => 'bg-surface', 'iconBg' => 'bg-surface-2', 'iconColor' => 'text-muted'],
];
$c = $colorMap[$color] ?? $colorMap['blue'];

// When the card is active we replace the soft tinted border with a stronger
// accent ring + slightly more saturated background so the user can tell at
// a glance which filter is currently applied.
$ringClass = $active ? 'ring-2 ring-accent ring-offset-2 ring-offset-page' : '';
$cursorClass = $href ? 'cursor-pointer' : '';
$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="block rounded-2xl border-2 {{ $c['border'] }} {{ $c['bg'] }} p-6 transition-all hover:scale-[1.01] hover:shadow-lg {{ $ringClass }} {{ $cursorClass }}">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-[32px] sm:text-[36px] font-bold {{ $c['text'] }} leading-none mb-2 truncate">{{ $value }}</p>
            <p class="text-[13px] text-muted">{{ $label }}</p>
        </div>
        @if($icon)
        <div class="w-10 h-10 rounded-xl {{ $c['iconBg'] }} flex items-center justify-center flex-shrink-0">
            <svg class="w-[18px] h-[18px] {{ $c['iconColor'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon !!}</svg>
        </div>
        @endif
    </div>
</{{ $tag }}>
