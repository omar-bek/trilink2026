@props(['status' => 'open', 'dot' => true])

@php
$map = [
    'open'        => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.open')],
    'active'      => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.active')],
    'completed'   => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.completed')],
    'paid'        => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.paid')],
    'resolved'    => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.resolved')],
    'accepted'    => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.accepted')],
    'approved'    => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]', 'border' => 'border-[#10B981]/20', 'dotColor' => 'bg-[#10B981]', 'label' => __('status.approved')],
    'delivered'   => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.delivered')],

    'in_transit'  => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]', 'border' => 'border-[#3B82F6]/20', 'dotColor' => 'bg-[#3B82F6]', 'label' => __('status.in_transit') ?? 'In Transit'],
    'submitted'   => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]', 'border' => 'border-[#3B82F6]/20', 'dotColor' => 'bg-[#3B82F6]', 'label' => __('status.submitted')],
    'in_progress' => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]', 'border' => 'border-[#3B82F6]/20', 'dotColor' => 'bg-[#3B82F6]', 'label' => __('status.in_progress')],
    'in_mediation'=> ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]', 'border' => 'border-[#3B82F6]/20', 'dotColor' => 'bg-[#3B82F6]', 'label' => __('status.in_mediation')],
    'under_review'=> ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.under_review')],
    'out_for_delivery' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.out_for_delivery')],

    'pending'     => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.pending')],
    'closed'      => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.closed')],
    'shortlisted' => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.shortlisted')],
    'due_soon'    => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.due_soon')],
    'scheduled'   => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]', 'border' => 'border-[#3B82F6]/20', 'dotColor' => 'bg-[#3B82F6]', 'label' => __('status.scheduled')],
    'at_customs'  => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.at_customs')],
    'preparing'   => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]', 'border' => 'border-[#F59E0B]/20', 'dotColor' => 'bg-[#F59E0B]', 'label' => __('status.preparing')],

    'expired'     => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20', 'dotColor' => 'bg-[#EF4444]', 'label' => __('status.expired')],
    'rejected'    => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20', 'dotColor' => 'bg-[#EF4444]', 'label' => __('status.rejected')],
    'urgent'      => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20', 'dotColor' => 'bg-[#EF4444]', 'label' => __('status.urgent')],
    'overdue'     => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20', 'dotColor' => 'bg-[#EF4444]', 'label' => __('status.overdue')],
    'delayed'     => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]', 'border' => 'border-[#EF4444]/20', 'dotColor' => 'bg-[#EF4444]', 'label' => __('status.delayed')],

    'draft'       => ['bg' => 'bg-surface-2', 'text' => 'text-muted', 'border' => 'border-th-border', 'dotColor' => 'bg-muted', 'label' => __('status.draft')],
];
$s = $map[$status] ?? $map['draft'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $s['bg'] }} {{ $s['text'] }} border {{ $s['border'] }}">
    @if($dot)<span class="w-1.5 h-1.5 rounded-full {{ $s['dotColor'] }}"></span>@endif
    {{ $s['label'] }}
</span>
