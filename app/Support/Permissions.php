<?php

namespace App\Support;

/**
 * Catalog of fine-grained permission keys a company manager can assign to
 * team members. Each group corresponds to a domain in the platform; each
 * key is a single capability the user is granted (or not).
 *
 * The catalog is intentionally a static array — a manager-controlled UI
 * that picks from this list is far simpler to reason about than a fully
 * dynamic permission system. Add new keys here and they appear in the UI.
 */
class Permissions
{
    public const PR_VIEW = 'pr.view';

    public const PR_CREATE = 'pr.create';

    public const PR_EDIT = 'pr.edit';

    public const PR_SUBMIT = 'pr.submit';

    public const PR_APPROVE = 'pr.approve';

    public const PR_DELETE = 'pr.delete';

    public const RFQ_VIEW = 'rfq.view';

    public const RFQ_CREATE = 'rfq.create';

    public const RFQ_EDIT = 'rfq.edit';

    public const RFQ_PUBLISH = 'rfq.publish';

    public const RFQ_CLOSE = 'rfq.close';

    public const BID_VIEW = 'bid.view';

    public const BID_SUBMIT = 'bid.submit';

    public const BID_WITHDRAW = 'bid.withdraw';

    public const BID_ACCEPT = 'bid.accept';

    public const BID_COMPARE = 'bid.compare';

    public const CONTRACT_VIEW = 'contract.view';

    public const CONTRACT_SIGN = 'contract.sign';

    public const CONTRACT_PDF = 'contract.pdf';

    public const PAYMENT_VIEW = 'payment.view';

    public const PAYMENT_APPROVE = 'payment.approve';

    public const PAYMENT_PROCESS = 'payment.process';

    public const PAYMENT_REJECT = 'payment.reject';

    public const SHIPMENT_VIEW = 'shipment.view';

    public const SHIPMENT_TRACK = 'shipment.track';

    // Phase 3 — Trade Finance MVP. Escrow workflow lives behind a tight
    // four-permission set so finance teams can split duties (e.g. one
    // user activates + deposits, a separate user releases).
    public const ESCROW_VIEW = 'escrow.view';

    public const ESCROW_ACTIVATE = 'escrow.activate';

    public const ESCROW_DEPOSIT = 'escrow.deposit';

    public const ESCROW_RELEASE = 'escrow.release';

    // Phase 4 — Catalog & Cart. cart.use lets a buyer interact with their
    // own cart (add, update, checkout). cart.checkout is the same gate as
    // PR_SUBMIT effectively — once you can buy, you can settle the cart.
    public const CART_USE = 'cart.use';

    public const CART_CHECKOUT = 'cart.checkout';

    // Phase 5 — single ai.use gate for all AI features (OCR, copilot,
    // negotiation assistant, risk analysis). Per-feature data
    // permissions (contract.view, bid.view) still apply on top.
    public const AI_USE = 'ai.use';

    // Phase 7 — integrations.manage gates webhook endpoints + ERP
    // connectors. Manager-only because it touches credentials and
    // outbound side-effects.
    public const INTEGRATIONS_MANAGE = 'integrations.manage';

    // Phase 8 — ESG & sustainability. Read for any user, write reserved
    // for the company manager.
    public const ESG_VIEW = 'esg.view';

    public const ESG_MANAGE = 'esg.manage';

    public const DISPUTE_VIEW = 'dispute.view';

    public const DISPUTE_OPEN = 'dispute.open';

    public const DISPUTE_ESCALATE = 'dispute.escalate';

    public const DISPUTE_RESOLVE = 'dispute.resolve';

    public const TEAM_VIEW = 'team.view';

    public const TEAM_INVITE = 'team.invite';

    public const TEAM_EDIT = 'team.edit';

