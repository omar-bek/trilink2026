<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('path')->get();

        return view('dashboard.admin.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = Category::create($data + ['is_active' => $request->boolean('is_active', true)]);

        $this->audit(AuditAction::CREATE, $category);

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.created'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $category = Category::findOrFail($id);
        $before = $category->only(['name', 'name_ar', 'description', 'parent_id', 'is_active']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:categories,id', "not_in:{$id}"],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($data + ['is_active' => $request->boolean('is_active')]);

        $this->audit(AuditAction::UPDATE, $category, $before, $category->only(array_keys($before)));

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $category = Category::findOrFail($id);

        // Don't allow deleting categories with children — admin must reparent first.
        if ($category->children()->exists()) {
            return back()->withErrors(['category' => __('admin.categories.has_children')]);
        }

        $this->audit(AuditAction::DELETE, $category, $category->toArray(), null);

        $category->delete(); // soft delete

        return redirect()->route('admin.categories.index')->with('status', __('admin.categories.deleted'));
    }

    private function audit(AuditAction $action, Category $category, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'company_id' => auth()->user()?->company_id,
            'action' => $action,
            'resource_type' => 'Category',
            'resource_id' => $category->id,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'status' => 'success',
        ]);
    }
}
