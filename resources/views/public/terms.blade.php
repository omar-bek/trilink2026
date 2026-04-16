@extends('layouts.app')

@section('content')
<x-landing.navbar />

<div class="min-h-screen bg-page pt-28 pb-20">
    <div class="max-w-4xl mx-auto px-6 lg:px-10">
        <div class="mb-10">
            <h1 class="text-[36px] sm:text-[42px] font-bold text-primary leading-tight mb-3">{{ __('legal.terms_title') }}</h1>
            <p class="text-[14px] text-muted">{{ __('legal.last_updated') }}: {{ config('data_residency.privacy_policy_version', '2026-04-01') }}</p>
        </div>

        <div class="prose prose-invert max-w-none text-[14px] leading-relaxed text-muted [&_h2]:text-[20px] [&_h2]:font-bold [&_h2]:text-primary [&_h2]:mt-10 [&_h2]:mb-4 [&_h3]:text-[16px] [&_h3]:font-semibold [&_h3]:text-primary [&_h3]:mt-6 [&_h3]:mb-3 [&_p]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-4 [&_li]:mb-2 [&_strong]:text-primary [&_a]:text-accent [&_a]:hover:underline">

            <h2>1. Acceptance of Terms</h2>
            <p>By accessing or using the TriLink B2B Procurement Platform ("Platform"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree, do not use the Platform.</p>
            <p>These Terms constitute a legally binding agreement between you ("User") and TriLink Technologies LLC ("TriLink", "we", "us"), a company registered in the United Arab Emirates.</p>

            <h2>2. Platform Description</h2>
            <p>TriLink is a business-to-business electronic procurement platform that facilitates:</p>
            <ul>
                <li>Purchase requisitions and request for quotations (RFQs)</li>
                <li>Bid submission and evaluation</li>
                <li>Contract negotiation, execution, and digital signature</li>
                <li>Payment processing and escrow services</li>
                <li>Shipment tracking and logistics coordination</li>
                <li>Compliance monitoring and reporting</li>
            </ul>

            <h2>3. Eligibility & Registration</h2>
            <p>To use the Platform, you must:</p>
            <ul>
                <li>Be a legally registered business entity in your jurisdiction</li>
                <li>Hold a valid trade license (required under UAE Federal Decree-Law No. 50/2022)</li>
                <li>Provide accurate and complete registration information</li>
                <li>Maintain the confidentiality of your account credentials</li>
                <li>Be authorized to bind your company to these Terms</li>
            </ul>

            <h2>4. Company Verification</h2>
            <p>TriLink operates a tiered verification system. Companies must submit required documentation including trade licenses, tax registration certificates, and beneficial ownership disclosures. Verification levels affect access to platform features and transaction limits.</p>

            <h2>5. User Obligations</h2>
            <h3>5.1 Lawful Use</h3>
            <p>You agree to use the Platform only for lawful business purposes and in compliance with all applicable UAE federal laws, emirate-level regulations, and international trade laws.</p>
            <h3>5.2 Accurate Information</h3>
            <p>All information provided must be accurate, current, and complete. You must promptly update any changes to your company details, documents, or beneficial ownership structure.</p>
            <h3>5.3 Prohibited Conduct</h3>
            <p>You shall not:</p>
            <ul>
                <li>Engage in bid rigging, price fixing, or market allocation (Federal Decree-Law No. 36/2023)</li>
                <li>Submit false or misleading bids or documentation</li>
                <li>Circumvent platform security measures</li>
                <li>Use the Platform for money laundering or terrorist financing</li>
                <li>Violate sanctions or export control regulations</li>
            </ul>

            <h2>6. Transactions & Contracts</h2>
            <p>Contracts executed through the Platform are legally binding. Digital signatures comply with UAE Federal Decree-Law No. 46/2021 on Electronic Transactions and Trust Services. The Platform supports simple, advanced, and qualified signature grades.</p>

            <h2>7. Payment & Escrow</h2>
            <p>Payments processed through the Platform are subject to applicable VAT (currently 5%) under Federal Decree-Law No. 8/2017. Escrow services are provided through licensed banking partners. Platform fees are disclosed before each transaction.</p>

            <h2>8. Intellectual Property</h2>
            <p>The Platform, its design, code, and content are the intellectual property of TriLink Technologies LLC. Users retain ownership of their uploaded content but grant TriLink a limited license to display and process it within the Platform.</p>

            <h2>9. Data Protection</h2>
            <p>Your data is processed in accordance with UAE Federal Decree-Law No. 45/2021 (Personal Data Protection Law). Please refer to our <a href="{{ url('/privacy') }}">Privacy Policy</a> for details on data collection, processing, and your rights.</p>

            <h2>10. Limitation of Liability</h2>
            <p>TriLink provides the Platform "as is" and shall not be liable for indirect, consequential, or punitive damages. Our total liability shall not exceed the fees paid by you in the twelve (12) months preceding the claim.</p>

            <h2>11. Dispute Resolution</h2>
            <p>Any disputes arising from these Terms shall be resolved through arbitration in accordance with the rules of the Dubai International Arbitration Centre (DIAC). The seat of arbitration shall be Dubai, UAE, and the language shall be English.</p>

            <h2>12. Governing Law</h2>
            <p>These Terms are governed by and construed in accordance with the laws of the United Arab Emirates. For transactions involving DIFC or ADGM entities, the respective common-law frameworks shall apply.</p>

            <h2>13. Modifications</h2>
            <p>TriLink reserves the right to modify these Terms at any time. Material changes will be communicated via email and in-platform notification at least 30 days before taking effect.</p>

            <h2>14. Contact</h2>
            <p>For questions about these Terms, contact us at:</p>
            <ul>
                <li><strong>Email:</strong> legal@trilink.ae</li>
                <li><strong>Address:</strong> TriLink Technologies LLC, Dubai, United Arab Emirates</li>
            </ul>
        </div>
    </div>
</div>

<x-landing.footer />
@endsection
