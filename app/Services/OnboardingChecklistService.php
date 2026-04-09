<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\ErpConnector;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Rfq;
use App\Models\User;
use App\Enums\DocumentType;

/**
 * Sprint B.6 — onboarding checklist for newly-registered companies.
 *
 * Lives as a small read-only service so the dashboard payload builder
 * can ask "what's still left for this user?" without duplicating the
 * derivation rules across roles. The service derives every step from
 * existing data — no new schema, no completion column to keep in sync,
 * no risk of "checked but not actually done".
 *
 * Returns a 5-step list with:
 *   - title / description (already translated)
 *   - done flag (boolean)
 *   - cta route + label for the "do it now" button
 *
 * The dashboard view hides the whole widget once every step is done so
 * power users don't see it forever.
 */
class OnboardingChecklistService
{
    /**
     * @return array{
     *     visible: bool,
     *     completed: int,
     *     total: int,
     *     percent: int,
     *     steps: array<int, array{key:string,title:string,description:string,done:bool,route:string,cta:string,optional:bool}>
     * }
     */
    public function for(?User $user): array
    {
        if (!$user || !$user->company_id) {
            return $this->empty();
        }

        $company = Company::find($user->company_id);
        if (!$company) {
            return $this->empty();
        }

        $isSupplier = in_array(
            $company->type instanceof \BackedEnum ? $company->type->value : (string) $company->type,
            ['supplier', 'service_provider', 'logistics', 'clearance'],
            true,
        );

        $steps = [
            [
                'key'         => 'company_info',
                'title'       => __('onboarding.step_company_info_title'),
                'description' => __('onboarding.step_company_info_desc'),
                'done'        => $this->companyInfoComplete($company),
                'route'       => route('dashboard.company.profile'),
                'cta'         => __('onboarding.step_company_info_cta'),
                'optional'    => false,
            ],
            [
                'key'         => 'trade_license',
                'title'       => __('onboarding.step_trade_license_title'),
                'description' => __('onboarding.step_trade_license_desc'),
                'done'        => $this->tradeLicenseUploaded($company),
                'route'       => route('dashboard.documents.index'),
                'cta'         => __('onboarding.step_trade_license_cta'),
                'optional'    => false,
            ],
            [
                'key'         => 'first_action',
                'title'       => $isSupplier
                    ? __('onboarding.step_first_product_title')
                    : __('onboarding.step_first_pr_title'),
                'description' => $isSupplier
                    ? __('onboarding.step_first_product_desc')
                    : __('onboarding.step_first_pr_desc'),
                'done'        => $isSupplier
                    ? $this->hasAnyProduct($company)
                    : $this->hasAnyPurchaseRequestOrRfq($company),
                'route'       => $isSupplier
                    ? route('dashboard.products.create')
                    : route('dashboard.purchase-requests.create'),
                'cta'         => $isSupplier
                    ? __('onboarding.step_first_product_cta')
                    : __('onboarding.step_first_pr_cta'),
                'optional'    => false,
            ],
            [
                'key'         => 'invite_team',
                'title'       => __('onboarding.step_invite_team_title'),
                'description' => __('onboarding.step_invite_team_desc'),
                'done'        => $this->teamInvited($company),
                'route'       => route('company.users'),
                'cta'         => __('onboarding.step_invite_team_cta'),
                'optional'    => false,
            ],
            [
                'key'         => 'connect_erp',
                'title'       => __('onboarding.step_erp_title'),
                'description' => __('onboarding.step_erp_desc'),
                'done'        => $this->erpConnected($company),
                'route'       => route('dashboard.integrations.index'),
                'cta'         => __('onboarding.step_erp_cta'),
                // ERP integration is optional. Marked so the UI can
                // render the badge differently and the "all done"
                // hide rule treats this step as already complete.
                'optional'    => true,
            ],
        ];

        $required  = array_filter($steps, fn ($s) => !$s['optional']);
        $completed = array_filter($required, fn ($s) => $s['done']);
        $total     = count($required);
        $count     = count($completed);
        $percent   = $total > 0 ? (int) round(($count / $total) * 100) : 100;

        return [
            // Hide the whole widget once every required step is done.
            // Optional steps don't gate visibility — a user who skips
            // ERP forever shouldn't see the checklist forever.
            'visible'   => $count < $total,
            'completed' => $count,
            'total'     => $total,
            'percent'   => $percent,
            'steps'     => $steps,
        ];
    }

    private function companyInfoComplete(Company $company): bool
    {
        return !empty($company->name)
            && !empty($company->tax_number)
            && !empty($company->country);
    }

    private function tradeLicenseUploaded(Company $company): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('company_documents')) {
            return false;
        }
        return CompanyDocument::query()
            ->where('company_id', $company->id)
            ->where('type', DocumentType::TRADE_LICENSE)
            ->exists();
    }

    private function hasAnyProduct(Company $company): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('products')) {
            return false;
        }
        return Product::query()->where('company_id', $company->id)->exists();
    }

    private function hasAnyPurchaseRequestOrRfq(Company $company): bool
    {
        if (PurchaseRequest::query()->where('company_id', $company->id)->exists()) {
            return true;
        }
        return Rfq::query()->where('company_id', $company->id)->exists();
    }

    private function teamInvited(Company $company): bool
    {
        return User::query()->where('company_id', $company->id)->count() > 1;
    }

    private function erpConnected(Company $company): bool
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('erp_connectors')) {
            return false;
        }
        return ErpConnector::query()->where('company_id', $company->id)->exists();
    }

    private function empty(): array
    {
        return [
            'visible'   => false,
            'completed' => 0,
            'total'     => 0,
            'percent'   => 0,
            'steps'     => [],
        ];
    }
}
