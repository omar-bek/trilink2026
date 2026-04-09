<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 2.5 (UAE Compliance Roadmap — post-implementation hardening) —
 * an immutable snapshot of one published privacy policy version. See
 * the migration for the legal background.
 *
 * Append-only in production. The admin publish flow inserts a new
 * row; nothing edits or deletes existing rows. The DSAR export reads
 * by `id` (via the consents FK), never by `version` string, so
 * version-string reuse cannot retroactively change what a user
 * appears to have consented to.
 */
class PrivacyPolicyVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'body_en',
        'body_ar',
        'sha256',
        'effective_from',
        'changelog',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Compute the canonical sha256 used by the publish flow. The
     * input is `ar || "\n----\n" || en` so changing either body
     * produces a different hash. Centralised here so the publish
     * service and any verifier use the exact same recipe.
     */
    public static function canonicalSha256(string $bodyEn, string $bodyAr): string
    {
        return hash('sha256', $bodyAr . "\n----\n" . $bodyEn);
    }
}
