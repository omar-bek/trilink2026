<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->service->list($request->only(['type', 'status', 'search', 'per_page'])));
    }

    public function show(int $id): JsonResponse
    {
        $company = $this->service->find($id);

        return $company ? $this->success($company) : $this->notFound();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'registration_number' => 'required|string|unique:companies',
            'type' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        return $this->created($this->service->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'type' => 'sometimes|string',
            'status' => 'sometimes|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $company = $this->service->update($id, $data);

        return $company ? $this->success($company) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'Company deleted')
            : $this->notFound();
    }

    public function approve(int $id): JsonResponse
    {
        $company = Company::find($id);
        if (! $company) {
            return $this->notFound();
        }

        $company->update(['status' => CompanyStatus::ACTIVE]);

        return $this->success($company->fresh(), 'Company approved');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $company = Company::find($id);
        if (! $company) {
            return $this->notFound();
        }

        $company->update(['status' => CompanyStatus::INACTIVE]);

        return $this->success($company->fresh(), 'Company rejected');
    }

    public function addDocuments(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array',
            'documents.*.type' => 'required|string',
            'documents.*.url' => 'required|string',
        ]);

        $company = Company::find($id);
        if (! $company) {
            return $this->notFound();
        }

        $documents = $company->documents ?? [];
        foreach ($request->documents as $doc) {
            $documents[] = array_merge($doc, ['uploaded_at' => now()->toISOString()]);
        }

        $company->update(['documents' => $documents]);

        return $this->success($company->fresh(), 'Documents added');
    }

    public function linkCategories(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $this->service->linkCategories($id, $data['category_ids']);

        return $this->success(null, 'Categories linked');
    }

    public function unlinkCategory(int $companyId, int $categoryId): JsonResponse
    {
        $company = Company::find($companyId);
        if (! $company) {
            return $this->notFound();
        }

        $company->categories()->detach($categoryId);

        return $this->success(null, 'Category unlinked');
    }

    public function getCategories(int $id): JsonResponse
    {
        $company = Company::find($id);
        if (! $company) {
            return $this->notFound();
        }

        return $this->success($company->categories);
    }
}
