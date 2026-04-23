@php
    // Same tab template handles two flows depending on role:
    //  - Supplier  → bank account form (where to RECEIVE payments)
    //  - Buyer     → outgoing payment methods list (how to PAY suppliers)
    $role = auth()->user()?->role?->value ?? 'buyer';
    $isSupplier = in_array($role, ['supplier', 'service_provider', 'logistics', 'clearance'], true);
    // Existing bank details now live on the typed `company_bank_details`
    // table (Phase 0 / task 0.6). Map columns back to the legacy `bank_*`
    // keys the form already uses so the markup didn't have to change shape.
    $bankRow = auth()->user()?->company?->bankDetails;
    $bank = $bankRow ? [
        'bank_holder'         => $bankRow->holder_name,
        'bank_name'           => $bankRow->bank_name,
        'bank_iban'           => $bankRow->iban,
        'bank_swift'          => $bankRow->swift,
        'bank_account_number' => $bankRow->notes,
        'bank_currency'       => $bankRow->currency,
    ] : [];
@endphp

@if($isSupplier)
    <h3 class="text-[20px] font-bold text-primary mb-1">{{ __('settings.bank_account') ?? 'Receiving Bank Account' }}</h3>
    <p class="text-[14px] text-muted mb-6">{{ __('settings.bank_account_hint') ?? 'Where TriLink will deposit funds when buyers settle your invoices.' }}</p>

    @unless($canManageBilling)
    <div class="mb-6 bg-[#ffc24d]/5 border border-[#ffc24d]/30 rounded-xl p-4 text-[13px] text-[#ffc24d]">
        {{ __('settings.manager_only_billing_notice') }}
    </div>
    @endunless

    <form method="POST" action="{{ route('settings.payment.update') }}" class="space-y-4" @unless($canManageBilling) onsubmit="return false" @endunless>
        @csrf
        @method('PATCH')
        <fieldset @unless($canManageBilling) disabled class="opacity-60 pointer-events-none" @endunless class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[13px] text-muted mb-1.5">Account Holder Name</label>
                <input type="text" name="bank_holder" value="{{ old('bank_holder', $bank['bank_holder'] ?? auth()->user()?->company?->name) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 transition-colors">
            </div>
            <div>
                <label class="block text-[13px] text-muted mb-1.5">Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g., Emirates NBD"
                       value="{{ old('bank_name', $bank['bank_name'] ?? '') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 transition-colors">
            </div>
            <div>
                <label class="block text-[13px] text-muted mb-1.5">IBAN</label>
                <input type="text" name="bank_iban" placeholder="AE07 0331 2345 6789 0123 456"
                       value="{{ old('bank_iban', $bank['bank_iban'] ?? '') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 transition-colors">
            </div>
            <div>
                <label class="block text-[13px] text-muted mb-1.5">SWIFT / BIC</label>
                <input type="text" name="bank_swift" placeholder="EBILAEAD"
                       value="{{ old('bank_swift', $bank['bank_swift'] ?? '') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 transition-colors">
            </div>
            <div>
                <label class="block text-[13px] text-muted mb-1.5">Account Number</label>
                <input type="text" name="bank_account_number" placeholder="****4567"
                       value="{{ old('bank_account_number', $bank['bank_account_number'] ?? '') }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50 transition-colors">
            </div>
            <div>
                <label class="block text-[13px] text-muted mb-1.5">Currency</label>
                <select name="bank_currency" class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary focus:outline-none focus:border-accent/50">
                    @foreach(['AED', 'USD', 'EUR', 'SAR'] as $cur)
                        <option value="{{ $cur }}" @selected(old('bank_currency', $bank['bank_currency'] ?? 'AED') === $cur)>{{ $cur }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[14px] font-medium text-white bg-accent hover:bg-accent-h transition-colors disabled:opacity-50 disabled:cursor-not-allowed" @unless($canManageBilling) disabled @endunless>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Save Bank Details
        </button>
        </fieldset>
    </form>

@else
    <h3 class="text-[20px] font-bold text-primary mb-6">{{ __('settings.payment_methods') }}</h3>

    {{-- Bank Transfer card — visually mirrors the Figma payment-method row:
         solid blue BANK chip + name + masked account + Default pill. --}}
    <div class="bg-page border border-th-border rounded-xl p-5 flex items-center gap-3 mb-4">
        <div class="w-12 h-8 rounded bg-accent flex items-center justify-center flex-shrink-0">
            <span class="text-[12px] font-bold text-white tracking-wide">BANK</span>
        </div>

        <div class="flex-1 min-w-0">
            <p class="text-[16px] font-medium text-primary leading-tight">Bank Transfer</p>
            <p class="text-[14px] text-muted mt-0.5">Emirates NBD &middot; ****4567</p>
        </div>

        <span class="text-[12px] text-[#00d9b5] bg-[#00d9b5]/10 rounded-full px-2.5 py-1 flex-shrink-0">Default</span>
    </div>

    <button type="button" class="w-full border-2 border-dashed border-th-border rounded-xl py-4 text-[16px] font-medium text-muted hover:text-primary hover:border-accent/40 transition-colors">
        + Add Payment Method
    </button>
@endif
