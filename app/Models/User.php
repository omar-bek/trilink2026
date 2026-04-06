<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * Spatie permissions are seeded under BOTH the `web` and `api` guards
     * (see RolesAndPermissionsSeeder), so we leave the guard unset and let
     * Spatie auto-detect from the active auth guard. This makes
     * `$user->hasPermissionTo()` work for session-based web requests AND
     * JWT-based API requests transparently.
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'role',
        'position_title',
        'additional_roles',
        'permissions',
        'status',
        'company_id',
        'custom_permissions',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'custom_permissions' => 'array',
            'additional_roles' => 'array',
            'permissions' => 'array',
            'last_login' => 'datetime',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role->value,
            'company_id' => $this->company_id,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'buyer_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'provider_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'buyer_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'raised_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isGovernment(): bool
    {
        return $this->role === UserRole::GOVERNMENT;
    }

    /**
     * All role keys this user can act as — primary `role` plus any
     * `additional_roles` granted by the company manager. Used by the
     * web.role middleware so multi-hat users (buyer + supplier, etc.)
     * pass role checks for any hat they wear.
     *
     * @return array<int, string>
     */
    public function allRoles(): array
    {
        $primary = $this->role instanceof \BackedEnum ? $this->role->value : (string) $this->role;
        $extras  = is_array($this->additional_roles) ? $this->additional_roles : [];

        return array_values(array_unique(array_filter(array_merge([$primary], $extras))));
    }

    /**
     * Whether this user has been granted the named permission key.
     *
     * Source of truth is the granular catalog in App\Support\Permissions.
     * Resolution order (first decisive match wins):
     *
     *   1. Admins always pass — platform owners are unrestricted.
     *
     *   2. If the user has anything in their `permissions` JSON column, we
     *      treat it as a STRICT ALLOWLIST. The company manager has explicitly
     *      decided "this team member can only do X, Y, Z" via the team UI.
     *      Anything not in the list is denied — even keys the role would
     *      normally grant. This is what makes "give a buyer only PR access"
     *      actually mean what it says.
     *
     *   3. Otherwise (empty/null permissions JSON) the user inherits the
     *      defaults for their primary role + any additional_roles, looked
     *      up from Permissions::defaultsForRole(). New seeded users with no
     *      explicit permissions still get sensible role behaviour.
     *
     * The resolved set is memoized per request so Blade `@can` is cheap
     * even when called dozens of times rendering a sidebar/menu.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!isset($this->resolvedPermissionKeys)) {
            $this->resolvedPermissionKeys = $this->resolvePermissionKeys();
        }

        return in_array($key, $this->resolvedPermissionKeys, true);
    }

    /**
     * Get the full effective permission set for this user (admins return [],
     * since admin bypass short-circuits hasPermission earlier). Useful for
     * UI surfaces that want to introspect what a user can do without calling
     * hasPermission for every key.
     *
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        if (!isset($this->resolvedPermissionKeys)) {
            $this->resolvedPermissionKeys = $this->resolvePermissionKeys();
        }
        return $this->resolvedPermissionKeys;
    }

    /** @var array<int,string>|null */
    protected ?array $resolvedPermissionKeys = null;

    /**
     * @return array<int, string>
     */
    protected function resolvePermissionKeys(): array
    {
        // Step 1: explicit per-user allowlist takes precedence.
        $perUser = is_array($this->permissions) ? array_values(array_filter($this->permissions)) : [];
        if (!empty($perUser)) {
            return array_values(array_unique($perUser));
        }

        // Step 2: union the defaults for the user's primary + additional roles.
        $roleNames = $this->allRoles();
        $keys = [];
        foreach ($roleNames as $role) {
            foreach (\App\Support\Permissions::defaultsForRole($role) as $k) {
                $keys[$k] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Whenever a user is created or has its `role` enum changed, mirror that
     * onto Spatie's role assignment table so `$user->hasRole()` and
     * `$user->hasPermissionTo()` Just Work — no manual assignRole() needed.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (!$user->wasChanged('role') && !$user->wasRecentlyCreated) {
                return;
            }

            $primary = $user->role instanceof \BackedEnum ? $user->role->value : (string) ($user->role ?? '');
            if ($primary === '') {
                return;
            }

            try {
                // syncRoles replaces existing assignments — this keeps Spatie
                // and the User.role enum in lockstep.
                $user->syncRoles([$primary]);
            } catch (\Throwable $e) {
                // Roles seeder hasn't run yet (e.g. very first migration) —
                // skip silently. The next save will catch up.
            }
        });
    }
}
