<?php

namespace App\Providers;

use App\Models\Bid;
use App\Models\Contract;
use App\Models\Dispute;
use App\Models\EscrowAccount;
use App\Models\EscrowRelease;
use App\Models\Payment;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Events\ContractSigned;
use App\Events\ShipmentDelivered;
use App\Listeners\ReleaseEscrowOnDelivery;
use App\Listeners\ReleaseEscrowOnSignature;
use App\Models\Shipment;
use App\Models\User;
use App\Observers\AuditLogObserver;
use App\Observers\ContractObserver;
use App\Observers\SidebarBadgeInvalidator;
use App\Services\AI\AnthropicClient;
use App\Services\Credit\CreditScoringProviderInterface;
use App\Services\Credit\MockCreditScoringProvider;
use App\Services\Escrow\BankPartnerFactory;
use App\Services\Escrow\BankPartnerInterface;
use App\Services\Logistics\DubaiTradeAdapter;
use App\Services\Sanctions\OpenSanctionsProvider;
use App\Services\Sanctions\SanctionsProviderInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Models\Company;
use App\Policies\BidPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContractPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RfqPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Phase 2 / Sprint 7 / task 2.1 — sanctions provider abstraction.
        // The interface is bound to OpenSanctions for now; Phase 3 swaps it
        // for Refinitiv on enterprise tenants by re-binding here behind a
        // config flag. The constructor pulls the (optional) API key from
        // services.sanctions.opensanctions.api_key so the bare provider
        // class stays free of config dependencies.
        $this->app->bind(SanctionsProviderInterface::class, function () {
            return new OpenSanctionsProvider(
                apiKey: config('services.sanctions.opensanctions.api_key'),
                timeout: (int) config('services.sanctions.opensanctions.timeout', 8),
            );
        });

        // Phase 2 / Sprint 10 / task 2.16 — credit scoring provider
        // abstraction. Mock implementation today (deterministic per
        // registration_number); Phase 3 swaps in AECB / SIMAH / D&B by
        // re-binding here behind a config-based selector.
        $this->app->bind(CreditScoringProviderInterface::class, function () {
            return new MockCreditScoringProvider();
        });

        // Phase 3 / Sprint 11 / task 3.3 — bank partner adapter for the
        // escrow workflow. Tenants pick the bank via services.escrow.default
        // (mock by default so demos work without a real partnership). The
        // factory itself is a singleton so listeners + commands + the web
        // controller all share one resolver.
        $this->app->singleton(BankPartnerFactory::class);
        $this->app->bind(BankPartnerInterface::class, function ($app) {
            $factory = $app->make(BankPartnerFactory::class);
            return $factory->make($factory->defaultKey());
        });

        // Phase 5 — shared Claude client for every AI service (OCR,
        // negotiation assistant, risk analysis, copilot). Singleton so
        // every service gets the same configured instance.
        $this->app->singleton(AnthropicClient::class, function () {
            return new AnthropicClient(
                apiKey: config('services.anthropic.api_key'),
                model: config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
            );
        });

        // Phase 6 — Dubai Trade adapter for customs declarations.
        // Singleton because LogisticsController + future cron jobs both
        // need the same configured instance.
        $this->app->singleton(DubaiTradeAdapter::class, function () {
            return new DubaiTradeAdapter(
                apiKey: config('services.dubai_trade.api_key'),
                baseUrl: config('services.dubai_trade.base_url', 'https://api.sandbox.dubaitrade.ae/customs/v1'),
                timeout: (int) config('services.dubai_trade.timeout', 12),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Model Policies ──────────────────────────────────────────
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(Bid::class, BidPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Rfq::class, RfqPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Contract sign attempts. Step-up authentication uses the
        // user's password, so a leaked password could be brute-forced
        // through the sign endpoint. We cap to 5 attempts per minute
        // per (user, contract) pair so a determined attacker has to
        // pace themselves and the legitimate user (who occasionally
        // typos the password) is not affected.
        RateLimiter::for('contract.sign', function (Request $request) {
            $userId   = $request->user()?->id ?: $request->ip();
            $contract = $request->route('id') ?: '0';
            return Limit::perMinute(5)->by("contract.sign:{$userId}:{$contract}");
        });

        // Audit logging (Phase 0 / task 0.11). Every state change on the
        // six core transactional models writes an `audit_logs` row via
        // App\Observers\AuditLogObserver. Single shared observer keeps the
        // wiring trivial — see the class docblock for the rationale.
        Contract::observe(AuditLogObserver::class);
        Bid::observe(AuditLogObserver::class);
        Rfq::observe(AuditLogObserver::class);
        PurchaseRequest::observe(AuditLogObserver::class);
        Payment::observe(AuditLogObserver::class);
        Shipment::observe(AuditLogObserver::class);

        // Phase 3 / Sprint A — escrow movements (account state flips +
        // every individual deposit / release / refund) are financial
        // events and must produce an immutable audit row. Without this,
        // a malicious bank-partner adapter or a privilege-escalated user
        // could trigger an off-ledger release and there would be no
        // tamper-evident record of who pulled the trigger.
        EscrowAccount::observe(AuditLogObserver::class);
        EscrowRelease::observe(AuditLogObserver::class);

        // Phase 1 (UAE Compliance Roadmap) — auto-issue tax invoices when
        // a Payment row transitions into COMPLETED. The observer dispatches
        // IssueTaxInvoiceJob (ShouldBeUnique on payment_id) so duplicate
        // status flips from concurrent webhooks don't double-issue.
        Payment::observe(\App\Observers\PaymentInvoiceObserver::class);

        // Sidebar badge cache invalidation. Every entity that contributes
        // to a sidebar count clears the per-company cache the moment its
        // row changes, so freshly-created RFQs / bids / contracts appear
        // in the sidebar instantly instead of waiting for the 60s TTL.
        // Single fan-out point for "contract was just created" — covers
        // every entry path (RFQ → bid accept, Buy-Now, cart checkout)
        // so the supplier never finds a contract on their dashboard
        // without having been notified about it.
        Contract::observe(ContractObserver::class);

        Contract::observe(SidebarBadgeInvalidator::class);
        Bid::observe(SidebarBadgeInvalidator::class);
        Rfq::observe(SidebarBadgeInvalidator::class);
        PurchaseRequest::observe(SidebarBadgeInvalidator::class);
        Payment::observe(SidebarBadgeInvalidator::class);
        Shipment::observe(SidebarBadgeInvalidator::class);
        Dispute::observe(SidebarBadgeInvalidator::class);
        EscrowAccount::observe(SidebarBadgeInvalidator::class);

        // Phase 3 / Sprint 12 — auto-release listeners. Both run on the
        // 'escrow' queue so the originating HTTP request returns
        // immediately and the bank API call happens asynchronously.
        Event::listen(ContractSigned::class, ReleaseEscrowOnSignature::class);
        Event::listen(ShipmentDelivered::class, ReleaseEscrowOnDelivery::class);

        // Failed-job surfacing: every permanently-failed job (all retries
        // exhausted) writes an ERROR-level structured log line so our
        // aggregator / Sentry / oncall alert picks it up. The Laravel
        // default only persists the row into failed_jobs — without this,
        // a silent queue outage could rot for hours before anyone noticed.
        Queue::failing(function (JobFailed $event) {
            Log::error('queue.job.failed', [
                'connection' => $event->connectionName,
                'queue'      => $event->job->getQueue(),
                'job'        => $event->job->resolveName(),
                'payload'    => $event->job->getRawBody(),
                'exception'  => [
                    'class'   => get_class($event->exception),
                    'message' => $event->exception->getMessage(),
                    'file'    => $event->exception->getFile(),
                    'line'    => $event->exception->getLine(),
                ],
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Authorization gate fallback
        |--------------------------------------------------------------------------
        |
        | This `before` callback runs before any defined Gate ability check
        | (including @can in Blade and $user->can() in code). It lets us:
        |
        |   1. Short-circuit admins to always-allowed (return true).
        |   2. Resolve any unknown ability name as a permission key against
        |      User::hasPermission(), which itself consults Spatie role-based
        |      permissions seeded by RolesAndPermissionsSeeder.
        |
        | Returning null falls through to any explicitly-defined Gate, so this
        | does not break callers that register specific abilities.
        */
        Gate::before(function (?User $user, string $ability) {
            if (!$user) {
                return null;
            }
            if ($user->isAdmin()) {
                return true;
            }
            // Only treat dotted ability names as permission keys (e.g.
            // "payments.approve") so we don't accidentally hijack policy
            // method names like "view" or "update".
            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }
            return null;
        });
    }
}
