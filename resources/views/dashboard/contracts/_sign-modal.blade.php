{{--
    Sign-contract confirmation modal. Replaces the legacy
    `confirm('Are you sure?')` JavaScript dialog with a proper UAE
    Federal Decree-Law 46/2021 — compliant signing flow:

      1. Shows the contract number, total value and the signing
         company's name so the user is forced to read what they're
         about to bind themselves to.
      2. Requires the user to re-enter their account password
         (step-up authentication — Article 18 demands a unique link
         between signature and signatory).
      3. Requires an explicit consent checkbox the user must tick
         before the submit button enables.
      4. Posts to dashboard.contracts.sign with both fields. The
         controller validates them server-side AND captures IP, UA
         and a SHA-256 hash of the contract terms into the signature
         row for the audit trail.

    Required props:
        $contract     — the show-page $contract array
        $signing_company_name — display name for the signing party
--}}
@props(['contract', 'signing_company_name'])

<div
    x-data="{ open: false, consent: false, password: '', showPassword: false }"
    x-on:open-sign-modal.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-cloak
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm"
        @click="open = false"
        aria-hidden="true"
    ></div>

    {{-- Dialog --}}
    <div
        x-show="open"
        x-transition
        class="fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sign-modal-title"
    >
        <div
            class="pointer-events-auto w-full max-w-lg bg-surface dark:bg-[#1a1d29] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-2xl shadow-2xl overflow-hidden"
            @click.stop
        >
            <div class="flex items-start justify-between gap-3 p-6 border-b border-th-border dark:border-[rgba(255,255,255,0.08)]">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl bg-[#00d9b5]/15 text-[#00d9b5] flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="sign-modal-title" class="text-[16px] font-bold text-primary dark:text-white">{{ __('contracts.sign_modal_title') }}</h3>
                        <p class="text-[12px] text-muted dark:text-[#b4b6c0] mt-1 leading-relaxed">{{ __('contracts.sign_modal_subtitle') }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="w-8 h-8 rounded-lg text-muted hover:text-primary dark:text-[#b4b6c0] dark:hover:text-white flex items-center justify-center flex-shrink-0"
                    aria-label="{{ __('common.close') }}"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('dashboard.contracts.sign', ['id' => $contract['numeric_id']]) }}"
                class="p-6 space-y-5"
                x-on:submit="if (!consent || !password) { $event.preventDefault(); }"
            >
                @csrf

                {{-- Contract summary card so the user reads what they
                     are about to commit to. --}}
                <div class="bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between text-[12px]">
                        <span class="text-muted dark:text-[#b4b6c0]">{{ __('contracts.contract_number') }}</span>
                        <span class="font-mono font-bold text-primary dark:text-white">{{ $contract['id'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[12px]">
                        <span class="text-muted dark:text-[#b4b6c0]">{{ __('contracts.total_value') }}</span>
                        <span class="font-bold text-[#00d9b5]">{{ $contract['amount'] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[12px]">
                        <span class="text-muted dark:text-[#b4b6c0]">{{ __('contracts.signing_as') }}</span>
                        <span class="font-bold text-primary dark:text-white truncate ms-2 max-w-[60%]" dir="auto">{{ $signing_company_name }}</span>
                    </div>
                </div>

                {{-- Password (step-up auth) --}}
                <div>
                    <label for="sign-password" class="block text-[12px] font-semibold text-primary dark:text-white mb-2">
                        {{ __('contracts.sign_password_label') }}
                    </label>
                    <div class="relative">
                        <input
                            id="sign-password"
                            type="password"
                            name="password"
                            x-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="w-full bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.1)] rounded-xl px-3 pe-11 h-11 text-[13px] text-primary dark:text-white focus:outline-none focus:border-accent/50"
                        >
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute end-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-muted dark:text-[#b4b6c0] hover:text-primary dark:hover:text-white flex items-center justify-center"
                            :aria-label="showPassword ? '{{ __('common.hide') }}' : '{{ __('common.show') }}'"
                        >
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
                    <p class="text-[11px] text-muted dark:text-[#b4b6c0] mt-1.5">{{ __('contracts.sign_password_hint') }}</p>
                </div>

                {{-- Explicit consent checkbox — Federal Decree-Law
                     46/2021 Article 18 requires unambiguous evidence
                     of intent. The submit button stays disabled
                     until both this AND the password are filled. --}}
                <label class="flex items-start gap-3 p-3 rounded-xl bg-page dark:bg-[#0f1117] border border-th-border dark:border-[rgba(255,255,255,0.1)] cursor-pointer">
                    <input
                        type="checkbox"
                        name="consent"
                        value="1"
                        x-model="consent"
                        required
                        class="mt-0.5 w-4 h-4 rounded border-th-border text-accent focus:ring-accent/40 flex-shrink-0"
                    >
                    <span class="text-[12px] text-body dark:text-[#b4b6c0] leading-[18px]">
                        {{ __('contracts.sign_consent_label', ['number' => $contract['id']]) }}
                    </span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="open = false"
                        class="px-4 py-2.5 rounded-xl text-[13px] font-semibold text-muted dark:text-[#b4b6c0] hover:text-primary dark:hover:text-white"
                    >
                        {{ __('contracts.amendment_cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="!consent || !password"
                        :class="(!consent || !password) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-[#00b894]'"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[13px] font-bold text-white bg-[#00d9b5] shadow-[0_4px_14px_rgba(0,217,181,0.25)]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 19.5h-15"/></svg>
                        {{ __('contracts.sign_contract') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