    public const TEAM_REMOVE = 'team.remove';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    /**
     * Catalog grouped by domain. Keys are translation suffixes; the UI
     * looks them up via __('perm.<key>').
     *
     * @return array<string, array<int, string>>
     */
    public static function catalog(): array
    {
        return [
            'purchase_requests' => [
                self::PR_VIEW, self::PR_CREATE, self::PR_EDIT,
                self::PR_SUBMIT, self::PR_APPROVE, self::PR_DELETE,
            ],
            'rfqs' => [
                self::RFQ_VIEW, self::RFQ_CREATE, self::RFQ_EDIT,
                self::RFQ_PUBLISH, self::RFQ_CLOSE,
            ],
            'bids' => [
                self::BID_VIEW, self::BID_SUBMIT, self::BID_WITHDRAW,
                self::BID_ACCEPT, self::BID_COMPARE,
            ],
            'contracts' => [
                self::CONTRACT_VIEW, self::CONTRACT_SIGN, self::CONTRACT_PDF,
            ],
            'payments' => [
                self::PAYMENT_VIEW, self::PAYMENT_APPROVE,
                self::PAYMENT_PROCESS, self::PAYMENT_REJECT,
            ],
            'shipments' => [
                self::SHIPMENT_VIEW, self::SHIPMENT_TRACK,
            ],
            'escrow' => [
                self::ESCROW_VIEW, self::ESCROW_ACTIVATE,
                self::ESCROW_DEPOSIT, self::ESCROW_RELEASE,
            ],
            'cart' => [
                self::CART_USE, self::CART_CHECKOUT,
            ],
            'ai' => [
                self::AI_USE,
            ],
            'integrations' => [
                self::INTEGRATIONS_MANAGE,
            ],
            'esg' => [
                self::ESG_VIEW, self::ESG_MANAGE,
            ],
            'disputes' => [
                self::DISPUTE_VIEW, self::DISPUTE_OPEN, self::DISPUTE_ESCALATE, self::DISPUTE_RESOLVE,
            ],
            'team' => [
                self::TEAM_VIEW, self::TEAM_INVITE,
                self::TEAM_EDIT, self::TEAM_REMOVE,
            ],
            'reports' => [
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
            ],
        ];
    }

