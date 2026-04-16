<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->when($filters['company_id'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('first_name', 'like', "%{$v}%")
                    ->orWhere('last_name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%");
            }))
            ->with('company')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function find(int $id): ?User
    {
        return User::with('company')->find($id);
    }

    public function getByCompany(int $companyId): Collection
    {
        return User::where('company_id', $companyId)->with('company')->get();
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->assignRole($user->role->value);

            return $user->load('company');
        });
    }

    public function update(int $id, array $data): ?User
    {
        $user = User::find($id);
        if (! $user) {
            return null;
        }

        $user->update($data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user->fresh('company');
    }

    public function updatePermissions(int $id, array $permissions): ?User
    {
        $user = User::find($id);
        if (! $user) {
            return null;
        }

        $user->update(['custom_permissions' => $permissions]);

        return $user->fresh('company');
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('company');
    }

    public function delete(int $id): bool
    {
        $user = User::find($id);

        return $user ? $user->delete() : false;
    }
}
