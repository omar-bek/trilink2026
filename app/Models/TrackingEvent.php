<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'description',
        'location',
        'event_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => 'array',
            'event_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
