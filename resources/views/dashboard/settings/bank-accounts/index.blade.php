@extends('layouts.dashboard', ['active' => 'settings'])
@section('title', __('settings.bank_accounts_title'))

@section('content')
<x-dashboard.page-header :title="__('settings.bank_accounts_title')" :subtitle="__('settings.bank_accounts_subtitle')" />

@if(session('status'))
<div class="mb-6 bg-[#00d9b5]/5 border border-[#00d9b5]/30 rounded-xl p-4 text-[13px] text-[#00d9b5]">{{ session('status') }}</div>
@endif

@if($errors->any())
<div class="mb-6 bg-[#ff4d7f]/5 border border-[#ff4d7f]/30 rounded-xl p-4 text-[13px] text-[#ff4d7f]">
    <ul class="list-disc ms-5 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="bg-surface border border-th-border rounded-2xl p-6">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-[16px] font-bold text-primary">{{ __('settings.existing_accounts') }}</h3>
        <a href="{{ route('settings.index') }}" class="text-[13px] text-muted hover:text-primary">← {{ __('settings.back_to_settings') }}</a>
    </div>

    @if($accounts->isEmpty())
        <p class="text-[13px] text-muted mb-5">{{ __('settings.no_bank_accounts') }}</p>
    @else
    <div class="overflow-x-auto mb-6">
        <table class="w-full text-[13px]">
            <thead class="text-muted border-b border-th-border">
                <tr>
                    <th class="text-start py-2">{{ __('settings.label') }}</th>
                    <th class="text-start py-2">{{ __('settings.bank') }}</th>
                    <th class="text-start py-2">IBAN</th>
                    <th class="text-start py-2">SWIFT</th>
                    <th class="text-start py-2">{{ __('settings.currency') }}</th>
                    <th class="text-start py-2">{{ __('settings.roles') }}</th>
                    <th class="text-end py-2"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($accounts as $a)
                <tr class="border-b border-th-border/50">
                    <td class="py-3 font-semibold text-primary">{{ $a->label }}</td>
                    <td class="py-3 text-primary">{{ $a->bank_name }}</td>
                    <td class="py-3 text-muted font-mono">{{ $a->iban }}</td>
                    <td class="py-3 text-muted font-mono">{{ $a->swift }}</td>
                    <td class="py-3 text-primary">{{ $a->currency }}</td>
                    <td class="py-3 space-x-1">
                        @if($a->is_default_receiving)<span class="text-[11px] bg-accent/10 text-accent rounded-full px-2 py-0.5">{{ __('settings.default_receiving') }}</span>@endif
                        @if($a->is_default_payout)<span class="text-[11px] bg-[#00d9b5]/10 text-[#00d9b5] rounded-full px-2 py-0.5">{{ __('settings.default_payout') }}</span>@endif
                        @if($a->is_wps_account)<span class="text-[11px] bg-[#ffc24d]/10 text-[#ffc24d] rounded-full px-2 py-0.5">WPS</span>@endif
                        @if($a->is_tax_account)<span class="text-[11px] bg-[#ff4d7f]/10 text-[#ff4d7f] rounded-full px-2 py-0.5">{{ __('settings.tax') }}</span>@endif
                    </td>
                    <td class="py-3 text-end">
                        <form method="POST" action="{{ route('settings.bank-accounts.destroy', $a->id) }}" class="inline"
                              onsubmit="return confirm('{{ __('settings.confirm_delete') }}')">
                            @csrf @method('DELETE')
                            <button class="text-[#ff4d7f] text-[12px] hover:underline">{{ __('settings.delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <h4 class="text-[14px] font-bold text-primary mb-3">{{ __('settings.add_bank_account') }}</h4>
    <form method="POST" action="{{ route('settings.bank-accounts.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @csrf
        <div>
            <label class="block text-[12px] text-muted mb-1">{{ __('settings.label') }}</label>
            <input type="text" name="label" required class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">{{ __('settings.currency') }}</label>
            <select name="currency" required class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
                @foreach(['AED','USD','EUR','GBP','SAR','QAR','KWD','OMR','BHD','INR','EGP'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">{{ __('settings.holder_name') }}</label>
            <input type="text" name="holder_name" required class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">{{ __('settings.bank') }}</label>
            <input type="text" name="bank_name" required class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary">
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">IBAN</label>
            <input type="text" name="iban" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary font-mono">
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">SWIFT/BIC</label>
            <input type="text" name="swift" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary font-mono">
        </div>
        <div>
            <label class="block text-[12px] text-muted mb-1">{{ __('settings.account_number') }}</label>
            <input type="text" name="account_number" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[13px] text-primary font-mono">
        </div>
        <div class="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
            <label class="flex items-center gap-2 text-[12px] text-primary">
                <input type="checkbox" name="is_default_receiving" value="1" class="rounded text-accent"> {{ __('settings.default_receiving') }}
            </label>
            <label class="flex items-center gap-2 text-[12px] text-primary">
                <input type="checkbox" name="is_default_payout" value="1" class="rounded text-accent"> {{ __('settings.default_payout') }}
            </label>
            <label class="flex items-center gap-2 text-[12px] text-primary">
                <input type="checkbox" name="is_wps_account" value="1" class="rounded text-accent"> WPS
            </label>
            <label class="flex items-center gap-2 text-[12px] text-primary">
                <input type="checkbox" name="is_tax_account" value="1" class="rounded text-accent"> {{ __('settings.tax_account') }}
            </label>
        </div>
        <div class="md:col-span-2">
            <button class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h">{{ __('settings.add_bank_account') }}</button>
        </div>
    </form>
</div>
@endsection
