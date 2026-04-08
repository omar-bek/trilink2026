<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tracking_number',
        'contract_id',
        'company_id',
        'logistics_company_id',
        'status',
        'origin',
        'destination',
        'current_location',
        'inspection_status',
        'customs_clearance_status',
        'customs_documents',
        'estimated_delivery',
        'actual_delivery',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'origin' => 'array',
            'destination' => 'array',
            'current_location' => 'array',
            'customs_documents' => 'array',
            'estimated_delivery' => 'datetime',
            'actual_delivery' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'logistics_company_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->orderByDesc('event_at');
    }

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment) {
            if (!$shipment->tracking_number) {
                $shipment->tracking_number = 'SHP-' . strtoupper(uniqid());
            }
        });
    }

    /**
     * Real progress percentage 0-100, derived from the canonical phase
     * order. The phases are linear (preparing → in_transit → at_customs →
     * out_for_delivery → delivered), so progress is just the index of the
     * current phase divided by the total. "delivered" is always 100, any
     * unknown status is 0.
     *
     * Used by both the index card and the show page progress bar so they
     * stay consistent.
     */
    public function realProgress(): int
    {
        $phases = [
            ShipmentStatus::IN_PRODUCTION->value    => 20,
            ShipmentStatus::READY_FOR_PICKUP->value => 35,
            ShipmentStatus::IN_TRANSIT->value       => 60,
            ShipmentStatus::IN_CLEARANCE->value     => 75,
            ShipmentStatus::DELIVERED->value        => 100,
            ShipmentStatus::CANCELLED->value        => 0,
        ];

        $value = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;

        return $phases[$value] ?? 0;
    }
}
