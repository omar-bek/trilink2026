<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private readonly UploadService $service,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB
            'category' => 'nullable|string',
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable|integer',
            'folder' => 'nullable|string',
        ]);

        $upload = $this->service->upload($request->file('file'), $request->only([
            'category', 'entity_type', 'entity_id', 'folder',
        ]));

        return $this->created($upload);
    }

    public function show(int $id): JsonResponse
    {
        $upload = $this->service->find($id);

        return $upload ? $this->success($upload) : $this->notFound();
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->service->delete($id)
            ? $this->success(null, 'File deleted')
            : $this->notFound();
    }
}
