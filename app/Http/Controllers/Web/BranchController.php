<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manager-facing CRUD for company branches. Each branch can have a dedicated
 * branch_manager who only sees PRs/RFQs/Contracts inside that branch.
 *
 * Only the COMPANY_MANAGER may create/destroy branches; branch managers
 * cannot self-promote or create siblings.
 */
class BranchController extends Controller
{
    public function index(): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        $branches = Branch::with(['category', 'manager'])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate(20);

        return view('dashboard.branches.index', compact('branches'));
    }

    public function create(): View
    {
        $companyId = auth()->user()->company_id;
        abort_unless($companyId, 403);

        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $candidates = $this->managerCandidates($companyId);

        return view('dashboard.branches.create', compact('categories', 'candidates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->company_id, 403);

        $data = $this->validateData($request);

        $branch = Branch::create(array_merge($data, [
            'company_id' => $user->company_id,
        ]));

        // If a manager was picked, promote them to BRANCH_MANAGER and link
        // them to this branch in a single touch — saves a separate edit step.
        if (!empty($data['branch_manager_id'])) {
            $this->assignManager($branch, (int) $data['branch_manager_id'], $user->company_id);
        }

        return redirect()
            ->route('dashboard.branches.index')
            ->with('status', __('branches.created_successfully'));
    }

    public function edit(int $id): View
    {
        $user = auth()->user();
        $branch = Branch::where('company_id', $user->company_id)->findOrFail($id);

        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $candidates = $this->managerCandidates($user->company_id);

        return view('dashboard.branches.edit', compact('branch', 'categories', 'candidates'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $branch = Branch::where('company_id', $user->company_id)->findOrFail($id);

        $data = $this->validateData($request);

        $branch->update($data);

        if (!empty($data['branch_manager_id'])) {
            $this->assignManager($branch, (int) $data['branch_manager_id'], $user->company_id);
        }

        return redirect()
            ->route('dashboard.branches.index')
            ->with('status', __('branches.updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = auth()->user();
        $branch = Branch::where('company_id', $user->company_id)->findOrFail($id);

        // Soft-delete only — preserves historical PRs/RFQs that point to it.
        $branch->delete();

        return redirect()
            ->route('dashboard.branches.index')
            ->with('status', __('branches.deleted_successfully'));
    }

    /**
     * Promote the chosen user to BRANCH_MANAGER and pin them to this branch.
     * Defensive: validates the candidate belongs to the same company first.
     */
    private function assignManager(Branch $branch, int $userId, int $companyId): void
    {
        $candidate = User::where('company_id', $companyId)->find($userId);
        if (!$candidate) {
            return;
        }

        $candidate->update([
            'role'      => UserRole::BRANCH_MANAGER,
            'branch_id' => $branch->id,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function managerCandidates(int $companyId)
    {
        // Anyone in the company who isn't the company manager themselves can
        // be promoted to a branch manager.
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', UserStatus::ACTIVE)
            ->where('role', '!=', UserRole::COMPANY_MANAGER->value)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'role']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:191'],
            'name_ar'           => ['nullable', 'string', 'max:191'],
            'category_id'       => ['nullable', 'exists:categories,id'],
            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:100'],
            'country'           => ['nullable', 'string', 'size:2'],
            'branch_manager_id' => ['nullable', 'exists:users,id'],
            'is_active'         => ['sometimes', 'boolean'],
        ]);
    }
}
