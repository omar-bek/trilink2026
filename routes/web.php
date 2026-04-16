<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Public\SignatureVerifyController;
use App\Http\Controllers\Web\Admin\AntiCollusionController;
use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\BlacklistController as AdminBlacklistController;
use App\Http\Controllers\Web\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Web\Admin\CertificateUploadAdminController as AdminCertificateUploadController;
use App\Http\Controllers\Web\Admin\CompanyCategoryRequestController as AdminCompanyCategoryRequestController;
use App\Http\Controllers\Web\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Web\Admin\DisputeManagementController as AdminDisputeManagementController;
use App\Http\Controllers\Web\Admin\EInvoiceController as AdminEInvoiceController;
use App\Http\Controllers\Web\Admin\ExchangeRateController as AdminExchangeRateController;
use App\Http\Controllers\Web\Admin\FeeController as AdminFeeController;
use App\Http\Controllers\Web\Admin\IcvCertificateAdminController as AdminIcvCertificateController;
use App\Http\Controllers\Web\Admin\OversightController as AdminOversightController;
use App\Http\Controllers\Web\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Web\Admin\TaxInvoiceController as AdminTaxInvoiceController;
use App\Http\Controllers\Web\Admin\TaxRateController as AdminTaxRateController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Admin\VerificationQueueController as AdminVerificationQueueController;
use App\Http\Controllers\Web\Admin\WebhookManagementController as AdminWebhookManagementController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AIController;
use App\Http\Controllers\Web\ApiTokenController;
use App\Http\Controllers\Web\AuctionController;
use App\Http\Controllers\Web\Auth\AuthController as WebAuthController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\BeneficialOwnerController;
use App\Http\Controllers\Web\BidController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CompanyDocumentController;
use App\Http\Controllers\Web\CompanyInsuranceController;
use App\Http\Controllers\Web\CompanyProfileController;
use App\Http\Controllers\Web\CompanySupplierController;
use App\Http\Controllers\Web\CompanyUserController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\Contract\AmendmentController;
use App\Http\Controllers\Web\Contract\AnalyticsController;
use App\Http\Controllers\Web\Contract\SigningController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisputeController;
use App\Http\Controllers\Web\EscrowController;
use App\Http\Controllers\Web\EsgController;
use App\Http\Controllers\Web\FeedbackController;
use App\Http\Controllers\Web\GlobalSearchController;
use App\Http\Controllers\Web\GovernmentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\IcvCertificateController;
use App\Http\Controllers\Web\IntegrationsController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\LogisticsController;
use App\Http\Controllers\Web\NegotiationController;
use App\Http\Controllers\Web\NegotiationRoundController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PerformanceController;
use App\Http\Controllers\Web\PrivacyController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PurchaseRequestController;
use App\Http\Controllers\Web\RfqController;
use App\Http\Controllers\Web\SavedSearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\ShipmentController;
use App\Http\Controllers\Web\ShippingQuoteController;
use App\Http\Controllers\Web\SpendAnalyticsController;
use App\Http\Controllers\Web\SupplierProfileController;
use App\Http\Controllers\Web\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

// Public "Demo" walkthrough — explains the platform end-to-end to visitors.
// No auth required; linked from the landing navbar.
Route::view('/demo', 'public.demo')->name('public.demo');

// Health probes for load balancers / k8s / uptime monitors. Must be
// unauthenticated and NOT behind any middleware that touches DB/session
// (the whole point is to answer even when the app is degraded).
Route::get('/health', [HealthController::class, 'liveness'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'readiness'])->name('health.ready');

// Phase 1 / task 1.14 — public "Browse Suppliers" landing page (no auth).
// SEO + lead generation. Cards link to the gated profile page; clicking
// pops the visitor into the login flow with `?intended=` set.
Route::get('/suppliers', [SupplierProfileController::class, 'publicDirectory'])->name('public.suppliers');

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:5,15')->name('login.attempt');
});

// Register form + submission are guest-only. After Auth::login() at the end
// of the register flow the user's session is regenerated; if they then hit
// Back or refresh /register, the rendered form would carry a stale CSRF
// token and the next POST would 419. Bouncing logged-in users to the
// success page avoids that whole class of error.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,60')->name('register.submit');
});
Route::get('/register/success', [RegisterController::class, 'showSuccess'])->name('register.success');
// Submit additional info that an admin asked for after the initial submission.
// Only reachable while authenticated, but a guest who hits it just gets a 403.
Route::post('/register/info', [RegisterController::class, 'submitInfo'])->middleware('auth')->name('register.submit-info');
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

// ──── Phase 2 (UAE Compliance Roadmap) — public PDPL pages ────────────
// Privacy policy + DPA + cookie consent endpoint. Open to everyone
// (logged-in or not) so the cookie banner can record consent before
// the visitor signs up. PDPL Article 6 — consent must be obtainable
// without forcing the data subject to authenticate first.
Route::get('/privacy', [PrivacyController::class, 'showPolicy'])->name('public.privacy');
Route::get('/data-processing-agreement', [PrivacyController::class, 'showDpa'])->name('public.dpa');
Route::post('/privacy/cookies', [PrivacyController::class, 'recordCookieConsent'])->name('public.privacy.cookies');

// ──── Public legal & info pages ─────────────────────────────────────────
Route::view('/terms', 'public.terms')->name('public.terms');
Route::view('/cookies', 'public.cookies')->name('public.cookies');
Route::view('/about', 'public.about')->name('public.about');
Route::get('/contact', fn () => view('public.contact'))->name('public.contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:3,10')->name('contact.store');

// ──── 2FA challenge (post-login TOTP verification) ──────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', fn () => view('auth.two-factor-challenge'))->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [WebAuthController::class, 'twoFactorChallenge'])->middleware('throttle:5,5')->name('two-factor.challenge.verify');
});

// Phase 6 (UAE Compliance Roadmap) — public signature verification.
// Anyone holding the contract URL (an inspector, court clerk, opposing
// counsel) can recompute the canonical hash and inspect the signature
// audit trail WITHOUT authenticating. Federal Decree-Law 46/2021
// Article 23 — independent verifiability of electronic signatures.
Route::get('/contracts/{id}/verify', [SignatureVerifyController::class, 'show'])
    ->name('public.contracts.verify')
    ->where('id', '[0-9]+');

// Password reset (Laravel default broker uses these route names)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:3,15')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,15')->name('password.update');
});

