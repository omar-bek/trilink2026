@props(['status' => 'open', 'dot' => true])

@php
$map = [
    'open'        => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.open')],
    'active'      => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.active')],
    'completed'   => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.completed')],
    'paid'        => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.paid')],
    'resolved'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.resolved')],
    'accepted'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.accepted')],
    'approved'    => ['bg' => 'bg-[#00d9b5]/10', 'text' => 'text-[#00d9b5]', 'border' => 'border-[#00d9b5]/20', 'dotColor' => 'bg-[#00d9b5]', 'label' => __('status.approved')],
    'delivered'   => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.delivered')],

    'in_transit'  => ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'border' => 'border-[#4f7cff]/20', 'dotColor' => 'bg-[#4f7cff]', 'label' => __('status.in_transit') ?? 'In Transit'],
    'submitted'   => ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'border' => 'border-[#4f7cff]/20', 'dotColor' => 'bg-[#4f7cff]', 'label' => __('status.submitted')],
    'in_progress' => ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'border' => 'border-[#4f7cff]/20', 'dotColor' => 'bg-[#4f7cff]', 'label' => __('status.in_progress')],
    'in_mediation'=> ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'border' => 'border-[#4f7cff]/20', 'dotColor' => 'bg-[#4f7cff]', 'label' => __('status.in_mediation')],
    'under_review'=> ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.under_review')],
    'out_for_delivery' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]', 'border' => 'border-[#8B5CF6]/20', 'dotColor' => 'bg-[#8B5CF6]', 'label' => __('status.out_for_delivery')],

    'pending'     => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.pending')],
    'closed'      => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.closed')],
    'shortlisted' => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.shortlisted')],
    'due_soon'    => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.due_soon')],
    'scheduled'   => ['bg' => 'bg-[#4f7cff]/10', 'text' => 'text-[#4f7cff]', 'border' => 'border-[#4f7cff]/20', 'dotColor' => 'bg-[#4f7cff]', 'label' => __('status.scheduled')],
    'at_customs'  => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.at_customs')],
    'preparing'   => ['bg' => 'bg-[#ffb020]/10', 'text' => 'text-[#ffb020]', 'border' => 'border-[#ffb020]/20', 'dotColor' => 'bg-[#ffb020]', 'label' => __('status.preparing')],

    'expired'     => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.expired')],
    'inactive'    => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.inactive')],
    'rejected'    => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.rejected')],
    'urgent'      => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.urgent')],
    'overdue'     => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.overdue')],
    'delayed'     => ['bg' => 'bg-[#ff4d7f]/10', 'text' => 'text-[#ff4d7f]', 'border' => 'border-[#ff4d7f]/20', 'dotColor' => 'bg-[#ff4d7f]', 'label' => __('status.delayed')],

    'draft'       => ['bg' => 'bg-surface-2', 'text' => 'text-muted', 'border' => 'border-th-border', 'dotColor' => 'bg-muted', 'label' => __('status.draft')],
];
$s = $map[$status] ?? $map['draft'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $s['bg'] }} {{ $s['text'] }} border {{ $s['border'] }}">
    @if($dot)<span class="w-1.5 h-1.5 rounded-full {{ $s['dotColor'] }}"></span>@endif
    {{ $s['label'] }}
</span>
