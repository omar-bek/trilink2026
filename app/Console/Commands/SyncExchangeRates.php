<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 / Sprint 14 / task 3.13 — daily currency rates sync. Pulls a
 * snapshot from Open Exchange Rates (free tier returns rates against USD)
 * and dumps it into the `exchange_rates` table. Each row is keyed by
 * (from_currency, to_currency, as_of), so re-running the same day
 * overwrites existing rows in place via updateOrCreate.
 *
 * Without an OPENEXCHANGERATES_APP_ID configured, the command falls back
 * to a static seed of common GCC + USD + EUR rates so the FX-aware UI
 * still has something to show on a fresh database.
 */
class SyncExchangeRates extends Command
{
    protected $signature = 'fx:sync {--date= : ISO date to record (defaults to today)}';
    protected $description = 'Sync daily currency exchange rates into the exchange_rates table.';

    /**
     * Currencies we care about — every contract on TriLink today is in
     * one of these. Adding a new currency means adding it here AND
     * making sure Open Exchange Rates returns it.
     */
    private const CURRENCIES = ['AED', 'SAR', 'QAR', 'KWD', 'BHD', 'OMR', 'USD', 'EUR', 'GBP', 'CNY', 'INR', 'EGP'];

    public function handle(): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $rates = $this->fetchUsdRates();

        if (empty($rates)) {
            $this->warn('No rates available — using static fallback seed.');
            $rates = $this->fallbackUsdRates();
            $source = 'fallback';
        } else {
            $source = 'openexchangerates';
        }

        // Open Exchange Rates returns "1 USD = X CCY". We persist the
        // direct USD→CCY pair AND a CCY→USD pair so lookups in either
        // direction hit a row without triangulation.
        $written = 0;
        foreach ($rates as $code => $rate) {
            if (!in_array($code, self::CURRENCIES, true) || $rate <= 0) {
                continue;
            }

            ExchangeRate::updateOrCreate(
                ['from_currency' => 'USD', 'to_currency' => $code, 'as_of' => $asOf->toDateString()],
                ['rate' => $rate, 'source' => $source],
            );

            ExchangeRate::updateOrCreate(
                ['from_currency' => $code, 'to_currency' => 'USD', 'as_of' => $asOf->toDateString()],
                ['rate' => 1.0 / $rate, 'source' => $source],
            );

            $written += 2;
        }

        // Bust the in-process cache the ExchangeRate model uses so the
        // next request sees the new rates immediately.
        Cache::flush();

        $this->info("FX sync complete: {$written} rows written for {$asOf->toDateString()} ({$source}).");
        return self::SUCCESS;
    }

    /**
     * Pull "1 USD = X CCY" rates from Open Exchange Rates. Returns an
     * empty array on any error so the caller can fall back to the seed.
     */
    private function fetchUsdRates(): array
    {
        $appId = config('services.openexchangerates.app_id');
        if (!$appId) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('services.openexchangerates.timeout', 8))
                ->get('https://openexchangerates.org/api/latest.json', [
                    'app_id' => $appId,
                    'base'   => 'USD',
                ]);

            if ($response->failed()) {
                return [];
            }

            return (array) ($response->json('rates') ?? []);
        } catch (\Throwable $e) {
            Log::warning('FX sync failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Static seed used when no API key is configured. Numbers are
     * approximate mid-2026 values; the goal is just to make sure the UI
     * has something plausible to render. The daily cron is supposed to
     * overwrite this from the live API in production.
     */
    private function fallbackUsdRates(): array
    {
        return [
            'AED' => 3.6725,
            'SAR' => 3.7500,
            'QAR' => 3.6400,
            'KWD' => 0.3070,
            'BHD' => 0.3760,
            'OMR' => 0.3845,
            'USD' => 1.0000,
            'EUR' => 0.9200,
            'GBP' => 0.7900,
            'CNY' => 7.2500,
            'INR' => 83.5000,
            'EGP' => 47.6000,
        ];
    }
}
