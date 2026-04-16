<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadService
{
    public function upload(UploadedFile $file, array $metadata = []): Upload
    {
        $disk = config('filesystems.default', 's3');
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = ($metadata['folder'] ?? 'uploads').'/'.date('Y/m').'/'.$fileName;

        Storage::disk($disk)->put($path, file_get_contents($file));

        return Upload::create([
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
            'category' => $metadata['category'] ?? null,
            'uploaded_by' => auth()->id(),
            'company_id' => auth()->user()?->company_id,
            'entity_type' => $metadata['entity_type'] ?? null,
            'entity_id' => $metadata['entity_id'] ?? null,
        ]);
    }

    public function find(int $id): ?Upload
    {
        return Upload::with(['uploader'])->find($id);
    }

    public function delete(int $id): bool
    {
        $upload = Upload::find($id);
        if (! $upload) {
            return false;
        }

        Storage::disk($upload->disk)->delete($upload->path);

        return $upload->delete();
    }
}
