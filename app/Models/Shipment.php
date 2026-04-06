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
}
