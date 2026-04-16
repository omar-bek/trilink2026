<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks dashboard pages for users whose company is still PENDING admin
 * approval (or has been suspended). They are auto-logged-in at the end of
 * the registration flow so they don't have to remember a password — but
 * until an admin clicks Approve, the only place they should be able to
 * reach is the registration-success "pending review" page.
 *
 * Bypassed for:
 *   - admins (they have no company; they review)
 *   - government users (platform-side)
 *   - users without a company (lone accounts)
 */
class EnsureCompanyApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform roles never need a company to be approved.
        if (in_array($user->role, [UserRole::ADMIN, UserRole::GOVERNMENT], true)) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return $next($request);
        }

        if ($company->status === CompanyStatus::ACTIVE) {
            return $next($request);
        }

        // Pending or suspended → push the user to the holding page.
        return redirect()->route('register.success');
    }
}
