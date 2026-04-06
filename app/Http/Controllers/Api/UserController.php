<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service,
        private readonly AuthService $authService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['company_id', 'role', 'status', 'search', 'per_page']);

        if (!auth()->user()->isAdmin()) {
            $filters['company_id'] = auth()->user()->company_id;
        }

        return $this->success($this->service->list($filters));
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->service->find($id);
        return $user ? $this->success($user) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$id}",
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|string',
            'status' => 'sometimes|string',
        ]);

        $user = $this->service->update($id, $data);
        return $user ? $this->success($user) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'User deleted')
            : $this->notFound();
    }

    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'permissions' => 'required|array',
        ]);

        $user = $this->service->updatePermissions($id, $data['permissions']);
        return $user ? $this->success($user, 'Permissions updated') : $this->notFound();
    }

    public function changePassword(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->service->find($id);
        if (!$user) return $this->notFound();

        $success = $this->authService->changePassword($user, $data['current_password'], $data['password']);

        return $success
            ? $this->success(null, 'Password changed')
            : $this->error('Current password is incorrect', 422);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $this->service->updateProfile(auth()->user(), $data);
        return $this->success($user);
    }

    public function getByCompany(int $companyId): JsonResponse
    {
        return $this->success($this->service->getByCompany($companyId));
    }
}
