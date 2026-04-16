@props([
    'type' => 'line',  // line, card, table, avatar
    'count' => 1,
    'class' => '',
])

@for($i = 0; $i < $count; $i++)
@if($type === 'line')
<div class="animate-pulse {{ $class }}">
    <div class="h-4 bg-surface-2 rounded-lg w-full"></div>
</div>
@elseif($type === 'card')
<div class="animate-pulse bg-surface border border-th-border rounded-2xl p-6 {{ $class }}">
    <div class="h-8 bg-surface-2 rounded-lg w-1/3 mb-4"></div>
    <div class="h-4 bg-surface-2 rounded-lg w-full mb-2"></div>
    <div class="h-4 bg-surface-2 rounded-lg w-2/3"></div>
</div>
@elseif($type === 'table')
<div class="animate-pulse bg-surface border border-th-border rounded-2xl overflow-hidden {{ $class }}">
    <div class="h-12 bg-surface-2 border-b border-th-border"></div>
    @for($j = 0; $j < 5; $j++)
    <div class="flex items-center gap-4 px-4 py-3 border-b border-th-border/50">
        <div class="h-4 bg-surface-2 rounded w-1/6"></div>
        <div class="h-4 bg-surface-2 rounded w-1/4"></div>
        <div class="h-4 bg-surface-2 rounded w-1/5"></div>
        <div class="h-4 bg-surface-2 rounded w-1/6"></div>
    </div>
    @endfor
</div>
@elseif($type === 'avatar')
<div class="animate-pulse flex items-center gap-3 {{ $class }}">
    <div class="w-10 h-10 bg-surface-2 rounded-full flex-shrink-0"></div>
    <div class="flex-1">
        <div class="h-4 bg-surface-2 rounded w-1/3 mb-2"></div>
        <div class="h-3 bg-surface-2 rounded w-1/2"></div>
    </div>
</div>
@endif
@endfor
