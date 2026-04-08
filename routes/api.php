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
// Phase 3 / Sprint 11 / task 3.4 — bank partner escrow webhook. The
// {provider} segment is the BankPartnerInterface::key() of whichever
// bank is firing the callback (mashreq_neobiz, enbd_trade, mock).
Route::post('webhooks/escrow/{provider}', [WebhookController::class, 'escrowWebhook'])->name('api.webhooks.escrow');

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

/*
|--------------------------------------------------------------------------
| Public API v1 — Sanctum bearer token authentication
|--------------------------------------------------------------------------
|
| Stable, versioned, externally-facing REST API. Customers issue tokens
| from /dashboard/api-tokens and use them as `Authorization: Bearer <token>`.
| Each endpoint declares the ability it requires; tokens without the right
| ability return 403.
|
| Tenancy: every read is scoped to the token owner's company. Cross-tenant
| reads are impossible by design.
|
| OpenAPI spec served at /api/v1/public/openapi.json.
|
*/
Route::prefix('v1/public')->middleware('auth:sanctum')->group(function () {
    $c = \App\Http\Controllers\Api\Public\V1Controller::class;

    // Read endpoints
    Route::get('/me',             [$c, 'me']);
    Route::get('/rfqs',           [$c, 'listRfqs']);
    Route::get('/rfqs/{id}',      [$c, 'showRfq']);
    Route::get('/bids',           [$c, 'listBids']);
    Route::get('/contracts',      [$c, 'listContracts']);
    Route::get('/contracts/{id}', [$c, 'showContract']);
    Route::get('/payments',       [$c, 'listPayments']);
    Route::get('/products',       [$c, 'listProducts']);

    // Write endpoints — require explicit write:* abilities
    Route::post('/rfqs',          [$c, 'createRfq']);
    Route::post('/rfqs/{id}/bids',[$c, 'createBid']);
    Route::post('/products',      [$c, 'createProduct']);
});

/*
|--------------------------------------------------------------------------
| Phase 7 — SCIM 2.0 user provisioning
|--------------------------------------------------------------------------
|
| Standard SCIM endpoints for enterprise IdPs (Okta, Azure AD, OneLogin,
| JumpCloud) to push and pull user records into TriLink. Authentication
| uses the same Sanctum bearer token system as the public API; the token
| must hold the `scim` ability and the IdP must use the company manager's
| credentials so the user records land in the right tenant.
|
*/
Route::prefix('scim/v2')->middleware(['auth:sanctum', 'ability:scim'])->group(function () {
    $c = \App\Http\Controllers\Api\ScimController::class;
    Route::get('/Users',                  [$c, 'index']);
    Route::post('/Users',                 [$c, 'store']);
    Route::get('/Users/{externalId}',     [$c, 'show']);
    Route::put('/Users/{externalId}',     [$c, 'update']);
    Route::patch('/Users/{externalId}',   [$c, 'update']);
    Route::delete('/Users/{externalId}',  [$c, 'destroy']);
});

// OpenAPI spec is unauthenticated so doc viewers can fetch it freely.
Route::get('/v1/public/openapi.json', function () {
    return response()->json([
        'openapi' => '3.0.3',
        'info' => [
            'title'       => 'TriLink Public API',
            'version'     => '1.0.0',
            'description' => 'Read-only B2B procurement API. Authenticate with a Sanctum bearer token issued from /dashboard/api-tokens.',
        ],
        'servers' => [['url' => url('/api/v1/public'), 'description' => 'Production']],
        'security' => [['bearerAuth' => []]],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type'         => 'http',
                    'scheme'       => 'bearer',
                    'bearerFormat' => 'Sanctum',
                ],
            ],
        ],
        'paths' => [
            '/me'              => ['get' => ['summary' => 'Authenticated user + company', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/rfqs' => [
                'get'  => ['summary' => 'List RFQs (paginated)', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']], ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create an RFQ (requires write:rfqs)', 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['title','items'], 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'category_id' => ['type' => 'integer'], 'budget' => ['type' => 'number'], 'currency' => ['type' => 'string'], 'deadline' => ['type' => 'string', 'format' => 'date-time'], 'items' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]], 'responses' => ['201' => ['description' => 'Created']]],
            ],
            '/rfqs/{id}'       => ['get' => ['summary' => 'Get RFQ by id', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/bids'            => ['get' => ['summary' => 'List bids (paginated)', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/contracts'       => ['get' => ['summary' => 'List contracts (paginated)', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/contracts/{id}'  => ['get' => ['summary' => 'Get contract by id', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/payments'        => ['get' => ['summary' => 'List payments (paginated)', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'OK']]]],
            '/products' => [
                'get'  => ['summary' => 'List catalog products (paginated)', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'company_id', 'in' => 'query', 'schema' => ['type' => 'integer']], ['name' => 'category_id', 'in' => 'query', 'schema' => ['type' => 'integer']]], 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['summary' => 'Create a catalog product (requires write:products)', 'security' => [['bearerAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['name','base_price','currency','unit','min_order_qty','lead_time_days'], 'properties' => ['name' => ['type' => 'string'], 'sku' => ['type' => 'string'], 'hs_code' => ['type' => 'string'], 'base_price' => ['type' => 'number'], 'currency' => ['type' => 'string'], 'unit' => ['type' => 'string'], 'min_order_qty' => ['type' => 'integer'], 'lead_time_days' => ['type' => 'integer']]]]]], 'responses' => ['201' => ['description' => 'Created']]],
            ],
            '/rfqs/{id}/bids' => [
                'post' => ['summary' => 'Submit a bid on an RFQ (requires write:bids)', 'security' => [['bearerAuth' => []]], 'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['price'], 'properties' => ['price' => ['type' => 'number'], 'currency' => ['type' => 'string'], 'delivery_time_days' => ['type' => 'integer'], 'payment_terms' => ['type' => 'string'], 'notes' => ['type' => 'string']]]]]], 'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Business rule violation (own RFQ, sanctions, exclusive supplier, etc.)']]],
            ],
        ],
    ]);
})->name('api.public.openapi');
