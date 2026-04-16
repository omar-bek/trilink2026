@extends('layouts.app')

@section('content')
<x-landing.navbar />

<div class="min-h-screen bg-page pt-28 pb-20">
    <div class="max-w-4xl mx-auto px-6 lg:px-10">

        <div class="text-center mb-12">
            <h1 class="text-[36px] sm:text-[42px] font-bold text-primary leading-tight mb-3">{{ __('contact.title') }}</h1>
            <p class="text-[14px] text-muted max-w-xl mx-auto">{{ __('contact.subtitle') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Contact form --}}
            <div class="bg-surface border border-th-border rounded-2xl p-8">
                <h2 class="text-[18px] font-bold text-primary mb-6">{{ __('contact.send_message') }}</h2>
                <form method="POST" action="{{ route('contact.store') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[12px] font-semibold text-muted mb-1.5">{{ __('contact.name') }}</label>
                            <input name="name" required class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-muted focus:border-accent/50 focus:ring-2 focus:ring-accent/15" placeholder="{{ __('contact.name_placeholder') }}" />
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-muted mb-1.5">{{ __('contact.email') }}</label>
                            <input name="email" type="email" required class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-muted focus:border-accent/50 focus:ring-2 focus:ring-accent/15" placeholder="{{ __('contact.email_placeholder') }}" />
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-muted mb-1.5">{{ __('contact.company') }}</label>
                            <input name="company" class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary placeholder:text-muted focus:border-accent/50 focus:ring-2 focus:ring-accent/15" placeholder="{{ __('contact.company_placeholder') }}" />
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-muted mb-1.5">{{ __('contact.subject') }}</label>
                            <select name="subject" required class="w-full bg-page border border-th-border rounded-xl px-4 h-12 text-[14px] text-primary">
                                <option value="general">{{ __('contact.subject_general') }}</option>
                                <option value="sales">{{ __('contact.subject_sales') }}</option>
                                <option value="support">{{ __('contact.subject_support') }}</option>
                                <option value="partnership">{{ __('contact.subject_partnership') }}</option>
                                <option value="compliance">{{ __('contact.subject_compliance') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-muted mb-1.5">{{ __('contact.message') }}</label>
                            <textarea name="message" required rows="5" class="w-full bg-page border border-th-border rounded-xl px-4 py-3 text-[14px] text-primary placeholder:text-muted focus:border-accent/50 focus:ring-2 focus:ring-accent/15 resize-none" placeholder="{{ __('contact.message_placeholder') }}"></textarea>
                        </div>
                        <button type="submit" class="w-full h-12 rounded-xl text-[14px] font-semibold text-white bg-accent hover:bg-accent-h shadow-[0_4px_14px_rgba(79,124,255,0.25)] transition-all hover:scale-[1.01]">
                            {{ __('contact.send') }}
                        </button>
                    </div>
                </form>
            </div>

            {{-- Contact info --}}
            <div class="space-y-6">
                <div class="bg-surface border border-th-border rounded-2xl p-8">
                    <h2 class="text-[18px] font-bold text-primary mb-6">{{ __('contact.info') }}</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold text-primary mb-0.5">{{ __('contact.email') }}</p>
                                <a href="mailto:info@trilink.ae" class="text-[14px] text-accent hover:underline">info@trilink.ae</a>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#00d9b5]/10 border border-[#00d9b5]/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#00d9b5]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold text-primary mb-0.5">{{ __('contact.phone') }}</p>
                                <a href="tel:+97145551234" class="text-[14px] text-muted hover:text-primary">+971 4 555 1234</a>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#ffb020]/10 border border-[#ffb020]/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#ffb020]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold text-primary mb-0.5">{{ __('contact.address') }}</p>
                                <p class="text-[14px] text-muted">Dubai, United Arab Emirates</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface border border-th-border rounded-2xl p-8">
                    <h3 class="text-[15px] font-bold text-primary mb-3">{{ __('contact.business_hours') }}</h3>
                    <div class="space-y-2 text-[13px]">
                        <div class="flex justify-between"><span class="text-muted">{{ __('contact.sun_thu') }}</span><span class="text-primary font-semibold">9:00 AM - 6:00 PM (GST)</span></div>
                        <div class="flex justify-between"><span class="text-muted">{{ __('contact.fri') }}</span><span class="text-primary font-semibold">9:00 AM - 12:00 PM (GST)</span></div>
                        <div class="flex justify-between"><span class="text-muted">{{ __('contact.sat') }}</span><span class="text-muted">{{ __('contact.closed') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-landing.footer />
@endsection
