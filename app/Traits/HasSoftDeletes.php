<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSoftDeletes
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function softDelete(): bool
    {
        return $this->update(['deleted_at' => now()]);
    }

    public function restore(): bool
    {
        return $this->update(['deleted_at' => null]);
    }
}
