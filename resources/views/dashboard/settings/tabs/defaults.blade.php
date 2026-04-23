<h3 class="text-[18px] font-bold text-primary mb-1">{{ __('settings.defaults_title') }}</h3>
<p class="text-[13px] text-muted mb-6">{{ __('settings.defaults_subtitle') }}</p>

@unless($canManageDefaults)
<div class="mb-6 bg-[#ffc24d]/5 border border-[#ffc24d]/30 rounded-xl p-4 text-[13px] text-[#ffc24d]">
    {{ __('settings.manager_only_notice') }}
</div>
@endunless

<form method="POST" action="{{ route('settings.defaults.update') }}" class="space-y-6" @unless($canManageDefaults) onsubmit="return false" @endunless>
    @csrf
    @method('PATCH')
    <fieldset @unless($canManageDefaults) disabled class="opacity-60 pointer-events-none" @endunless class="space-y-6">

    {{-- Currency / fiscal basics --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.defaults_commercial') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.default_currency') }}</label>
                <select name="default_currency" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
                    @foreach(['AED','USD','EUR','GBP','SAR','QAR','KWD','OMR','BHD','INR','EGP'] as $c)
                        <option value="{{ $c }}" @selected(old('default_currency', $defaults->default_currency) === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.default_language') }}</label>
                <select name="default_language" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
                    <option value="en" @selected(old('default_language', $defaults->default_language) === 'en')>English</option>
                    <option value="ar" @selected(old('default_language', $defaults->default_language) === 'ar')>العربية</option>
                </select>
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.default_timezone') }}</label>
                <input type="text" name="default_timezone" value="{{ old('default_timezone', $defaults->default_timezone) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.fiscal_year_start') }}</label>
                <select name="fiscal_year_start_month" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
                    @foreach(range(1,12) as $m)
                        <option value="{{ $m }}" @selected((int) old('fiscal_year_start_month', $defaults->fiscal_year_start_month) === $m)>{{ \DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Tax --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.defaults_tax') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.default_vat_rate') }} (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="default_vat_rate" value="{{ old('default_vat_rate', $defaults->default_vat_rate) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.default_vat_treatment') }}</label>
                <select name="default_vat_treatment" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
                    @foreach(['standard','zero_rated','exempt','out_of_scope','reverse_charge'] as $t)
                        <option value="{{ $t }}" @selected(old('default_vat_treatment', $defaults->default_vat_treatment) === $t)>{{ __('settings.vat_'.$t) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Payment terms --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.defaults_payment_terms') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.payment_terms_days') }}</label>
                <input type="number" min="0" max="365" name="default_payment_terms_days" value="{{ old('default_payment_terms_days', $defaults->default_payment_terms_days) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.late_penalty_percent') }}</label>
                <input type="number" min="0" max="100" name="late_payment_penalty_percent" value="{{ old('late_payment_penalty_percent', $defaults->late_payment_penalty_percent) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary">
            </div>
        </div>
    </div>

    {{-- Approval thresholds --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.defaults_approvals') }}</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.contract_approval_threshold') }}</label>
                <input type="number" min="0" name="contract_approval_threshold_aed" value="{{ old('contract_approval_threshold_aed', $defaults->contract_approval_threshold_aed) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary"
                       placeholder="{{ __('settings.no_threshold') }}">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-primary mb-2">{{ __('settings.payment_dual_approval_threshold') }}</label>
                <input type="number" min="0" name="payment_dual_approval_threshold_aed" value="{{ old('payment_dual_approval_threshold_aed', $defaults->payment_dual_approval_threshold_aed) }}"
                       class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary"
                       placeholder="{{ __('settings.no_threshold') }}">
            </div>
        </div>
    </div>

    {{-- Procurement policy --}}
    <div>
        <h4 class="text-[14px] font-semibold text-primary mb-3">{{ __('settings.defaults_procurement') }}</h4>
        <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="require_three_quotes_above_threshold" value="1"
                       @checked(old('require_three_quotes_above_threshold', $defaults->require_three_quotes_above_threshold))
                       class="mt-1 w-4 h-4 rounded border-th-border text-accent">
                <div class="flex-1">
                    <span class="text-[13px] font-semibold text-primary">{{ __('settings.require_three_quotes') }}</span>
                    <div class="mt-2">
                        <input type="number" min="0" name="three_quotes_threshold_aed" value="{{ old('three_quotes_threshold_aed', $defaults->three_quotes_threshold_aed) }}"
                               class="w-64 bg-page border border-th-border rounded-xl px-4 py-2 text-[13px] text-primary"
                               placeholder="AED">
                    </div>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="prefer_local_suppliers" value="1"
                       @checked(old('prefer_local_suppliers', $defaults->prefer_local_suppliers))
                       class="w-4 h-4 rounded border-th-border text-accent">
                <span class="text-[13px] font-semibold text-primary">{{ __('settings.prefer_local') }}</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="require_icv_certificate" value="1"
                       @checked(old('require_icv_certificate', $defaults->require_icv_certificate))
                       class="w-4 h-4 rounded border-th-border text-accent">
                <span class="text-[13px] font-semibold text-primary">{{ __('settings.require_icv') }}</span>
            </label>
        </div>
    </div>

    <div class="pt-4 border-t border-th-border">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-semibold text-white bg-accent hover:bg-accent-h disabled:opacity-50" @unless($canManageDefaults) disabled @endunless>
            {{ __('settings.save') }}
        </button>
    </div>
    </fieldset>
</form>
