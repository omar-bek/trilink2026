<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UaeHoliday extends Model
{
    protected $table = 'uae_holidays';

    protected $fillable = [
        'holiday_date', 'name', 'name_ar', 'scope', 'confirmation',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    /**
     * Memoised set of holiday dates (YYYY-MM-DD strings) so the
     * settlement-calendar helpers below avoid a DB hit on every call.
     * Invalidated when a holiday row is added or edited via observer —
     * keep the TTL short (1h) as a safety net even if cache invalidation
     * misses.
     */
    public static function datesFor(string $scope = 'federal'): array
    {
        return Cache::remember("uae_holidays.$scope", 3600, function () use ($scope) {
            return static::query()
                ->whereIn('scope', [$scope, 'federal'])
                ->pluck('holiday_date')
                ->map(fn ($d) => $d->format('Y-m-d'))
                ->all();
        });
    }
}
