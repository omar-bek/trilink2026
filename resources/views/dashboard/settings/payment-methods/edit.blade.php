@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.payment_methods_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.payment_methods_title')" :subtitle="__('settings.payment_methods_subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <form method="POST" action="{{ route('settings.payment-methods.update') }}">
        @csrf @method('PATCH')

        <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="text-muted border-b border-th-border">
                <tr>
                    <th class="text-start py-2">{{ __('settings.rail') }}</th>
                    <th class="py-2">{{ __('settings.accept_incoming') }}</th>
                    <th class="py-2">{{ __('settings.allow_outgoing') }}</th>
                    <th class="py-2">{{ __('settings.min_aed') }}</th>
                    <th class="py-2">{{ __('settings.max_aed') }}</th>
                    <th class="py-2">{{ __('settings.preferred_above') }}</th>
                    <th class="py-2">{{ __('settings.dual_approval') }}</th>
                    <th class="py-2">{{ __('settings.receiving_account') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rails as $rail)
                @php $cfg = $existing[$rail->value] ?? null; @endphp
                <tr class="border-b border-th-border/50">
                    <td class="py-3 font-semibold text-primary">
                        {{ __('settings.rail_'.$rail->value) }}
                        <div class="text-[11px] text-muted">{{ $rail->value }}</div>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="rails[{{ $rail->value }}][accept_incoming]" value="1"
                               @checked(old("rails.{$rail->value}.accept_incoming", $cfg?->accept_incoming ?? true))
                               class="rounded text-accent">
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="rails[{{ $rail->value }}][allow_outgoing]" value="1"
                               @checked(old("rails.{$rail->value}.allow_outgoing", $cfg?->allow_outgoing ?? true))
                               class="rounded text-accent">
                    </td>
                    <td class="py-2">
                        <input type="number" min="0" name="rails[{{ $rail->value }}][min_amount_aed]"
                               value="{{ old("rails.{$rail->value}.min_amount_aed", $cfg?->min_amount_aed) }}"
                               class="w-24 bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                    </td>
                    <td class="py-2">
                        <input type="number" min="0" name="rails[{{ $rail->value }}][max_amount_aed]"
                               value="{{ old("rails.{$rail->value}.max_amount_aed", $cfg?->max_amount_aed) }}"
                               class="w-24 bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                    </td>
                    <td class="py-2">
                        <input type="number" min="0" name="rails[{{ $rail->value }}][preferred_above_aed]"
                               value="{{ old("rails.{$rail->value}.preferred_above_aed", $cfg?->preferred_above_aed) }}"
                               class="w-24 bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="rails[{{ $rail->value }}][require_dual_approval]" value="1"
                               @checked(old("rails.{$rail->value}.require_dual_approval", $cfg?->require_dual_approval))
                               class="rounded text-accent">
                    </td>
                    <td class="py-2">
                        <select name="rails[{{ $rail->value }}][receiving_account_id]"
                                class="bg-page border border-th-border rounded px-2 py-1 text-[12px]">
                            <option value="">—</option>
                            @foreach($receivingAccounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old("rails.{$rail->value}.receiving_account_id", $cfg?->receiving_account_id) == $acc->id)>
                                    {{ $acc->label }} ({{ $acc->currency }})
                                </option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>

        <div class="pt-5 mt-5 border-t border-th-border">
            <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">
                {{ __('settings.save') }}
            </button>
        </div>
    </form>
</div>
@endsection
