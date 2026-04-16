<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\ScreenCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function registerCompany(array $data): array
    {
        $result = DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'name_ar' => $data['company_name_ar'] ?? null,
                'registration_number' => $data['registration_number'],
                'tax_number' => $data['tax_number'] ?? null,
                'type' => $data['company_type'],
                'status' => CompanyStatus::PENDING,
                'email' => $data['company_email'] ?? null,
                'phone' => $data['company_phone'] ?? null,
                'website' => $data['website'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'description' => $data['description'] ?? null,
                // Phase 3 (UAE Compliance Roadmap) — Free Zone & Jurisdiction.
                // The registration controller derives these from the
                // selected free zone authority. Default to mainland /
                // federal when the registration source did not pass them
                // (legacy API callers, tests).
                'is_free_zone' => (bool) ($data['is_free_zone'] ?? false),
                'free_zone_authority' => $data['free_zone_authority'] ?? null,
                'is_designated_zone' => (bool) ($data['is_designated_zone'] ?? false),
                'legal_jurisdiction' => $data['legal_jurisdiction'] ?? 'federal',
            ]);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'role' => UserRole::COMPANY_MANAGER,
                'status' => UserStatus::ACTIVE,
                'company_id' => $company->id,
            ]);

            // Spatie role sync now happens inside the User model's `booted()`
            // saved-hook (with a try/catch so a missing seeded role can never
            // crash registration). Calling assignRole() here used to abort the
            // whole transaction silently if the `web` guard hadn't been seeded
            // — that bug surfaced as "submit does nothing" in the browser.

            $token = JWTAuth::fromUser($user);
            $refreshToken = $this->generateRefreshToken($user);

            return [
                'user' => $user->load('company'),
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'company' => $company,
            ];
        });

        // Phase 2 / Sprint 7 / task 2.2 — sanctions screening dispatched
        // to the queue rather than called inline. Keeps registration <500ms
        // even when the screening provider is slow or down. The job lands
        // a `review` verdict on three failed attempts so the company ends
        // up in the verification queue instead of going unscreened.
        ScreenCompany::dispatch(
            companyId: $result['company']->id,
            triggeredBy: $result['user']->id,
            useCache: false,
        );

        unset($result['company']);

        return $result;
    }

    public function register(array $data): array
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? UserRole::BUYER,
            'status' => UserStatus::ACTIVE,
            'company_id' => $data['company_id'] ?? null,
        ]);

        $user->assignRole($user->role->value);

        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);

        return [
            'user' => $user->load('company'),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
        ];
    }

    public function login(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        if (! $user->isActive()) {
            return null;
        }

        $user->update(['last_login' => now()]);

        $token = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);

        return [
            'user' => $user->load('company'),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update(['password' => $newPassword]);

        return true;
    }

    public function refresh(string $refreshToken): ?array
    {
        $userId = Cache::get("refresh_token:{$refreshToken}");
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);
        if (! $user || ! $user->isActive()) {
            return null;
        }

        Cache::forget("refresh_token:{$refreshToken}");

        $newToken = JWTAuth::fromUser($user);
        $newRefreshToken = $this->generateRefreshToken($user);

        return [
            'user' => $user->load('company'),
            'access_token' => $newToken,
            'refresh_token' => $newRefreshToken,
        ];
    }

    public function logout(string $token, ?string $refreshToken = null): void
    {
        $ttl = config('jwt.ttl', 60) * 60;
        Cache::put("blacklist:token:{$token}", true, $ttl);

        if ($refreshToken) {
            Cache::forget("refresh_token:{$refreshToken}");
        }
    }

    public function forgotPassword(string $email): ?string
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        return $token;
    }

    public function resetPassword(string $email, string $token, string $password): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return false;
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return false;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return false;
        }

        $user->update(['password' => $password]);
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
    }

    public function isTokenBlacklisted(string $token): bool
    {
        return Cache::has("blacklist:token:{$token}");
    }

    private function generateRefreshToken(User $user): string
    {
        $refreshToken = Str::random(64);
        $ttl = config('jwt.refresh_ttl', 20160);
        Cache::put("refresh_token:{$refreshToken}", $user->id, $ttl * 60);

        return $refreshToken;
    }
}
