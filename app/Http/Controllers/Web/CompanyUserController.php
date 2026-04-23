<?php

namespace App\Http\Controllers\Web;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Lets a company manager fully run their company's team:
 *
 * - create users with primary + additional roles
 * - assign a job title (position_title)
 * - tick fine-grained permission boxes from the catalog
 * - edit, deactivate, reset password, soft-delete
 *
 * Tenancy is strict: every action is scoped to the manager's own
 * `company_id`. Cross-company access is impossible from this controller.
 */
class CompanyUserController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('team.view'), 403);
        $companyId = $user?->company_id;
        abort_unless($companyId, 403);

        $q = trim((string) $request->query('q', ''));

        $users = User::where('company_id', $companyId)
            ->when($q !== '', fn ($query) => $query->search($q, ['first_name', 'last_name', 'email', 'position_title']))
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.company.users.index', compact('users', 'q'));
    }

    public function create(): View
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('team.invite'), 403);
        $companyId = $user?->company_id;
        abort_unless($companyId, 403);

        return view('dashboard.company.users.create', [
            'user' => null,
            'assignableRoles' => UserRole::assignableByCompanyManager(),
            'permissionCatalog' => Permissions::catalog(),
            'roleDefaults' => $this->roleDefaultsMap(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user?->hasPermission('team.invite'), 403);
        $companyId = $user?->company_id;
        abort_unless($companyId, 403);

        $data = $this->validateUser($request);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'position_title' => $data['position_title'] ?? null,
            'additional_roles' => $data['additional_roles'] ?? [],
            'permissions' => $data['permissions'] ?? [],
            'company_id' => $companyId,
            'status' => $data['status'] ?? UserStatus::ACTIVE->value,
            'password' => Hash::make($data['password'] ?? Str::password(14)),
        ]);

        $this->audit(AuditAction::CREATE, $user);

        return redirect()
            ->route('company.users')
            ->with('status', __('company.users.created'));
    }

    public function edit(int $id): View
    {
        abort_unless(auth()->user()?->hasPermission('team.edit'), 403);

        $user = $this->findInCompany($id);

        return view('dashboard.company.users.edit', [
            'user' => $user,
            'assignableRoles' => UserRole::assignableByCompanyManager(),
            'permissionCatalog' => Permissions::catalog(),
            'roleDefaults' => $this->roleDefaultsMap(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('team.edit'), 403);

        $user = $this->findInCompany($id);
        $before = $user->only(['first_name', 'last_name', 'email', 'phone', 'role', 'position_title', 'additional_roles', 'permissions', 'status']);

        $data = $this->validateUser($request, $user->id);

        // A manager cannot demote themselves out of company_manager via this
        // form (extra safety — the form doesn't expose company_manager as an
        // option, but defence in depth never hurts).
        if ($user->id === auth()->id() && $user->role === UserRole::COMPANY_MANAGER) {
            $data['role'] = UserRole::COMPANY_MANAGER->value;
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'position_title' => $data['position_title'] ?? null,
            'additional_roles' => $data['additional_roles'] ?? [],
            'permissions' => $data['permissions'] ?? [],
            'status' => $data['status'] ?? $user->status?->value,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        $this->audit(AuditAction::UPDATE, $user, $before, $user->only(array_keys($before)));

        return redirect()
            ->route('company.users')
            ->with('status', __('company.users.updated'));
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('team.edit'), 403);

        $user = $this->findInCompany($id);
        abort_if($user->id === auth()->id(), 422);

        $before = ['status' => $user->status?->value];
        $user->status = $user->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;
        $user->save();

        $this->audit(AuditAction::UPDATE, $user, $before, ['status' => $user->status->value]);

        return back()->with('status', __('company.users.status_toggled'));
    }

    public function resetPassword(int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('team.edit'), 403);

        $user = $this->findInCompany($id);

        $newPassword = Str::password(14);
        $user->update(['password' => Hash::make($newPassword)]);

        $this->audit(AuditAction::UPDATE, $user, null, ['password_reset' => true]);

        return back()->with('status', __('company.users.password_reset', ['password' => $newPassword]));
    }

    public function destroy(int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('team.remove'), 403);

        $user = $this->findInCompany($id);
        abort_if($user->id === auth()->id(), 422);

        $this->audit(AuditAction::DELETE, $user, $user->toArray(), null);
        $user->delete();

        return redirect()->route('company.users')->with('status', __('company.users.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?int $ignoreId = null): array
    {
        $assignable = array_map(fn ($r) => $r->value, UserRole::assignableByCompanyManager());

        // Manager-only permission keys (the `company_admin` group) must
        // never be assignable to a team member — otherwise a manager
        // could hand out the bypass that lets any user edit bank details,
        // security policy, etc. Strip them from the validation whitelist.
        $delegatable = array_values(array_diff(
            Permissions::all(),
            Permissions::catalog()['company_admin'] ?? []
        ));

        // Email domain allowlist — sourced from the company policy, so a
        // tenant that locks invites to @acme.com cannot accidentally
        // invite an external contractor. Empty list = no restriction.
        $company = $request->user()?->company;
        $allowedDomains = $company?->securityPolicy()->allowed_email_domains ?? [];

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
            'position_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in($assignable)],
            'additional_roles' => ['nullable', 'array'],
            'additional_roles.*' => [Rule::in($assignable)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in($delegatable)],
            'status' => ['nullable', new Enum(UserStatus::class)],
            'password' => ['nullable', 'string', new \App\Rules\CompanyPasswordPolicy(null, $company?->id)],
        ]);

        if (! empty($allowedDomains)) {
            $domain = strtolower(substr((string) strrchr($data['email'], '@'), 1));
            if (! in_array($domain, $allowedDomains, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => __('auth.email_domain_not_allowed'),
                ]);
            }
        }

        return $data;
    }

    private function findInCompany(int $id): User
    {
        $companyId = auth()->user()?->company_id;
        abort_unless($companyId, 403);

        return User::where('company_id', $companyId)->findOrFail($id);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function roleDefaultsMap(): array
    {
        $map = [];
        foreach (UserRole::assignableByCompanyManager() as $r) {
            $map[$r->value] = Permissions::defaultsForRole($r->value);
        }

        return $map;
    }

    private function audit(AuditAction $action, User $user, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => auth()->user()?->company_id,
            'action' => $action,
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'status' => 'success',
        ]);
    }
}
