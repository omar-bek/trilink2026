@php
$prefs = ($user->custom_permissions['notifications'] ?? []);
$items = [
    ['name' => 'rfq_matches',         'label' => 'New RFQ Matches',         'desc' => 'Get notified when new RFQs match your product categories', 'default' => true],
    ['name' => 'bid_updates',         'label' => 'Bid Status Updates',      'desc' => 'Receive updates when your bid status changes',              'default' => true],
    ['name' => 'contract_milestones', 'label' => 'Contract Milestones',     'desc' => 'Notifications for contract deliveries and payments',         'default' => true],
    ['name' => 'messages',            'label' => 'Messages from Buyers',   'desc' => 'Get notified when buyers send you messages',                'default' => true],
    ['name' => 'marketing',           'label' => 'Marketing & Updates',    'desc' => 'Platform updates and promotional content',                  'default' => false],
];
@endphp

<h3 class="text-[18px] font-bold text-primary mb-6">Notification Preferences</h3>

<form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-4">
    @csrf
    @method('PATCH')

    @foreach($items as $item)
    @php $checked = array_key_exists($item['name'], $prefs) ? (bool) $prefs[$item['name']] : $item['default']; @endphp
    <label class="flex items-start gap-4 p-5 rounded-xl bg-page border border-th-border cursor-pointer hover:border-accent/30 transition-colors">
        <div class="flex-1">
            <p class="text-[14px] font-bold text-primary">{{ $item['label'] }}</p>
            <p class="text-[12px] text-muted mt-1">{{ $item['desc'] }}</p>
        </div>
        <div class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="{{ $item['name'] }}" value="1" class="sr-only peer" {{ $checked ? 'checked' : '' }}>
            <div class="w-11 h-6 bg-elevated peer-checked:bg-accent rounded-full peer transition-colors"></div>
            <div class="absolute top-0.5 start-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></div>
        </div>
    </label>
    @endforeach

    <div class="pt-4">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(37,99,235,0.25)]">
            Save Preferences
        </button>
    </div>
</form>
