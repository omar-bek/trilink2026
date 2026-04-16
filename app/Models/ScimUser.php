<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 7 — shadow row for a user provisioned by an external IdP via
 * SCIM 2.0. Carries the IdP's `external_id` so the controller can route
 * subsequent PATCH/DELETE requests back to the linked TriLink user.
 *
 * The model intentionally exposes minimal surface area — the SCIM
 * controller is the only authorised writer, and other parts of the
 * platform should never need to query this table directly.
 */
class ScimUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'external_id',
        'is_active',
        'scim_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scim_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
