<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryRoutingController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\RfqController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register-company', [AuthController::class, 'registerCompany']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));

// Public settings
Route::get('settings/public', [SettingController::class, 'publicSettings']);

// Webhooks (no auth)
Route::post('webhooks/stripe', [WebhookController::class, 'stripeWebhook']);
Route::post('webhooks/paypal', [WebhookController::class, 'paypalWebhook']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['jwt.auth', 'audit'])->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Users
    Route::patch('users/me', [UserController::class, 'updateMe']);
    Route::get('users/company/{companyId}', [UserController::class, 'getByCompany']);
    Route::patch('users/{id}/permissions', [UserController::class, 'updatePermissions']);
    Route::post('users/{id}/change-password', [UserController::class, 'changePassword']);
    Route::apiResource('users', UserController::class);

    // Companies
    Route::post('companies/{id}/approve', [CompanyController::class, 'approve'])
        ->middleware('permission:companies.manage');
    Route::post('companies/{id}/reject', [CompanyController::class, 'reject'])
        ->middleware('permission:companies.manage');
    Route::post('companies/{id}/documents', [CompanyController::class, 'addDocuments']);
    Route::get('companies/{id}/categories', [CompanyController::class, 'getCategories']);
    Route::post('companies/{id}/categories', [CompanyController::class, 'linkCategories'])
        ->middleware('permission:companies.manage');
    Route::delete('companies/{companyId}/categories/{categoryId}', [CompanyController::class, 'unlinkCategory'])
        ->middleware('permission:companies.manage');
    Route::apiResource('companies', CompanyController::class);

    // Categories
    Route::get('categories/tree', [CategoryController::class, 'tree']);
    Route::apiResource('categories', CategoryController::class);

    // Category Routing
    Route::prefix('category-routing')->group(function () {
        Route::get('match', [CategoryRoutingController::class, 'match']);
        Route::get('can-view', [CategoryRoutingController::class, 'canView']);
    });

    // Purchase Requests
    Route::patch('purchase-requests/{id}/approve', [PurchaseRequestController::class, 'approve'])
        ->middleware('permission:purchase-requests.approve');
    Route::apiResource('purchase-requests', PurchaseRequestController::class);

    // RFQs
    Route::get('rfqs/available', [RfqController::class, 'available']);
    Route::get('rfqs/purchase-request/{id}', [RfqController::class, 'getByPurchaseRequest']);
    Route::get('rfqs/{id}/bids/compare', [RfqController::class, 'compareBids']);
    Route::post('rfqs/{id}/enable-anonymity', [RfqController::class, 'enableAnonymity']);
    Route::post('rfqs/{id}/reveal-identity', [RfqController::class, 'revealIdentity']);
    Route::apiResource('rfqs', RfqController::class);

    // Bids
    Route::post('bids/{id}/evaluate', [BidController::class, 'evaluate'])
        ->middleware('permission:bids.evaluate');
    Route::post('bids/{id}/withdraw', [BidController::class, 'withdraw']);
    Route::post('bids/{id}/enable-anonymity', [BidController::class, 'enableAnonymity']);
    Route::post('bids/{id}/reveal-identity', [BidController::class, 'revealIdentity']);
    Route::apiResource('bids', BidController::class);

    // Contracts
    Route::post('contracts/{id}/sign', [ContractController::class, 'sign'])
        ->middleware('permission:contracts.sign');
    Route::post('contracts/{id}/verify-signature', [ContractController::class, 'verifySignature']);
    Route::post('contracts/{id}/activate', [ContractController::class, 'activate']);
    Route::get('contracts/{id}/pdf', [ContractController::class, 'pdf']);
    Route::get('contracts/{id}/versions', [ContractController::class, 'versions']);
    Route::get('contracts/{id}/versions/compare', [ContractController::class, 'compareVersions']);
    Route::get('contracts/{id}/versions/{version}', [ContractController::class, 'showVersion']);
    Route::get('contracts/{id}/amendments', [ContractController::class, 'amendments']);
    Route::post('contracts/{id}/amendments', [ContractController::class, 'createAmendment'])
        ->middleware('permission:contracts.amend');
    Route::get('contracts/{contractId}/amendments/{amendmentId}', [ContractController::class, 'showAmendment']);
    Route::post('contracts/{contractId}/amendments/{amendmentId}/approve', [ContractController::class, 'approveAmendment']);
    Route::apiResource('contracts', ContractController::class);

    // Payments
    Route::post('payments/{id}/approve', [PaymentController::class, 'approve'])
        ->middleware('permission:payments.approve');
    Route::post('payments/{id}/reject', [PaymentController::class, 'reject'])
        ->middleware('permission:payments.reject');
    Route::post('payments/{id}/process', [PaymentController::class, 'process'])
        ->middleware('permission:payments.process');
    Route::apiResource('payments', PaymentController::class);

    // Shipments
    Route::patch('shipments/{id}/track', [ShipmentController::class, 'track'])
        ->middleware('permission:shipments.track');
    Route::get('shipments/{id}/tracking-events', [ShipmentController::class, 'trackingEvents']);
    Route::post('shipments/{id}/customs/resubmit', [ShipmentController::class, 'resubmitCustoms']);
    Route::apiResource('shipments', ShipmentController::class);

    // Disputes
    Route::post('disputes/{id}/escalate', [DisputeController::class, 'escalate'])
        ->middleware('permission:disputes.escalate');
    Route::post('disputes/{id}/resolve', [DisputeController::class, 'resolve'])
        ->middleware('permission:disputes.resolve');
    Route::apiResource('disputes', DisputeController::class);

    // Analytics
    Route::prefix('analytics')->middleware('permission:analytics.view')->group(function () {
        Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('company-stats', [AnalyticsController::class, 'companyStats']);
        Route::get('payment-metrics', [AnalyticsController::class, 'paymentMetrics']);
        Route::get('government', [AnalyticsController::class, 'government']);
    });

    // Audit Logs
    Route::prefix('audit')->middleware('permission:audit.view')->group(function () {
        Route::get('logs', [AuditLogController::class, 'index']);
        Route::post('search', [AuditLogController::class, 'search']);
        Route::get('export', [AuditLogController::class, 'export'])
            ->middleware('permission:audit.export');
    });

    // Uploads
    Route::post('uploads', [UploadController::class, 'store'])->middleware('permission:uploads.create');
    Route::get('uploads/{id}', [UploadController::class, 'show'])->middleware('permission:uploads.view');
    Route::delete('uploads/{id}', [UploadController::class, 'destroy'])->middleware('permission:uploads.delete');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('notifications', [NotificationController::class, 'destroyAll']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
    Route::patch('settings', [SettingController::class, 'update'])->middleware('permission:settings.update');
    Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings.update');
});
