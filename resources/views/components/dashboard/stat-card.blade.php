@props([
    'value' => '',
    'label' => '',
    'color' => 'blue', // blue, green, orange, red, purple, slate
    'icon' => null,
])

@php
$colorMap = [
    'blue'   => ['border' => 'border-[#3B82F6]/30', 'text' => 'text-[#3B82F6]', 'bg' => 'bg-[#3B82F6]/[0.06]', 'iconBg' => 'bg-[#3B82F6]/10', 'iconColor' => 'text-[#3B82F6]'],
    'green'  => ['border' => 'border-[#10B981]/30', 'text' => 'text-[#10B981]', 'bg' => 'bg-[#10B981]/[0.06]', 'iconBg' => 'bg-[#10B981]/10', 'iconColor' => 'text-[#10B981]'],
    'orange' => ['border' => 'border-[#F59E0B]/30', 'text' => 'text-[#F59E0B]', 'bg' => 'bg-[#F59E0B]/[0.06]', 'iconBg' => 'bg-[#F59E0B]/10', 'iconColor' => 'text-[#F59E0B]'],
    'red'    => ['border' => 'border-[#EF4444]/30', 'text' => 'text-[#EF4444]', 'bg' => 'bg-[#EF4444]/[0.06]', 'iconBg' => 'bg-[#EF4444]/10', 'iconColor' => 'text-[#EF4444]'],
    'purple' => ['border' => 'border-[#8B5CF6]/30', 'text' => 'text-[#8B5CF6]', 'bg' => 'bg-[#8B5CF6]/[0.06]', 'iconBg' => 'bg-[#8B5CF6]/10', 'iconColor' => 'text-[#8B5CF6]'],
    'slate'  => ['border' => 'border-th-border', 'text' => 'text-primary', 'bg' => 'bg-surface', 'iconBg' => 'bg-surface-2', 'iconColor' => 'text-muted'],
];
$c = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div class="rounded-2xl border {{ $c['border'] }} {{ $c['bg'] }} p-6 transition-all hover:scale-[1.01] hover:shadow-lg">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <p class="text-[28px] font-bold {{ $c['text'] }} leading-none mb-2">{{ $value }}</p>
            <p class="text-[13px] text-muted">{{ $label }}</p>
        </div>
        @if($icon)
        <div class="w-9 h-9 rounded-lg {{ $c['iconBg'] }} flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 {{ $c['iconColor'] }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icon !!}</svg>
        </div>
        @endif
    </div>
</div>
