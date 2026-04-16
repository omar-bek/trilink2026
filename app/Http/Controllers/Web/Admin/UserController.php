<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Admin-side user management. Admins can see ALL users across all companies
 * and create/edit/activate/deactivate them. Hard delete is intentionally
 * disabled — users are soft-deleted to preserve audit history and FK
 * integrity with PRs, bids, contracts, payments, etc.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $status = $request->query('status');
        $company = $request->query('company');

        $users = User::query()
            ->with('company')
            ->when($q !== '', fn ($query) => $query->search($q, ['first_name', 'last_name', 'email', 'phone']))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($company, fn ($query) => $query->where('company_id', $company))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => User::count(),
            'active' => User::where('status', UserStatus::ACTIVE->value)->count(),
            'pending' => User::where('status', UserStatus::PENDING->value)->count(),
            'inactive' => User::where('status', UserStatus::INACTIVE->value)->count(),
        ];

        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('dashboard.admin.users.index', compact('users', 'stats', 'companies', 'q', 'role', 'status', 'company'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('dashboard.admin.users.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);

        $user = User::create($data + [
            'password' => Hash::make($data['password'] ?? Str::password(14)),
            'status' => $data['status'] ?? UserStatus::ACTIVE->value,
        ]);

        $this->audit(AuditAction::CREATE, $user);

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('admin.users.created'));
    }

    public function edit(int $id): View
    {
        $user = User::findOrFail($id);
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('dashboard.admin.users.edit', compact('user', 'companies'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $before = $user->only(['first_name', 'last_name', 'email', 'phone', 'role', 'status', 'company_id']);

        $data = $this->validateUser($request, $user->id);

        // Don't let an admin demote their own admin role — protects against lockout.
        if ($user->id === auth()->id() && ($data['role'] ?? null) !== UserRole::ADMIN->value) {
            return back()->withErrors(['role' => __('admin.users.cannot_self_demote')]);
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        unset($data['password']);

        $user->fill($data)->save();

        $this->audit(AuditAction::UPDATE, $user, $before, $user->only(array_keys($before)));

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('admin.users.updated'));
    }

    public function toggleStatus(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Never let an admin deactivate themselves.
        abort_if($user->id === auth()->id(), 422);

        $before = ['status' => $user->status?->value];
        $user->status = $user->status === UserStatus::ACTIVE ? UserStatus::INACTIVE : UserStatus::ACTIVE;
        $user->save();

        $this->audit(AuditAction::UPDATE, $user, $before, ['status' => $user->status->value]);

        return back()->with('status', __('admin.users.status_toggled'));
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $newPassword = Str::password(14);
        $user->update(['password' => Hash::make($newPassword)]);

        $this->audit(AuditAction::UPDATE, $user, null, ['password_reset' => true]);

        return back()->with('status', __('admin.users.password_reset', ['password' => $newPassword]));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Never delete yourself.
        abort_if($user->id === auth()->id(), 422);

        $this->audit(AuditAction::DELETE, $user, $user->toArray(), null);

        $user->delete(); // soft delete

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('admin.users.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', new Enum(UserRole::class)],
            'status' => ['nullable', new Enum(UserStatus::class)],
            'company_id' => ['nullable', 'exists:companies,id'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
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
