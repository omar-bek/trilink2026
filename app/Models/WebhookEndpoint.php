<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 7 — customer-managed HTTPS endpoint that receives event payloads.
 * Each event sent to this endpoint is HMAC-SHA256 signed with the
 * `secret` so the customer can verify authenticity.
 */
class WebhookEndpoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'label',
        'url',
        'events',
        'secret',
        'is_active',
        'last_delivered_at',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
            'failure_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }

    /**
     * Returns true when this endpoint is interested in the given event.
     * An empty `events` field means "subscribe to everything".
     */
    public function listensTo(string $event): bool
    {
        $filter = trim((string) $this->events);
        if ($filter === '') {
            return true;
        }
        $list = array_map('trim', explode(',', $filter));

        return in_array($event, $list, true);
    }
}
