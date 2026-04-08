<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved search owned by a user. See the migration docblock for the
 * design rationale; the model itself is mostly a typed window onto the
 * row + a couple of helpers used by the daily digest job.
 *
 * Phase 1 / task 1.5.
 */
class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'resource_type',
        'filters',
        'is_active',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'filters'          => 'array',
            'is_active'        => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Build a query-string suitable for re-rendering the saved search.
     * The digest email and the saved-search list both link to e.g.
     * `/dashboard/rfqs?...filters` — this method centralises that.
     */
    public function toQueryString(): string
    {
        return http_build_query(is_array($this->filters) ? $this->filters : []);
    }
}
