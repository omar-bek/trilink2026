<?php

namespace App\Models;

use App\Enums\DisputeOfferStatus;
use App\Enums\DisputeRemedy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisputeOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_id', 'parent_offer_id',
        'offered_by_user_id', 'offered_by_company_id',
        'amount', 'currency', 'remedy', 'terms',
        'status', 'expires_at',
        'responded_at', 'responded_by', 'response_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisputeOfferStatus::class,
            'remedy' => DisputeRemedy::class,
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function offeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }

    public function offeredByCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'offered_by_company_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DisputeOffer::class, 'parent_offer_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(DisputeOffer::class, 'parent_offer_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === DisputeOfferStatus::PENDING;
    }
}
