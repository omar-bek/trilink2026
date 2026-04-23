<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions;

class CompanyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editing the company identity (name, registration, address, tax
     * number) is a manager-only action. Previously the Settings page
     * only checked `company_id`, which let any team member mutate the
     * legal identity of their own company — not acceptable for a B2B
     * platform where the name on the invoice has legal weight.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_PROFILE_EDIT));
    }

    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    public function approve(User $user, Company $company): bool
    {
        return false;
    }

    public function manageUsers(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission('company.manage_users'));
    }

    /**
     * Gate for bank account / billing settings. This is the highest-
     * impact setting on the platform — it controls where the company's
     * money is received — so it is locked to the company manager and
     * the one explicit `company.billing.manage` grant.
     */
    public function manageBilling(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_BILLING_MANAGE));
    }

    public function manageSecurity(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_SECURITY_MANAGE));
    }

    public function manageDefaults(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_DEFAULTS_MANAGE));
    }

    public function manageApiTokens(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_API_TOKENS_MANAGE));
    }

    public function manageBranding(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_BRANDING_MANAGE));
    }

    public function manageApprovals(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_APPROVALS_MANAGE));
    }

    public function viewAudit(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && ($user->isCompanyManager() || $user->hasPermission(Permissions::COMPANY_AUDIT_VIEW));
    }
}