// Profile (any authenticated user). Also gated by company.approved so a
// pending-approval user can't slip into profile/settings/notifications —
// only register.success and logout are reachable for them.
Route::middleware(['auth', 'company.approved'])->group(function () {
    // Notifications feed
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,15')->name('profile.password');
    Route::post('/profile/company-logo', [ProfileController::class, 'updateCompanyLogo'])->name('profile.company-logo');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotificationPreferences'])->name('profile.notifications');

    // Tabbed Settings page (Company / Personal / Notifications / Security / Payment)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company.update');
    Route::patch('/settings/personal', [SettingsController::class, 'updatePersonal'])->name('settings.personal.update');
    Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::patch('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security.update');
    Route::patch('/settings/payment', [SettingsController::class, 'updatePayment'])->name('settings.payment.update');

    // ──── Phase 4 (UAE Compliance Roadmap) — ICV certificates ─────
    // Supplier-side self-service: list, upload, delete (pending only),
    // and download own certificates. Verification is admin-only.
    Route::get('/dashboard/icv-certificates', [IcvCertificateController::class, 'index'])->name('dashboard.icv-certificates.index');
    Route::get('/dashboard/icv-certificates/create', [IcvCertificateController::class, 'create'])->name('dashboard.icv-certificates.create');
    Route::post('/dashboard/icv-certificates', [IcvCertificateController::class, 'store'])->name('dashboard.icv-certificates.store');
    Route::get('/dashboard/icv-certificates/{id}/download', [IcvCertificateController::class, 'download'])->name('dashboard.icv-certificates.download');
    Route::delete('/dashboard/icv-certificates/{id}', [IcvCertificateController::class, 'destroy'])->name('dashboard.icv-certificates.destroy');

    // ──── Phase 2 (UAE Compliance Roadmap) — PDPL dashboard ────────
    // Self-service privacy hub: consent log, DSAR (data export), and
    // erasure scheduling. Auth-only because everything here is the
    // user acting on their own personal data record.
    Route::get('/dashboard/privacy', [PrivacyController::class, 'dashboard'])->name('dashboard.privacy.index');
    Route::post('/dashboard/privacy/export', [PrivacyController::class, 'requestExport'])->name('dashboard.privacy.export');
    Route::get('/dashboard/privacy/export/{id}/download', [PrivacyController::class, 'downloadExport'])->name('dashboard.privacy.export.download');
    Route::post('/dashboard/privacy/erasure', [PrivacyController::class, 'requestErasure'])->name('dashboard.privacy.erasure');
    Route::post('/dashboard/privacy/erasure/{id}/cancel', [PrivacyController::class, 'cancelErasure'])->name('dashboard.privacy.erasure.cancel');
    Route::post('/dashboard/privacy/consents/{type}/grant', [PrivacyController::class, 'grantConsent'])->name('dashboard.privacy.consents.grant');
    Route::post('/dashboard/privacy/consents/{type}/withdraw', [PrivacyController::class, 'withdrawConsent'])->name('dashboard.privacy.consents.withdraw');

    // Two-Factor Authentication (TOTP) — self-service enable/disable.
    Route::get('/two-factor', [TwoFactorController::class, 'setup'])->name('dashboard.two-factor.setup');
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('dashboard.two-factor.enable');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('dashboard.two-factor.disable');
    Route::post('/two-factor/recovery', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('dashboard.two-factor.recovery');

    // Performance Dashboard
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
});

// Government console (top-level so the URL is /gov, not /dashboard/gov)
Route::middleware(['auth', 'web.role:government,admin'])->prefix('gov')->name('gov.')->group(function () {
    Route::get('/', [GovernmentController::class, 'index'])->name('index');
    Route::get('/contracts', [GovernmentController::class, 'contracts'])->name('contracts');
    Route::get('/payments', [GovernmentController::class, 'payments'])->name('payments');
    Route::get('/icv-report', [GovernmentController::class, 'icvReport'])->name('icv-report');
    Route::get('/competition', [GovernmentController::class, 'competition'])->name('competition');
    Route::get('/collusion-report', [GovernmentController::class, 'collusionReport'])->name('collusion-report');
    Route::get('/esg-report', [GovernmentController::class, 'esgReport'])->name('esg-report');
    Route::get('/sanctions-report', [GovernmentController::class, 'sanctionsReport'])->name('sanctions-report');
    Route::get('/sme-report', [GovernmentController::class, 'smeReport'])->name('sme-report');
    Route::get('/disputes', [GovernmentController::class, 'disputes'])->name('disputes');
    Route::get('/export', [GovernmentController::class, 'export'])->name('export');
});

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'company.approved'])->prefix('dashboard')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Company Users --------------------------------------------------------
    // Company manager/admin manage team members in their tenant.
    Route::middleware('web.role:company_manager,admin')->group(function () {
        Route::get('/company/users', [CompanyUserController::class, 'index'])->name('company.users');
        Route::get('/company/users/create', [CompanyUserController::class, 'create'])->name('company.users.create');
        Route::post('/company/users', [CompanyUserController::class, 'store'])->name('company.users.store');
        Route::get('/company/users/{id}/edit', [CompanyUserController::class, 'edit'])->name('company.users.edit');
        Route::patch('/company/users/{id}', [CompanyUserController::class, 'update'])->name('company.users.update');
        Route::post('/company/users/{id}/toggle', [CompanyUserController::class, 'toggleStatus'])->name('company.users.toggle');
        Route::post('/company/users/{id}/reset', [CompanyUserController::class, 'resetPassword'])->name('company.users.reset');
        Route::delete('/company/users/{id}', [CompanyUserController::class, 'destroy'])->name('company.users.destroy');
    });

    // ---- Company Profile ------------------------------------------------------
    // The unified company profile page — every company-attached user
    // can view it (gated by team.view inside the controller); editing
    // is gated by team.manage so only company managers can save.
    Route::get('/company/profile', [CompanyProfileController::class, 'show'])->name('dashboard.company.profile');
    Route::patch('/company/profile', [CompanyProfileController::class, 'update'])->name('dashboard.company.profile.update');
    Route::post('/company/profile/logo', [CompanyProfileController::class, 'uploadLogo'])->name('dashboard.company.profile.logo');
    // Authorised signature image + company stamp/seal upload. Both
    // assets gate the contract sign action — the contract show page
    // pushes users here via an inline modal when either is missing.
    Route::post('/company/profile/signature', [CompanyProfileController::class, 'uploadSignature'])->name('dashboard.company.profile.signature');

    // Category assignment requests — manager proposes, admin approves.
    Route::post('/company/profile/categories/request', [CompanyProfileController::class, 'requestCategory'])->name('dashboard.company.profile.categories.request');
    Route::delete('/company/profile/categories/request/{id}', [CompanyProfileController::class, 'cancelCategoryRequest'])->name('dashboard.company.profile.categories.cancel');

    // ---- Admin ---------------------------------------------------------------
    Route::middleware('web.role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        // Users
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{id}/toggle', [AdminUserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{id}/reset', [AdminUserController::class, 'resetPassword'])->name('users.reset');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Companies
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        // Bulk routes must be declared before /{id} so literal segments are
        // not captured as an id.
        Route::post('/companies/bulk/rescreen', [AdminCompanyController::class, 'bulkRescreen'])->name('companies.bulk-rescreen');
        // Admin company detail — renders the unified Company Profile
        // blade in admin mode (verify/reject + verification-level
        // selector enabled). The legacy /companies/{id}/profile alias
        // was removed; show() is the single entry point now.
        Route::get('/companies/{id}', [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::get('/companies/{id}/edit', [AdminCompanyController::class, 'edit'])->name('companies.edit');
        Route::patch('/companies/{id}', [AdminCompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{id}/approve', [AdminCompanyController::class, 'approve'])->name('companies.approve');
        Route::post('/companies/{id}/request-info', [AdminCompanyController::class, 'requestInfo'])->name('companies.request-info');
        Route::delete('/companies/{id}/request-info', [AdminCompanyController::class, 'cancelInfoRequest'])->name('companies.cancel-info');
        Route::post('/companies/{id}/reject', [AdminCompanyController::class, 'reject'])->name('companies.reject');
        Route::post('/companies/{id}/suspend', [AdminCompanyController::class, 'suspend'])->name('companies.suspend');
        Route::post('/companies/{id}/reactivate', [AdminCompanyController::class, 'reactivate'])->name('companies.reactivate');
        Route::post('/companies/{id}/verification', [AdminCompanyController::class, 'setVerificationLevel'])->name('companies.set-verification');
        Route::post('/companies/{id}/rescreen', [AdminCompanyController::class, 'rescreenSanctions'])->name('companies.rescreen');
        Route::post('/documents/{document}/review', [AdminCompanyController::class, 'verifyDocument'])->name('documents.review');
        Route::get('/documents/{document}/download', [AdminCompanyController::class, 'downloadDocument'])->name('documents.download');
        // Phase 2 / Sprint 10 / task 2.14 — admin verify/reject an uploaded
        // insurance policy. Same review modal pattern as documents above.
        Route::post('/insurances/{insurance}/review', [AdminCompanyController::class, 'verifyInsurance'])->name('insurances.review');
        Route::delete('/companies/{id}', [AdminCompanyController::class, 'destroy'])->name('companies.destroy');

        // Phase 2 / Sprint 8 / task 2.6 — verification queue. Surfaces every
        // company that needs admin attention (sanctions, pending docs,
        // promotion eligibility) ranked by urgency.
        Route::get('/verification', [AdminVerificationQueueController::class, 'index'])->name('verification.index');
        Route::post('/verification/{id}/auto-promote', [AdminVerificationQueueController::class, 'autoPromote'])->name('verification.auto-promote');

        // Category-assignment requests submitted by company managers.
        Route::get('/category-requests', [AdminCompanyCategoryRequestController::class, 'index'])->name('category-requests.index');
        Route::post('/category-requests/{id}/approve', [AdminCompanyCategoryRequestController::class, 'approve'])->name('category-requests.approve');
        Route::post('/category-requests/{id}/reject', [AdminCompanyCategoryRequestController::class, 'reject'])->name('category-requests.reject');

        // Categories
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{id}', [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Settings
        Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::delete('/settings/{id}', [AdminSettingController::class, 'destroy'])->name('settings.destroy');

        // Tax Rates — platform-wide VAT/tax configuration. ContractService and
        // PaymentService consult TaxRate::resolveFor() at create time, so any
        // edit here flows through every subsequent transaction.
        Route::get('/tax-rates', [AdminTaxRateController::class, 'index'])->name('tax-rates.index');
        Route::get('/tax-rates/create', [AdminTaxRateController::class, 'create'])->name('tax-rates.create');
        Route::post('/tax-rates', [AdminTaxRateController::class, 'store'])->name('tax-rates.store');
        Route::get('/tax-rates/{id}/edit', [AdminTaxRateController::class, 'edit'])->name('tax-rates.edit');
        Route::patch('/tax-rates/{id}', [AdminTaxRateController::class, 'update'])->name('tax-rates.update');
        Route::delete('/tax-rates/{id}', [AdminTaxRateController::class, 'destroy'])->name('tax-rates.destroy');

        // Audit logs (read-only — append-only by design)
        Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit.index');
        // Export must come before /{id} so "export" isn't captured as an id.
        Route::get('/audit/export', [AdminAuditLogController::class, 'export'])->name('audit.export');
        Route::get('/audit/{id}', [AdminAuditLogController::class, 'show'])->name('audit.show');

        // Phase 1 (UAE Compliance Roadmap) — Tax Invoices.
        // Auto-issued by PaymentInvoiceObserver when a payment becomes
        // completed; this admin section is for finance to monitor, void
        // erroneous invoices, and manually re-issue when an auto job
        // failed. Hard delete is not exposed — voiding is the only legal
        // way to invalidate a tax invoice.
        Route::get('/tax-invoices', [AdminTaxInvoiceController::class, 'index'])->name('tax-invoices.index');
        Route::get('/tax-invoices/{id}', [AdminTaxInvoiceController::class, 'show'])->name('tax-invoices.show');
        Route::get('/tax-invoices/{id}/download', [AdminTaxInvoiceController::class, 'download'])->name('tax-invoices.download');
        Route::post('/tax-invoices/from-payment/{paymentId}', [AdminTaxInvoiceController::class, 'issueForPayment'])->name('tax-invoices.issue-for-payment');
        Route::post('/tax-invoices/{id}/void', [AdminTaxInvoiceController::class, 'void'])->name('tax-invoices.void');

        // Phase 4 (UAE Compliance Roadmap) — ICV verification queue.
        // Admin reviews supplier-uploaded MoIAT/ADNOC certificates and
        // either approves (so the score becomes eligible for bid
        // evaluation) or rejects with a reason.
        Route::get('/icv-certificates', [AdminIcvCertificateController::class, 'index'])->name('icv-certificates.index');
        Route::get('/icv-certificates/{id}/download', [AdminIcvCertificateController::class, 'download'])->name('icv-certificates.download');
        Route::post('/icv-certificates/{id}/approve', [AdminIcvCertificateController::class, 'approve'])->name('icv-certificates.approve');
        Route::post('/icv-certificates/{id}/reject', [AdminIcvCertificateController::class, 'reject'])->name('icv-certificates.reject');

        // Phase 8 (UAE Compliance Roadmap) — Tier 3 compliance certificate
        // uploads (CoO, ECAS, Halal, GSO, ISO). Same review pattern as ICV.
        Route::get('/certificate-uploads', [AdminCertificateUploadController::class, 'index'])->name('certificate-uploads.index');
        Route::get('/certificate-uploads/{id}/download', [AdminCertificateUploadController::class, 'download'])->name('certificate-uploads.download');
        Route::post('/certificate-uploads/{id}/approve', [AdminCertificateUploadController::class, 'approve'])->name('certificate-uploads.approve');
        Route::post('/certificate-uploads/{id}/reject', [AdminCertificateUploadController::class, 'reject'])->name('certificate-uploads.reject');

        // Phase 7 (UAE Compliance Roadmap) — Anti-Collusion alerts.
        // Admin triage queue for bid-rigging / collusion pattern
        // detections from AnalyzeRfqForCollusionJob. Federal
        // Decree-Law 36/2023 Article 13 — the platform must
        // demonstrate it acted on credible indications.
        Route::get('/anti-collusion', [AntiCollusionController::class, 'index'])->name('anti-collusion.index');
        Route::post('/anti-collusion/{id}/status', [AntiCollusionController::class, 'updateStatus'])->name('anti-collusion.update');

        // Phase 5 (UAE Compliance Roadmap) — e-invoice transmission queue.
        // Read-only listing of every Peppol PINT-AE submission with a
        // retry button for failed/rejected rows. Mocked locally until
        // a real ASP contract is signed (config('einvoice.enabled')).
        Route::get('/e-invoice', [AdminEInvoiceController::class, 'index'])->name('e-invoice.index');
        Route::post('/e-invoice/{id}/retry', [AdminEInvoiceController::class, 'retry'])->name('e-invoice.retry');

        // Oversight — read-only system-wide pivot across PRs / RFQs / Bids /
        // Contracts / Payments / Shipments / Disputes. The view is a tabbed
        // shell driven by ?scope= so each list keeps its own pagination and
        // the URL is shareable. No mutations live here on purpose: every
        // write still flows through the existing role-gated dashboards so
        // the audit trail keeps a single source of truth.
        Route::get('/oversight', [AdminOversightController::class, 'index'])->name('oversight.index');

        // Reports & Analytics — platform-wide KPIs, supplier scorecard,
        // cycle time, savings analysis, and CSV exports.
        Route::get('/reports', [AdminReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [AdminReportsController::class, 'export'])->name('reports.export');

        // System Health — DB, cache, queue, disk, memory monitoring.
        Route::get('/system-health', [AdminSystemHealthController::class, 'index'])->name('system-health.index');

        // Dispute Management — assign investigators, track SLA, intervene.
        Route::get('/disputes', [AdminDisputeManagementController::class, 'index'])->name('disputes.index');
        Route::post('/disputes/{id}/assign', [AdminDisputeManagementController::class, 'assign'])->name('disputes.assign');

        // Blacklist — block companies from participating on the platform.
        Route::get('/blacklist', [AdminBlacklistController::class, 'index'])->name('blacklist.index');
        Route::post('/blacklist', [AdminBlacklistController::class, 'store'])->name('blacklist.store');
        Route::delete('/blacklist/{id}', [AdminBlacklistController::class, 'destroy'])->name('blacklist.remove');

        // Platform Fees / Commissions — manage service charges.
        Route::get('/fees', [AdminFeeController::class, 'index'])->name('fees.index');
        Route::post('/fees', [AdminFeeController::class, 'store'])->name('fees.store');
        Route::patch('/fees/{id}', [AdminFeeController::class, 'update'])->name('fees.update');
        Route::delete('/fees/{id}', [AdminFeeController::class, 'destroy'])->name('fees.destroy');

        // Exchange Rates — manage currency rates for multi-currency support.
        Route::get('/exchange-rates', [AdminExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [AdminExchangeRateController::class, 'store'])->name('exchange-rates.store');
        Route::delete('/exchange-rates/{id}', [AdminExchangeRateController::class, 'destroy'])->name('exchange-rates.destroy');

        // Webhook Management — monitor all webhook endpoints and deliveries.
        Route::get('/webhooks', [AdminWebhookManagementController::class, 'index'])->name('webhooks.index');
        Route::get('/webhooks/{id}/deliveries', [AdminWebhookManagementController::class, 'deliveries'])->name('webhooks.deliveries');
    });

    // ---- Purchase Requests --------------------------------------------------
    // Reads: any authenticated dashboard user can browse.
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('dashboard.purchase-requests');
    // Writes: only buyers (and company managers) create/submit/delete PRs.
    // Create route MUST be declared before /{id} so the literal "create" segment
    // does not get captured as an id.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('dashboard.purchase-requests.create');
        Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('dashboard.purchase-requests.store');
        Route::post('/purchase-requests/{id}/submit', [PurchaseRequestController::class, 'submit'])->name('dashboard.purchase-requests.submit');
        Route::delete('/purchase-requests/{id}', [PurchaseRequestController::class, 'destroy'])->name('dashboard.purchase-requests.destroy');
    });
    // PR approval / rejection — anyone with pr.approve permission can act.
    // The controller enforces company_id matching, branch scoping, and the
    // self-approval ban (buyer_id ≠ user.id) internally — no route-level
    // role gate needed. The old `web.role:company_manager` middleware was
    // too narrow: it blocked branch_managers and buyers who'd been granted
    // pr.approve by their company manager.
    // Bulk route MUST be declared before the parameterised {id}/approve
    // route — otherwise Laravel captures "bulk" as the id.
    Route::post('/purchase-requests/bulk/approve', [PurchaseRequestController::class, 'bulkApprove'])->name('dashboard.purchase-requests.bulk-approve');
    Route::post('/purchase-requests/{id}/approve', [PurchaseRequestController::class, 'approve'])->name('dashboard.purchase-requests.approve');
    Route::post('/purchase-requests/{id}/reject', [PurchaseRequestController::class, 'reject'])->name('dashboard.purchase-requests.reject');

    // ---- Supplier Directory (Phase 1 / task 1.1) ----------------------------
    // Buyer-facing browse view of every active supplier on the platform.
    // Distinct from /suppliers below — that's the manager's *exclusive*
    // supplier list (CompanySupplier pivot). Open to any authenticated user.
    Route::get('/suppliers/directory', [SupplierProfileController::class, 'directory'])->name('dashboard.suppliers.directory');

    // Hierarchical UNSPSC-aware category browser (Phase 1 / task 1.10).
    // ?root=<category_id> drills into a sub-tree; no root shows top-level segments.
    Route::get('/categories/browse', [SupplierProfileController::class, 'categoryBrowser'])->name('dashboard.categories.browse');

    // Federated search (Phase 1 / task 1.12) — RFQs + products + suppliers
    // in one page. Persists each search to the user's history (task 1.13).
    Route::get('/search', [GlobalSearchController::class, 'index'])->name('dashboard.search');

    // Saved searches (Phase 1 / task 1.5) — store + toggle + delete the
    // current page's filters as a named search owned by the current user.
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('dashboard.saved-searches.store');
    Route::post('/saved-searches/{id}/toggle', [SavedSearchController::class, 'toggle'])->name('dashboard.saved-searches.toggle');
    Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy'])->name('dashboard.saved-searches.destroy');

    // ---- Approved Suppliers (manager-only) ----------------------------------
    // Locks specific supplier companies as exclusive to my company. Bids
    // from these suppliers on my own RFQs are blocked at BidService level.
    Route::middleware('web.role:company_manager')->group(function () {
        Route::get('/suppliers', [CompanySupplierController::class, 'index'])->name('dashboard.suppliers.index');
        Route::get('/suppliers/create', [CompanySupplierController::class, 'create'])->name('dashboard.suppliers.create');
        Route::post('/suppliers', [CompanySupplierController::class, 'store'])->name('dashboard.suppliers.store');
        Route::delete('/suppliers/{id}', [CompanySupplierController::class, 'destroy'])->name('dashboard.suppliers.destroy');
    });

    // ---- Spend Analytics (Phase 2 of H1) ------------------------------------
    Route::get('/analytics/spend', [SpendAnalyticsController::class, 'index'])->name('dashboard.analytics.spend');

    // ---- Shipping Quotes & Tracking Sync -----------------------------------
    Route::get('/shipping/quotes', [ShippingQuoteController::class, 'form'])->name('dashboard.shipping.quotes');
    Route::post('/shipping/quotes', [ShippingQuoteController::class, 'quote'])->name('dashboard.shipping.quotes.run');
    Route::post('/shipments/{id}/sync-tracking', [ShippingQuoteController::class, 'syncTracking'])->name('dashboard.shipments.sync-tracking');

    // ---- API Tokens (manager self-service) ---------------------------------
    Route::middleware('web.role:company_manager')->group(function () {
        Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('dashboard.api-tokens.index');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('dashboard.api-tokens.store');
        Route::delete('/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('dashboard.api-tokens.destroy');
    });

    // ---- Structured Negotiation Rounds -------------------------------------
    Route::post('/bids/{bid}/negotiation/counter', [NegotiationRoundController::class, 'counter'])->name('dashboard.negotiation.counter');
    Route::post('/bids/{bid}/negotiation/accept', [NegotiationRoundController::class, 'accept'])->name('dashboard.negotiation.accept');
    Route::post('/bids/{bid}/negotiation/reject', [NegotiationRoundController::class, 'reject'])->name('dashboard.negotiation.reject');

    // ---- Reverse Auctions (live bidding) -----------------------------------
    // Browse + JSON poller are open to anyone with rfq.view; placing a bid
    // requires bid.submit (suppliers/buyers depending on RFQ direction).
    // Enabling an auction on an RFQ requires rfq.edit + ownership.
    Route::get('/auctions/{id}', [AuctionController::class, 'show'])->name('dashboard.auctions.show');
    Route::get('/auctions/{id}/live', [AuctionController::class, 'live'])->name('dashboard.auctions.live');
    Route::post('/auctions/{id}/bid', [AuctionController::class, 'placeBid'])->name('dashboard.auctions.bid');
    Route::get('/rfqs/{id}/auction/create', [AuctionController::class, 'createForm'])->name('dashboard.auctions.create');
    Route::post('/rfqs/{id}/auction/enable', [AuctionController::class, 'enable'])->name('dashboard.auctions.enable');

    // ---- Document Vault (Trust v1) -----------------------------------------
    // Manager uploads + lists company documents (trade license, ISO, etc).
    // Admin verification happens in the admin namespace below.
    Route::middleware('web.role:company_manager')->group(function () {
        Route::get('/documents', [CompanyDocumentController::class, 'index'])->name('dashboard.documents.index');
        Route::post('/documents', [CompanyDocumentController::class, 'store'])->name('dashboard.documents.store');
        Route::get('/documents/{id}/download', [CompanyDocumentController::class, 'download'])->name('dashboard.documents.download');
        Route::delete('/documents/{id}', [CompanyDocumentController::class, 'destroy'])->name('dashboard.documents.destroy');

        // Phase 2 / Sprint 9 / task 2.9 — compliance-branded alias for the
        // same vault page. Lives under /dashboard/compliance/documents so
        // it can sit next to insurance/credit/screening pages added later
        // in Sprint 9-10. Both URLs render the same view.
        Route::get('/compliance/documents', [CompanyDocumentController::class, 'index'])->name('dashboard.compliance.documents');

        // Phase 2 / Sprint 9 / task 2.11 — renew workflow for an existing
        // document. The manager re-uploads a fresh file; the new copy
        // replaces the previous one and gets pushed back into the
        // verification queue (status flips to pending again).
        Route::post('/documents/{id}/renew', [CompanyDocumentController::class, 'renew'])->name('dashboard.documents.renew');

        // Phase 2 / Sprint 8 / task 2.7 — beneficial owners disclosure for
        // Gold tier and above. Manager-only because the data is sensitive
        // personal information with regulatory implications.
        Route::get('/beneficial-owners', [BeneficialOwnerController::class, 'index'])->name('dashboard.beneficial-owners.index');
        Route::post('/beneficial-owners', [BeneficialOwnerController::class, 'store'])->name('dashboard.beneficial-owners.store');
        Route::patch('/beneficial-owners/{id}', [BeneficialOwnerController::class, 'update'])->name('dashboard.beneficial-owners.update');
        Route::delete('/beneficial-owners/{id}', [BeneficialOwnerController::class, 'destroy'])->name('dashboard.beneficial-owners.destroy');

        // Phase 2 / Sprint 10 / task 2.14 — manager-side insurance upload.
        // Admin verification of each policy is exposed in the verification
        // queue (admin namespace below).
        Route::get('/insurances', [CompanyInsuranceController::class, 'index'])->name('dashboard.insurances.index');
        Route::post('/insurances', [CompanyInsuranceController::class, 'store'])->name('dashboard.insurances.store');
        Route::delete('/insurances/{id}', [CompanyInsuranceController::class, 'destroy'])->name('dashboard.insurances.destroy');
    });

    // ---- Catalog (Products) ------------------------------------------------
    // Buyer-side browse + Buy-Now is open to anyone with rfq.view; the
    // actual purchase requires a company. Supplier-side CRUD is restricted
    // to suppliers/service providers (and the company manager who runs them).
    Route::get('/catalog', [ProductController::class, 'browse'])->name('dashboard.catalog.browse');
    Route::get('/catalog/{id}', [ProductController::class, 'show'])->name('dashboard.catalog.show');
    Route::post('/catalog/{id}/buy', [ProductController::class, 'buyNow'])->name('dashboard.catalog.buy');

    // ---- Cart (Phase 4 / Sprint 16-17) -------------------------------------
    // Buyer-side shopping cart that powers multi-supplier checkout. Every
    // action is scoped to the authenticated user's own cart inside
    // CartService — there's no cross-user access by construction.
    Route::get('/cart', [CartController::class, 'index'])->name('dashboard.cart.index');
    Route::post('/cart/items', [CartController::class, 'add'])->name('dashboard.cart.add');
    Route::patch('/cart/items/{id}', [CartController::class, 'updateQuantity'])->name('dashboard.cart.update');
    Route::delete('/cart/items/{id}', [CartController::class, 'remove'])->name('dashboard.cart.remove');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('dashboard.cart.clear');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('dashboard.cart.checkout');
    // Phase 4 / Sprint 18 — quick reorder from a previously purchased
    // contract. Repopulates the open cart with the contract's lines and
    // bounces to /dashboard/cart for review.
    Route::post('/contracts/{id}/reorder', [CartController::class, 'reorderFromContract'])->name('dashboard.contracts.reorder');

    Route::middleware('web.role:supplier,service_provider,company_manager,sales,sales_manager')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('dashboard.products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('dashboard.products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('dashboard.products.store');
        Route::post('/products/suggest-hs-code', [ProductController::class, 'suggestHsCode'])->name('dashboard.products.suggest-hs');
        Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('dashboard.products.edit');
        Route::patch('/products/{id}', [ProductController::class, 'update'])->name('dashboard.products.update');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('dashboard.products.destroy');
    });

    // ---- Branches (manager-only) -------------------------------------------
    // Manager creates specialised branches; each branch can have its own
    // branch_manager who only sees PRs/RFQs/Contracts in that branch.
    Route::middleware('web.role:company_manager')->group(function () {
        Route::get('/branches', [BranchController::class, 'index'])->name('dashboard.branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('dashboard.branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('dashboard.branches.store');
        Route::get('/branches/{id}/edit', [BranchController::class, 'edit'])->name('dashboard.branches.edit');
        Route::patch('/branches/{id}', [BranchController::class, 'update'])->name('dashboard.branches.update');
        Route::delete('/branches/{id}', [BranchController::class, 'destroy'])->name('dashboard.branches.destroy');
    });
    Route::get('/purchase-requests/{id}/success', [PurchaseRequestController::class, 'showSuccess'])->name('dashboard.purchase-requests.success');
    Route::get('/purchase-requests/{id}', [PurchaseRequestController::class, 'show'])->name('dashboard.purchase-requests.show');

    // ---- RFQs ----------------------------------------------------------------
    Route::get('/rfqs', [RfqController::class, 'index'])->name('dashboard.rfqs');
    Route::get('/rfqs/{id}', [RfqController::class, 'show'])->name('dashboard.rfqs.show');
    Route::get('/rfqs/{id}/compare', [RfqController::class, 'compareBids'])->name('dashboard.rfqs.compare');
    Route::get('/rfqs/{id}/pdf', [RfqController::class, 'downloadPackage'])->name('dashboard.rfqs.pdf');
    // Supplier-side bid submission form (POST lives on BidController::store below).
    Route::get('/rfqs/{id}/bid', [RfqController::class, 'createBid'])->name('dashboard.rfqs.bid.create');

    // ---- Bids ----------------------------------------------------------------
    Route::get('/bids', [BidController::class, 'index'])->name('dashboard.bids');
    Route::get('/bids/{id}', [BidController::class, 'show'])->name('dashboard.bids.show');
    Route::get('/bids/{id}/pdf', [BidController::class, 'download'])->name('dashboard.bids.pdf');
    Route::get('/bids/{id}/attachments/{idx}', [BidController::class, 'downloadAttachment'])->name('dashboard.bids.attachment.download');
    // Bid submission: suppliers bid on purchase RFQs, buyers bid on sales-offer
    // RFQs. Sales roles + company_manager also have bid.submit by default, and
    // the controller re-checks the per-permission grant (bid.submit) plus the
    // self-RFQ / company / status rules, so the middleware here just gates out
    // clearly unrelated roles (admin/government/finance/...).
    Route::middleware('web.role:supplier,logistics,clearance,service_provider,buyer,sales,sales_manager,company_manager,branch_manager')->group(function () {
        Route::post('/rfqs/{rfq}/bids', [BidController::class, 'store'])->name('dashboard.bids.store');
        Route::post('/bids/{id}/withdraw', [BidController::class, 'withdraw'])->name('dashboard.bids.withdraw');
    });
    // Only buyers can accept a bid.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::post('/bids/{id}/accept', [BidController::class, 'accept'])->name('dashboard.bids.accept');
        Route::post('/bids/bulk/reject', [BidController::class, 'bulkReject'])->name('dashboard.bids.bulk-reject');
    });

    // ---- Negotiation Room ----------------------------------------------------
    // Buyer <-> Supplier chat + counter offers around a specific bid. The
    // controller authorizes each request against both companies, so we don't
    // need a role gate here — either side can reach the room.
    Route::get('/negotiations/{id}', [NegotiationController::class, 'show'])->name('dashboard.negotiations.show');
    Route::post('/negotiations/{id}/messages', [NegotiationController::class, 'storeMessage'])->name('dashboard.negotiations.message');
    Route::post('/negotiations/{id}/counter-offer', [NegotiationController::class, 'storeCounterOffer'])->name('dashboard.negotiations.counter');
    Route::post('/negotiations/{id}/accept', [NegotiationController::class, 'accept'])->name('dashboard.negotiations.accept');
    Route::post('/negotiations/{id}/end', [NegotiationController::class, 'end'])->name('dashboard.negotiations.end');

    // ---- Contracts -----------------------------------------------------------
    Route::get('/contracts', [ContractController::class, 'index'])->name('dashboard.contracts');
    // Spend analytics dashboard — declared BEFORE /{id} so the
    // /analytics literal isn't captured as a contract id.
    Route::get('/contracts/analytics', AnalyticsController::class)->name('dashboard.contracts.analytics');
    // Export must be declared before /{id} so "export" isn't captured as an id.
    Route::get('/contracts/export/csv', [ContractController::class, 'exportCsv'])->name('dashboard.contracts.export-csv');
    Route::get('/contracts/{id}', [ContractController::class, 'show'])->name('dashboard.contracts.show');
    Route::get('/contracts/{id}/pdf', [ContractController::class, 'pdf'])->name('dashboard.contracts.pdf');
    // Signing is open to any party of the contract — verified inside
    // the controller. Rate-limited to 5 attempts/minute per
    // (user, contract) pair so a leaked password can't be brute-forced
    // through the step-up auth check.
    Route::post('/contracts/{id}/sign', [SigningController::class, 'sign'])
        ->middleware('throttle:contract.sign')
        ->name('dashboard.contracts.sign');

    // Phase 6 (UAE Compliance Roadmap) — UAE Pass OAuth flow.
    // Redirect: kicks off the authorization code flow with a fresh
    // CSRF state stamped in the session.
    // Callback: validates state, exchanges the code, calls
    // ContractService::sign with signature_grade=advanced.
    // Both routes are under the same auth middleware as the regular
    // sign endpoint — UAE Pass is a stronger authentication, not a
    // bypass for the contract-party check.
    Route::get('/contracts/{id}/sign/uae-pass', [SigningController::class, 'uaePassRedirect'])->name('dashboard.contracts.sign.uae-pass');
    Route::get('/contracts/{id}/sign/uae-pass/callback', [SigningController::class, 'uaePassCallback'])->name('dashboard.contracts.sign.uae-pass.callback');
    // Bilateral amendment of contract terms — either party proposes, the
    // other party approves or rejects. All three handlers re-authorise the
    // caller as a contract party and enforce the cross-company rule.
    Route::post('/contracts/{id}/amendments', [AmendmentController::class, 'propose'])->name('dashboard.contracts.amendments.propose');
    Route::post('/contracts/{id}/amendments/{amendmentId}/approve', [AmendmentController::class, 'approve'])->name('dashboard.contracts.amendments.approve');
    Route::post('/contracts/{id}/amendments/{amendmentId}/reject', [AmendmentController::class, 'reject'])->name('dashboard.contracts.amendments.reject');
    // Proposer can withdraw their own pending amendment before the
    // counter-party decides on it. Strict ownership check inside the
    // controller — only the user who proposed it (not just any user
    // from the same company) can cancel.
    Route::post('/contracts/{id}/amendments/{amendmentId}/cancel', [AmendmentController::class, 'cancel'])->name('dashboard.contracts.amendments.cancel');
    // Per-amendment discussion thread — append-only conversation that
    // either party can post into to negotiate the wording before the
    // formal approve/reject decision is made.
    Route::post('/contracts/{id}/amendments/{amendmentId}/messages', [AmendmentController::class, 'postMessage'])->name('dashboard.contracts.amendments.messages.store');
    // Polling endpoint for the negotiation thread — returns JSON for
    // the messages created STRICTLY AFTER the ?since= timestamp so the
    // blade view can refresh in near real-time without reloading the
    // whole contract page.
    Route::get('/contracts/{id}/amendments/{amendmentId}/messages', [AmendmentController::class, 'pollMessages'])->name('dashboard.contracts.amendments.messages.poll');
    // Pre-signature decline: either party can refuse the contract
    // before any signature is collected. Post-signature termination:
    // either active party may end the contract early by mutual
    // agreement; settlement of held escrow is handled by the existing
    // escrow workflow downstream.
    Route::post('/contracts/{id}/decline', [ContractController::class, 'decline'])->name('dashboard.contracts.decline');
    Route::post('/contracts/{id}/terminate', [ContractController::class, 'terminate'])->name('dashboard.contracts.terminate');
    // Internal approval action — buyer-side gate when the contract
    // value exceeds the buyer company's approval_threshold_aed.
    // Decision = approved | rejected; routes to the same endpoint.
    Route::post('/contracts/{id}/approval', [ContractController::class, 'decideApproval'])->name('dashboard.contracts.approval');
    // Renew an existing contract — clones it into a fresh draft with
    // new start/end dates. Buyer-side only; supplier renews via the
    // standard RFQ → bid flow on their side.
    Route::post('/contracts/{id}/renew', [ContractController::class, 'renew'])->name('dashboard.contracts.renew');
    // Track-changes view: shows the contract terms across two
    // ContractVersion snapshots side-by-side and highlights every
    // line that was added / removed / modified between them.
    Route::get('/contracts/{id}/diff', [ContractController::class, 'diffVersions'])->name('dashboard.contracts.diff');
    // e-Signature audit certificate — separate PDF that lists every
    // signature with IP, device, terms hash and consent timestamp.
    // Used as legal evidence under Federal Decree-Law 46/2021.
    Route::get('/contracts/{id}/audit-certificate', [ContractController::class, 'auditCertificate'])->name('dashboard.contracts.audit-certificate');
    // Internal team notes — visible only to users from the SAME
    // company as the author. Strict tenant scoping enforced inside
    // the controller.
    Route::post('/contracts/{id}/internal-notes', [ContractController::class, 'postInternalNote'])->name('dashboard.contracts.internal-notes.store');
    Route::delete('/contracts/{id}/internal-notes/{noteId}', [ContractController::class, 'deleteInternalNote'])->name('dashboard.contracts.internal-notes.destroy');
    // Supplier-side actions on a contract: progress updates, doc uploads, shipment scheduling.
    // Each method authorizes the user is on the supplier side via authorizeSupplierParty().
    Route::post('/contracts/{id}/progress', [ContractController::class, 'updateProgress'])->name('dashboard.contracts.progress');
    Route::post('/contracts/{id}/documents', [ContractController::class, 'uploadDocuments'])->name('dashboard.contracts.documents.upload');
    Route::get('/contracts/{id}/documents/{idx}', [ContractController::class, 'downloadDocument'])->name('dashboard.contracts.documents.download');
    Route::post('/contracts/{id}/shipments', [ContractController::class, 'scheduleShipment'])->name('dashboard.contracts.shipments.schedule');
    // Feedback / review on a completed contract — either party can rate the other.
    Route::post('/contracts/{id}/feedback', [FeedbackController::class, 'store'])->name('dashboard.contracts.feedback.store');

    // Public-ish supplier profile (linked from bid show pages, supplier directory, etc.)
    Route::get('/companies/{id}', [SupplierProfileController::class, 'show'])->name('dashboard.suppliers.profile');

    // ---- Shipments -----------------------------------------------------------
    Route::get('/shipments', [ShipmentController::class, 'index'])->name('dashboard.shipments');
    Route::get('/shipments/{id}', [ShipmentController::class, 'show'])->name('dashboard.shipments.show');
    // Only logistics/clearance update GPS / customs tracking.
    Route::middleware('web.role:logistics,clearance')->group(function () {
        Route::post('/shipments/{id}/track', [ShipmentController::class, 'track'])->name('dashboard.shipments.track');
    });

    // ---- Payments ------------------------------------------------------------
    Route::get('/payments', [PaymentController::class, 'index'])->name('dashboard.payments');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('dashboard.payments.show');
    // Phase 1 (UAE Compliance Roadmap) — user-facing tax invoice routes.
    // Either party to the payment can download its attached invoice;
    // only the buyer (or an admin) can force-issue when auto-issuance
    // failed.
    Route::get('/payments/{id}/tax-invoice', [PaymentController::class, 'downloadInvoice'])->name('dashboard.payments.invoice.download');
    Route::post('/payments/{id}/tax-invoice', [PaymentController::class, 'issueInvoice'])->name('dashboard.payments.invoice.issue');
    // Only buyers approve/process payments.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::post('/payments/{id}/approve', [PaymentController::class, 'approve'])->name('dashboard.payments.approve');
        Route::post('/payments/{id}/process', [PaymentController::class, 'process'])->name('dashboard.payments.process');
    });

    // ---- Escrow (Phase 3 — Trade Finance MVP) -------------------------------
    // Buyer activates an escrow account on a signed contract, deposits
    // funds, and either releases manually or relies on the auto-release
    // listeners (signature / delivery / inspection). The dashboard view
    // is open to any contract party so suppliers can also see custody.
    Route::get('/escrow', [EscrowController::class, 'dashboard'])->name('dashboard.escrow.index');
    Route::post('/contracts/{id}/escrow/activate', [EscrowController::class, 'activate'])->name('dashboard.escrow.activate');
    Route::post('/contracts/{id}/escrow/deposit', [EscrowController::class, 'deposit'])->name('dashboard.escrow.deposit');
    Route::post('/contracts/{id}/escrow/release', [EscrowController::class, 'manualRelease'])->name('dashboard.escrow.release');
    Route::post('/contracts/{id}/escrow/refund', [EscrowController::class, 'refund'])->name('dashboard.escrow.refund');

    // ---- AI Layer (Phase 5) ------------------------------------------------
    // Document OCR + negotiation assistant + contract risk + price
    // prediction + procurement copilot. Every action requires `ai.use`,
    // additional permissions enforced inside the controller per-method.
    Route::get('/ai/ocr', [AIController::class, 'ocrForm'])->name('dashboard.ai.ocr');
    Route::post('/ai/ocr', [AIController::class, 'ocrExtract'])->name('dashboard.ai.ocr.extract');
    Route::get('/ai/copilot', [AIController::class, 'copilotPage'])->name('dashboard.ai.copilot');
    Route::post('/ai/copilot/chat', [AIController::class, 'copilotChat'])->name('dashboard.ai.copilot.chat');
    Route::post('/ai/copilot/reset', [AIController::class, 'copilotReset'])->name('dashboard.ai.copilot.reset');
    Route::get('/ai/bids/{bid}/negotiation', [AIController::class, 'negotiationSuggestion'])->name('dashboard.ai.negotiation');
    Route::get('/ai/contracts/{contract}/risk', [AIController::class, 'contractRisk'])->name('dashboard.ai.contract-risk');
    Route::get('/ai/predictions/price', [AIController::class, 'pricePrediction'])->name('dashboard.ai.price-prediction');

    // ---- Logistics (Phase 6) -----------------------------------------------
    // Carbon footprint + customs documents + Dubai Trade declaration.
    // Each action authorises against the underlying shipment's contract
    // parties inside the controller.
    Route::get('/logistics/shipments/{id}/carbon', [LogisticsController::class, 'carbonFootprint'])->name('dashboard.logistics.carbon');
    Route::get('/logistics/shipments/{id}/commercial-invoice', [LogisticsController::class, 'commercialInvoice'])->name('dashboard.logistics.commercial-invoice');
    Route::get('/logistics/shipments/{id}/packing-list', [LogisticsController::class, 'packingList'])->name('dashboard.logistics.packing-list');
    Route::post('/logistics/shipments/{id}/dubai-trade', [LogisticsController::class, 'submitToDubaiTrade'])->name('dashboard.logistics.dubai-trade');

    // ---- Integrations (Phase 7) --------------------------------------------
    // Customer-managed webhook endpoints + ERP connectors. Manager-only
    // because both surfaces involve credentials and outbound side-effects.
    Route::middleware('web.role:company_manager,admin')->group(function () {
        Route::get('/integrations', [IntegrationsController::class, 'index'])->name('dashboard.integrations.index');
        Route::post('/integrations/webhooks', [IntegrationsController::class, 'storeEndpoint'])->name('dashboard.integrations.webhooks.store');
        Route::delete('/integrations/webhooks/{id}', [IntegrationsController::class, 'destroyEndpoint'])->name('dashboard.integrations.webhooks.destroy');
        Route::post('/integrations/webhooks/{id}/test', [IntegrationsController::class, 'testEndpoint'])->name('dashboard.integrations.webhooks.test');
        Route::post('/integrations/connectors', [IntegrationsController::class, 'storeConnector'])->name('dashboard.integrations.connectors.store');
        Route::delete('/integrations/connectors/{id}', [IntegrationsController::class, 'destroyConnector'])->name('dashboard.integrations.connectors.destroy');
        Route::post('/integrations/connectors/{id}/push-contract', [IntegrationsController::class, 'pushContract'])->name('dashboard.integrations.connectors.push-contract');
    });

    // ---- ESG & Sustainability (Phase 8) ------------------------------------
    // Per-company self-assessment, modern slavery statement, conflict
    // minerals declaration, and Scope 1-3 carbon log. Read for any
    // user with esg.view; mutation gated by esg.manage.
    Route::get('/esg', [EsgController::class, 'index'])->name('dashboard.esg.index');
    Route::post('/esg/questionnaire', [EsgController::class, 'saveQuestionnaire'])->name('dashboard.esg.questionnaire');
    Route::post('/esg/modern-slavery', [EsgController::class, 'saveModernSlavery'])->name('dashboard.esg.modern-slavery');
    Route::post('/esg/conflict-minerals', [EsgController::class, 'saveConflictMinerals'])->name('dashboard.esg.conflict-minerals');
    Route::post('/esg/carbon', [EsgController::class, 'logCarbonEntry'])->name('dashboard.esg.carbon');

    // ---- Disputes ------------------------------------------------------------
    Route::get('/disputes', [DisputeController::class, 'index'])->name('dashboard.disputes');
    Route::get('/disputes/{id}', [DisputeController::class, 'show'])->name('dashboard.disputes.show');
    // Any party of a contract can open a dispute (verified in controller).
    Route::post('/disputes', [DisputeController::class, 'store'])->name('dashboard.disputes.store');
    Route::post('/disputes/{id}/escalate', [DisputeController::class, 'escalate'])->name('dashboard.disputes.escalate');
    // Only government users resolve escalated disputes.
    Route::middleware('web.role:government,admin')->group(function () {
        Route::post('/disputes/{id}/resolve', [DisputeController::class, 'resolve'])->name('dashboard.disputes.resolve');
    });
});
