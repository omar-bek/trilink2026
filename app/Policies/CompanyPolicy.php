<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

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

    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    public function delete(User $user, Company $company): bool
    {
        // Only admins can delete companies (handled by before())
        return false;
    }

    public function approve(User $user, Company $company): bool
    {
        // Only admins can approve (handled by before())
        return false;
    }

    public function manageUsers(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && $user->hasPermission('company.manage_users');
    }
}
