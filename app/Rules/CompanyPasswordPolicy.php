<?php

namespace App\Rules;

use App\Models\CompanySecurityPolicy;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Validation rule that enforces the calling company's password policy.
 *
 * Used by every path that sets a password — register, password reset,
 * profile password change. The policy is resolved from the company the
 * new password belongs to (for existing users) or from platform defaults
 * (for first-time registration where the user has no company yet).
 */
class CompanyPasswordPolicy implements ValidationRule
{
    public function __construct(
        private readonly ?User $user = null,
        private readonly ?int $companyId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('auth.password_too_weak'))->translate();

            return;
        }

        $policy = $this->resolvePolicy();

        if (strlen($value) < (int) $policy['password_min_length']) {
            $fail(__('auth.password_too_weak'))->translate();

            return;
        }

        if ($policy['password_require_mixed_case'] && ! (preg_match('/[a-z]/', $value) && preg_match('/[A-Z]/', $value))) {
            $fail(__('auth.password_too_weak'))->translate();

            return;
        }

        if ($policy['password_require_number'] && ! preg_match('/\d/', $value)) {
            $fail(__('auth.password_too_weak'))->translate();

            return;
        }

        if ($policy['password_require_symbol'] && ! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail(__('auth.password_too_weak'))->translate();

            return;
        }

        // Password history — reject if the new password matches any of
        // the last N hashes persisted on the user row. History depth 0
        // skips this check entirely (useful for companies that don't
        // want reuse restrictions).
        if ($this->user && (int) $policy['password_history_count'] > 0) {
            $history = is_array($this->user->password_history) ? $this->user->password_history : [];
            foreach ($history as $hash) {
                if (is_string($hash) && Hash::check($value, $hash)) {
                    $fail(__('auth.password_recently_used'))->translate();

                    return;
                }
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function resolvePolicy(): array
    {
        $companyId = $this->companyId ?? $this->user?->company_id;

        if ($companyId) {
            $policy = CompanySecurityPolicy::where('company_id', $companyId)->first();
            if ($policy) {
                return $policy->only([
                    'password_min_length',
                    'password_require_mixed_case',
                    'password_require_number',
                    'password_require_symbol',
                    'password_history_count',
                ]);
            }
        }

        return CompanySecurityPolicy::platformDefaults();
    }
}
