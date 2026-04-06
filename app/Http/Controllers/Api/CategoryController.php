<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->service->list($request->only(['parent_id', 'is_active', 'search', 'per_page'])));
    }

    public function tree(): JsonResponse
    {
        return $this->success($this->service->tree());
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->service->find($id);
        return $category ? $this->success($category) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
        ]);

        $category = $this->service->update($id, $data);
        return $category ? $this->success($category) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'Category deleted')
            : $this->notFound();
    }
}
