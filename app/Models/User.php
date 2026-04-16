<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\Permissions;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements HasLocalePreference, JWTSubject
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, Searchable, SoftDeletes;

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
        'branch_id',
        'custom_permissions',
        'last_login',
        'notification_preferences',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            // 2FA: secret + recovery codes are encrypted at rest so a DB
            // leak still protects the second factor. `confirmed_at` is a
            // plain timestamp — its presence is the "enabled" flag.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            // Sprint D.17 — per-user notification preferences. Stored
            // as JSON so adding a new notification type doesn't require
            // a schema migration. See defaultNotificationPreferences()
            // and shouldDeliverNotification() for the read recipe.
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Default notification preferences applied when the user has not
     * explicitly customised theirs. Centralised here so the form, the
     * API and any future digest job all read from the same source of
     * truth.
     *
     * @return array<string,mixed>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'channels' => [
                'database' => true,
                'mail' => true,
            ],
            'digest' => [
                // realtime: notifications fire immediately as they happen
                // daily:    rolled up into a single morning summary
                // off:      delivered to the bell only, never emailed
                'mode' => 'realtime',
            ],
            'types' => [
                // Empty by default — when a type isn't listed here we
                // fall back to "channels" above.
            ],
        ];
    }

    /**
     * Resolve the effective channels for a notification type. The form
     * only stores deltas; this method does the merge so callers don't
     * need to know how the JSON is laid out.
     *
     * @return array<int,string>
     */
    public function deliveryChannelsFor(string $type): array
    {
        $prefs = $this->notification_preferences ?? self::defaultNotificationPreferences();

        // "Off" digest mode disables email entirely — bell stays on so
        // the user can still find what they missed.
        $digestMode = $prefs['digest']['mode'] ?? 'realtime';

        $perType = $prefs['types'][$type] ?? null;
        if (is_array($perType)) {
            $channels = $perType;
        } else {
            $channels = [];
            foreach (($prefs['channels'] ?? []) as $name => $enabled) {
                if ($enabled) {
                    $channels[] = $name;
                }
            }
        }

        if ($digestMode === 'off') {
            $channels = array_values(array_filter($channels, fn ($c) => $c !== 'mail'));
        }

        return $channels;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Implements Laravel's HasLocalePreference contract — when a
     * notification or mailable is dispatched to this user, Laravel
     * automatically calls App::setLocale() with this value before
     * rendering it. That makes Arabic-speaking users get Arabic
     * emails even though queue workers run with no HTTP context.
     */
    public function preferredLocale(): ?string
    {
        $locale = $this->locale;

        return in_array($locale, ['en', 'ar'], true) ? $locale : null;
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isBranchManager(): bool
    {
        return $this->role === UserRole::BRANCH_MANAGER;
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

    /**
     * Phase 4 — every user has at most one OPEN cart at a time. The
     * CartService::current() helper is the canonical accessor; this
     * relationship exists so eager-loading from a User context works
     * (e.g. `User::with('cart')` for the topbar count).
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function openCart(): HasOne
    {
        return $this->hasOne(Cart::class)->where('status', Cart::STATUS_OPEN)->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Query scope: only users whose status is active. Use this on every
     * bulk-fetch that feeds notifications or approval lists so inactive,
     * pending, and soft-deleted users are never contacted.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE);
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
     * Company manager = tenant-level super-user. Runs their company end to
     * end: team, RFQs, bids, contracts, payments, escrow, disputes, ESG,
     * integrations. Treated like admin INSIDE their own company scope —
     * policies still enforce cross-company isolation, so a manager cannot
     * read another company's records even with the permission bypass.
     */
    public function isCompanyManager(): bool
    {
        return $this->role === UserRole::COMPANY_MANAGER;
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
        // Company manager wears every hat inside the tenant — the web.role
        // middleware accepts them for buyer/supplier/finance/etc. routes so
        // they can act on behalf of any teammate without juggling secondary
        // role assignments. Cross-company boundaries are still enforced by
        // policies that key on company_id.
        if ($this->isCompanyManager()) {
            return array_map(
                fn (UserRole $r) => $r->value,
                UserRole::cases()
            );
        }

        $primary = $this->role instanceof \BackedEnum ? $this->role->value : (string) $this->role;
        $extras = \is_array($this->additional_roles) ? $this->additional_roles : [];

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
        // Platform admin + tenant-level company manager both short-circuit
        // to true. Admin is a global bypass; company_manager is a
        // *within-company* bypass — cross-company isolation is still
        // enforced by the policies (RfqPolicy / ContractPolicy / etc.)
        // which check `$user->company_id === $resource->company_id`.
        if ($this->isAdmin() || $this->isCompanyManager()) {
            return true;
        }

        if (! isset($this->resolvedPermissionKeys)) {
            $this->resolvedPermissionKeys = $this->resolvePermissionKeys();
        }

        return \in_array($key, $this->resolvedPermissionKeys, true);
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
        if (! isset($this->resolvedPermissionKeys)) {
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
        if (! empty($perUser)) {
            return array_values(array_unique($perUser));
        }

        // Step 2: union the defaults for the user's primary + additional roles.
        $roleNames = $this->allRoles();
        $keys = [];
        foreach ($roleNames as $role) {
            foreach (Permissions::defaultsForRole($role) as $k) {
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
            if (! $user->wasChanged('role') && ! $user->wasRecentlyCreated) {
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