    /**
     * Flat list of every permission key the catalog knows about. Used for
     * validation in CompanyUserController to reject unknown keys.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::catalog())));
    }

    /**
     * Sensible defaults for each role. Pre-checks the matching boxes when
     * a manager picks a role in the create-user form so they don't have to
     * tick everything by hand. They can still customise freely afterwards.
     *
     * @return array<int, string>
     */
    public static function defaultsForRole(string $role): array
    {
        return match ($role) {
            // Company managers run their company — they hold every catalog
            // permission by default and can re-distribute via the team UI.
            'company_manager' => self::all(),

            // Branch managers mirror company_manager permissions but the
            // controllers/services scope writes to their branch_id only. The
            // BranchScopedQueries trait enforces this at the data layer.
            'branch_manager' => self::all(),

            // Government users see + intervene on disputes/payments/contracts
            // platform-wide; they don't create commercial records.
            'government' => [
                self::CONTRACT_VIEW, self::CONTRACT_PDF,
                self::PAYMENT_VIEW,
                self::SHIPMENT_VIEW,
                self::DISPUTE_VIEW, self::DISPUTE_ESCALATE, self::DISPUTE_RESOLVE,
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
            ],

            'buyer' => [
                self::PR_VIEW, self::PR_CREATE, self::PR_EDIT,
                self::PR_SUBMIT, self::PR_APPROVE, self::PR_DELETE,
                self::RFQ_VIEW, self::RFQ_CREATE, self::RFQ_EDIT,
                self::RFQ_PUBLISH, self::RFQ_CLOSE,
                // bid.submit lets a buyer submit purchase bids on other
                // companies' SALES_OFFER RFQs. Self-RFQ bids are still
                // blocked at BidService level.
                self::BID_VIEW, self::BID_SUBMIT, self::BID_COMPARE, self::BID_ACCEPT,
                self::CONTRACT_VIEW, self::CONTRACT_SIGN, self::CONTRACT_PDF,
                self::PAYMENT_VIEW, self::PAYMENT_APPROVE, self::PAYMENT_REJECT,
                self::SHIPMENT_VIEW,
                self::DISPUTE_VIEW, self::DISPUTE_OPEN, self::DISPUTE_ESCALATE,
                // Phase 3 — buyers run the escrow flow end-to-end.
                self::ESCROW_VIEW, self::ESCROW_ACTIVATE,
                self::ESCROW_DEPOSIT, self::ESCROW_RELEASE,
                // Phase 4 — buyers run the cart end-to-end.
                self::CART_USE, self::CART_CHECKOUT,
                // Phase 5 — buyers can use the AI copilot + risk analysis.
                self::AI_USE,
                // Phase 8 — read-only ESG visibility for buyers.
                self::ESG_VIEW,
            ],
            'supplier', 'service_provider' => [
                self::RFQ_VIEW,
                self::BID_VIEW, self::BID_SUBMIT, self::BID_WITHDRAW,
                self::CONTRACT_VIEW, self::CONTRACT_SIGN,
                self::SHIPMENT_VIEW,
                self::DISPUTE_VIEW, self::DISPUTE_OPEN,
                // Suppliers can SEE escrow custody on contracts they're a
                // party of (visibility into when funds will release) but
                // they can't deposit / release / refund — those actions
                // belong exclusively to the buyer side.
                self::ESCROW_VIEW,
                // Phase 5 — suppliers get the negotiation assistant +
                // copilot too. Same data, different perspective.
                self::AI_USE,
                // Phase 8 — suppliers maintain their own ESG record so
                // it's read+manage on their tenant.
                self::ESG_VIEW, self::ESG_MANAGE,
            ],
            'sales' => [
                // Sales reps create SALES_OFFER RFQs to advertise inventory.
                self::RFQ_VIEW, self::RFQ_CREATE, self::RFQ_EDIT, self::RFQ_PUBLISH,
                self::BID_VIEW, self::BID_SUBMIT, self::BID_COMPARE, self::BID_ACCEPT,
                self::CONTRACT_VIEW, self::CONTRACT_SIGN,
            ],
            'sales_manager' => [
                self::RFQ_VIEW, self::RFQ_CREATE, self::RFQ_EDIT, self::RFQ_PUBLISH, self::RFQ_CLOSE,
                self::BID_VIEW, self::BID_SUBMIT, self::BID_WITHDRAW, self::BID_COMPARE, self::BID_ACCEPT,
                self::CONTRACT_VIEW, self::CONTRACT_SIGN,
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
            ],
            'finance' => [
                self::PAYMENT_VIEW, self::PAYMENT_PROCESS,
                self::CONTRACT_VIEW,
                self::REPORTS_VIEW,
                // Finance can see + deposit into escrow but not activate
                // or release (split-duty default — manager grants more).
                self::ESCROW_VIEW, self::ESCROW_DEPOSIT,
            ],
            'finance_manager' => [
                self::PAYMENT_VIEW, self::PAYMENT_APPROVE,
                self::PAYMENT_PROCESS, self::PAYMENT_REJECT,
                self::CONTRACT_VIEW,
                self::REPORTS_VIEW, self::REPORTS_EXPORT,
                // Finance managers run the entire escrow workflow.
                self::ESCROW_VIEW, self::ESCROW_ACTIVATE,
                self::ESCROW_DEPOSIT, self::ESCROW_RELEASE,
            ],
            'logistics' => [
                self::SHIPMENT_VIEW, self::SHIPMENT_TRACK,
                self::CONTRACT_VIEW,
            ],
            'clearance' => [
                self::SHIPMENT_VIEW, self::SHIPMENT_TRACK,
                self::CONTRACT_VIEW,
            ],
            default => [],
        };
    }
}
