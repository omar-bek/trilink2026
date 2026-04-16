@extends('layouts.app')

@section('content')
<x-landing.navbar />

<div class="min-h-screen bg-page pt-28 pb-20">
    <div class="max-w-4xl mx-auto px-6 lg:px-10">
        <div class="mb-10">
            <h1 class="text-[36px] sm:text-[42px] font-bold text-primary leading-tight mb-3">{{ __('legal.cookies_title') }}</h1>
            <p class="text-[14px] text-muted">{{ __('legal.last_updated') }}: {{ config('data_residency.privacy_policy_version', '2026-04-01') }}</p>
        </div>

        <div class="prose prose-invert max-w-none text-[14px] leading-relaxed text-muted [&_h2]:text-[20px] [&_h2]:font-bold [&_h2]:text-primary [&_h2]:mt-10 [&_h2]:mb-4 [&_h3]:text-[16px] [&_h3]:font-semibold [&_h3]:text-primary [&_h3]:mt-6 [&_h3]:mb-3 [&_p]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-4 [&_li]:mb-2 [&_strong]:text-primary [&_a]:text-accent [&_a]:hover:underline">

            <h2>1. What Are Cookies</h2>
            <p>Cookies are small text files stored on your device when you visit our Platform. They help us provide you with a better experience by remembering your preferences and understanding how you use the Platform.</p>

            <h2>2. Types of Cookies We Use</h2>

            <h3>2.1 Essential Cookies (Required)</h3>
            <p>These cookies are necessary for the Platform to function and cannot be disabled:</p>
            <ul>
                <li><strong>Session Cookie</strong> — maintains your authenticated session (120-minute expiry)</li>
                <li><strong>CSRF Token</strong> — prevents cross-site request forgery attacks</li>
                <li><strong>Theme Preference</strong> — remembers your dark/light mode choice</li>
                <li><strong>Locale</strong> — stores your language preference (English/Arabic)</li>
            </ul>

            <h3>2.2 Analytics Cookies (Optional)</h3>
            <p>With your consent, we may use analytics cookies to understand Platform usage patterns. These cookies do not contain personal data.</p>

            <h2>3. Managing Cookies</h2>
            <p>You can manage cookie preferences through our cookie banner shown on your first visit. You can also modify browser settings to block or delete cookies, though this may affect Platform functionality.</p>

            <h2>4. Third-Party Cookies</h2>
            <p>Our payment processors (Stripe, PayPal) may set their own cookies during checkout. These are governed by their respective privacy policies.</p>

            <h2>5. Data Protection</h2>
            <p>Cookie data is processed in compliance with UAE Federal Decree-Law No. 45/2021 (PDPL). For more information, see our <a href="{{ url('/privacy') }}">Privacy Policy</a>.</p>

            <h2>6. Contact</h2>
            <p>For questions about our cookie practices, contact our Data Protection Officer at <a href="mailto:dpo@trilink.ae">dpo@trilink.ae</a>.</p>
        </div>
    </div>
</div>

<x-landing.footer />
@endsection
