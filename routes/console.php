<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily housekeeping for the Document Vault — flips expired documents and
// notifies managers about ones expiring within 30 days. Runs at 03:00 UTC
// to stay out of business hours across the GCC.
Schedule::command('documents:expire')->dailyAt('03:00');

// Phase 1 / task 1.6 — daily digest of new matching results for every
// active saved search. 07:00 UTC = 11:00 GST so users see the email
// when they sit down to start their day.
Schedule::command('digest:saved-searches')->dailyAt('07:00');

// Phase 2 / Sprint 7 / task 2.4 — daily re-screen against the sanctions
// provider for every active company that hasn't been screened in the last
// 7 days (or whose last screening errored). Dispatches one ScreenCompany
// job per company onto the `sanctions` queue so the scheduler tick stays
// fast even at 10K+ companies. 04:00 UTC = 08:00 GST, before business
// hours so any blocked accounts are surfaced when admins log in.
Schedule::command('sanctions:rescreen')->dailyAt('04:00');

// Phase 3 / Sprint 13 / task 3.11 — escrow auto-release sweeper. Runs
// every 10 minutes to catch any milestone whose release_condition should
// have fired but didn't (queue worker died, listener errored, manual
// inspection-pass condition with no listener yet). Idempotent.
Schedule::command('escrow:sweep')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Phase 3 / Sprint 14 / task 3.13 — daily FX rates pull from Open Exchange
// Rates. 02:00 UTC = 06:00 GST, before the FX desks at most regional
// banks open so the published rates align with what the bank quotes us.
Schedule::command('fx:sync')->dailyAt('02:00');

// Phase 0 (UAE Compliance Roadmap) — daily integrity check on the audit
// log hash chain. Runs quietly so successful runs don't fill the log; only
// chain breaks (which indicate tampering) print output. 01:00 UTC = 05:00
// GST, before any other scheduled job runs so we get a clean checkpoint
// of the previous day's activity.
Schedule::command('audit:verify-chain --quiet-success')->dailyAt('01:00');

// Phase 0 skeleton — daily archive sweep. Currently a no-op (dry-run only)
// until Phase 8 wires up S3 Object Lock. Scheduling it now means the
// retention math is exercised every day so config drift is caught early.
Schedule::command('audit:archive --dry-run')->dailyAt('01:30');

// Contract renewal alerts — daily fan-out for contracts approaching
// their end date (90 / 60 / 30 day buckets). 06:00 UTC = 10:00 GST,
// before the typical procurement team morning so they see the alert
// when they sit down for the day.
Schedule::command('contracts:renewal-alerts')->dailyAt('06:00');
