<?php

use App\Http\Controllers\Web\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Web\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Web\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\Auth\AuthController as WebAuthController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\BidController;
use App\Http\Controllers\Web\CompanyUserController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DisputeController;
use App\Http\Controllers\Web\GovernmentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PerformanceController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PurchaseRequestController;
use App\Http\Controllers\Web\RfqController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/',      [HomeController::class, 'index'])->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.attempt');
});

Route::get('/register',         [RegisterController::class, 'showForm'])->name('register');
Route::post('/register',        [RegisterController::class, 'register'])->name('register.submit');
Route::get('/register/success', [RegisterController::class, 'showSuccess'])->name('register.success');
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

// Password reset (Laravel default broker uses these route names)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password',          [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password',         [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}',   [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password',          [PasswordResetController::class, 'reset'])->name('password.update');
});

// Profile (any authenticated user). Also gated by company.approved so a
// pending-approval user can't slip into profile/settings/notifications —
// only register.success and logout are reachable for them.
Route::middleware(['auth', 'company.approved'])->group(function () {
    // Notifications feed
    Route::get('/notifications',                 [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all',       [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read',      [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{id}',         [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/profile',            [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/company-logo', [ProfileController::class, 'updateCompanyLogo'])->name('profile.company-logo');

    // Tabbed Settings page (Company / Personal / Notifications / Security / Payment)
    Route::get('/settings',                       [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/company',             [SettingsController::class, 'updateCompany'])->name('settings.company.update');
    Route::patch('/settings/personal',            [SettingsController::class, 'updatePersonal'])->name('settings.personal.update');
    Route::patch('/settings/notifications',       [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::patch('/settings/security',            [SettingsController::class, 'updateSecurity'])->name('settings.security.update');

    // Performance Dashboard
    Route::get('/performance',                    [PerformanceController::class, 'index'])->name('performance.index');
});

// Government console (top-level so the URL is /gov, not /dashboard/gov)
Route::middleware(['auth', 'web.role:government,admin'])->prefix('gov')->name('gov.')->group(function () {
    Route::get('/', [GovernmentController::class, 'index'])->name('index');
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
        Route::get('/company/users',                  [CompanyUserController::class, 'index'])->name('company.users');
        Route::get('/company/users/create',           [CompanyUserController::class, 'create'])->name('company.users.create');
        Route::post('/company/users',                 [CompanyUserController::class, 'store'])->name('company.users.store');
        Route::get('/company/users/{id}/edit',        [CompanyUserController::class, 'edit'])->name('company.users.edit');
        Route::patch('/company/users/{id}',           [CompanyUserController::class, 'update'])->name('company.users.update');
        Route::post('/company/users/{id}/toggle',     [CompanyUserController::class, 'toggleStatus'])->name('company.users.toggle');
        Route::post('/company/users/{id}/reset',      [CompanyUserController::class, 'resetPassword'])->name('company.users.reset');
        Route::delete('/company/users/{id}',          [CompanyUserController::class, 'destroy'])->name('company.users.destroy');
    });

    // ---- Admin ---------------------------------------------------------------
    Route::middleware('web.role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        // Users
        Route::get('/users',                  [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create',           [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users',                 [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit',        [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{id}',           [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/users/{id}/toggle',     [AdminUserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/users/{id}/reset',      [AdminUserController::class, 'resetPassword'])->name('users.reset');
        Route::delete('/users/{id}',          [AdminUserController::class, 'destroy'])->name('users.destroy');

        // Companies
        Route::get('/companies',                  [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/{id}',             [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::get('/companies/{id}/edit',        [AdminCompanyController::class, 'edit'])->name('companies.edit');
        Route::patch('/companies/{id}',           [AdminCompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{id}/approve',    [AdminCompanyController::class, 'approve'])->name('companies.approve');
        Route::post('/companies/{id}/reject',     [AdminCompanyController::class, 'reject'])->name('companies.reject');
        Route::post('/companies/{id}/suspend',    [AdminCompanyController::class, 'suspend'])->name('companies.suspend');
        Route::post('/companies/{id}/reactivate', [AdminCompanyController::class, 'reactivate'])->name('companies.reactivate');
        Route::delete('/companies/{id}',          [AdminCompanyController::class, 'destroy'])->name('companies.destroy');

        // Categories
        Route::get('/categories',          [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories',         [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{id}',   [AdminCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{id}',  [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

        // Settings
        Route::get('/settings',         [AdminSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings',        [AdminSettingController::class, 'update'])->name('settings.update');
        Route::delete('/settings/{id}', [AdminSettingController::class, 'destroy'])->name('settings.destroy');

        // Audit logs (read-only — append-only by design)
        Route::get('/audit',      [AdminAuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit/{id}', [AdminAuditLogController::class, 'show'])->name('audit.show');
    });

    // ---- Purchase Requests --------------------------------------------------
    // Reads: any authenticated dashboard user can browse.
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('dashboard.purchase-requests');
    // Writes: only buyers (and company managers) create/submit/delete PRs.
    // Create route MUST be declared before /{id} so the literal "create" segment
    // does not get captured as an id.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::get('/purchase-requests/create',       [PurchaseRequestController::class, 'create'])->name('dashboard.purchase-requests.create');
        Route::post('/purchase-requests',             [PurchaseRequestController::class, 'store'])->name('dashboard.purchase-requests.store');
        Route::post('/purchase-requests/{id}/submit', [PurchaseRequestController::class, 'submit'])->name('dashboard.purchase-requests.submit');
        Route::delete('/purchase-requests/{id}',      [PurchaseRequestController::class, 'destroy'])->name('dashboard.purchase-requests.destroy');
    });
    Route::get('/purchase-requests/{id}', [PurchaseRequestController::class, 'show'])->name('dashboard.purchase-requests.show');

    // ---- RFQs ----------------------------------------------------------------
    Route::get('/rfqs',              [RfqController::class, 'index'])->name('dashboard.rfqs');
    Route::get('/rfqs/{id}',         [RfqController::class, 'show'])->name('dashboard.rfqs.show');
    Route::get('/rfqs/{id}/compare', [RfqController::class, 'compareBids'])->name('dashboard.rfqs.compare');

    // ---- Bids ----------------------------------------------------------------
    Route::get('/bids',      [BidController::class, 'index'])->name('dashboard.bids');
    Route::get('/bids/{id}', [BidController::class, 'show'])->name('dashboard.bids.show');
    // Suppliers/logistics/clearance/service providers submit & withdraw bids.
    Route::middleware('web.role:supplier,logistics,clearance,service_provider')->group(function () {
        Route::post('/rfqs/{rfq}/bids',    [BidController::class, 'store'])->name('dashboard.bids.store');
        Route::post('/bids/{id}/withdraw', [BidController::class, 'withdraw'])->name('dashboard.bids.withdraw');
    });
    // Only buyers can accept a bid.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::post('/bids/{id}/accept', [BidController::class, 'accept'])->name('dashboard.bids.accept');
    });

    // ---- Contracts -----------------------------------------------------------
    Route::get('/contracts',          [ContractController::class, 'index'])->name('dashboard.contracts');
    Route::get('/contracts/{id}',     [ContractController::class, 'show'])->name('dashboard.contracts.show');
    Route::get('/contracts/{id}/pdf', [ContractController::class, 'pdf'])->name('dashboard.contracts.pdf');
    // Signing is open to any party of the contract — verified inside the controller.
    Route::post('/contracts/{id}/sign', [ContractController::class, 'sign'])->name('dashboard.contracts.sign');

    // ---- Shipments -----------------------------------------------------------
    Route::get('/shipments',      [ShipmentController::class, 'index'])->name('dashboard.shipments');
    Route::get('/shipments/{id}', [ShipmentController::class, 'show'])->name('dashboard.shipments.show');
    // Only logistics/clearance update GPS / customs tracking.
    Route::middleware('web.role:logistics,clearance')->group(function () {
        Route::post('/shipments/{id}/track', [ShipmentController::class, 'track'])->name('dashboard.shipments.track');
    });

    // ---- Payments ------------------------------------------------------------
    Route::get('/payments',      [PaymentController::class, 'index'])->name('dashboard.payments');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('dashboard.payments.show');
    // Only buyers approve/process payments.
    Route::middleware('web.role:buyer,company_manager')->group(function () {
        Route::post('/payments/{id}/approve', [PaymentController::class, 'approve'])->name('dashboard.payments.approve');
        Route::post('/payments/{id}/process', [PaymentController::class, 'process'])->name('dashboard.payments.process');
    });

    // ---- Disputes ------------------------------------------------------------
    Route::get('/disputes',      [DisputeController::class, 'index'])->name('dashboard.disputes');
    Route::get('/disputes/{id}', [DisputeController::class, 'show'])->name('dashboard.disputes.show');
    // Any party of a contract can open a dispute (verified in controller).
    Route::post('/disputes', [DisputeController::class, 'store'])->name('dashboard.disputes.store');
    Route::post('/disputes/{id}/escalate', [DisputeController::class, 'escalate'])->name('dashboard.disputes.escalate');
    // Only government users resolve escalated disputes.
    Route::middleware('web.role:government,admin')->group(function () {
        Route::post('/disputes/{id}/resolve', [DisputeController::class, 'resolve'])->name('dashboard.disputes.resolve');
    });
});
