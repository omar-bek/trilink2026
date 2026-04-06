<?php

namespace App\Models;

use App\Enums\FileCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Upload extends Model
{
    protected $fillable = [
        'file_name',
        'original_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'category',
        'uploaded_by',
        'company_id',
        'entity_type',
        'entity_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => FileCategory::class,
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrl(): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(30));
    }
}
