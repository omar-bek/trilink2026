<?php

namespace App\Services;

use App\Models\UaeHoliday;
use Carbon\CarbonInterface;

/**
 * Settlement calendar that honours UAE weekends (Saturday–Sunday after
 * the 2022 calendar change; before then Friday–Saturday) and public
 * holidays. Every payment-timing helper on the platform routes through
 * here so we never promise same-day settlement on Eid.
 */
class SettlementCalendarService
{
    /**
     * Post-2022 UAE weekend: Saturday + Sunday. The federal Cabinet
     * moved the official weekend in January 2022. Keep as a constant
     * so edge cases (ENBD processes on Saturday) can override per-bank.
     *
     * Carbon weekday ints: Sunday=0, Monday=1 ... Saturday=6.
     */
    private const WEEKEND_DAYS = [6, 0]; // Sat, Sun

    public function isBusinessDay(CarbonInterface $date, string $scope = 'federal'): bool
    {
        if (in_array($date->dayOfWeek, self::WEEKEND_DAYS, true)) {
            return false;
        }

        return ! in_array($date->format('Y-m-d'), UaeHoliday::datesFor($scope), true);
    }

    /**
     * Returns $date if it's a business day, otherwise the next one.
     * Idempotent — calling twice is safe.
     */
    public function nextBusinessDay(CarbonInterface $date, string $scope = 'federal'): CarbonInterface
    {
        $out = $date->copy();
        while (! $this->isBusinessDay($out, $scope)) {
            $out->addDay();
        }

        return $out;
    }

    public function previousBusinessDay(CarbonInterface $date, string $scope = 'federal'): CarbonInterface
    {
        $out = $date->copy();
        while (! $this->isBusinessDay($out, $scope)) {
            $out->subDay();
        }

        return $out;
    }

    public function addBusinessDays(CarbonInterface $date, int $n, string $scope = 'federal'): CarbonInterface
    {
        $out = $date->copy();
        $added = 0;
        while ($added < $n) {
            $out->addDay();
            if ($this->isBusinessDay($out, $scope)) {
                $added++;
            }
        }

        return $out;
    }
}
