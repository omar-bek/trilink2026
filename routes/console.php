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

// Negotiation counter-offer expiry sweeper. Runs every 5 minutes so a
// stale round is closed within a reasonable window without flooding the
// queue. Auto-reject writes an AuditLog row so the expired status is
// traceable back to the sweeper (no user action).
Schedule::command('negotiation:expire-rounds')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Payments hardening — daily late-fee accrual (one Payment row per overdue
// parent per month, statutory 12% cap) and release-condition sweeper for
// retention_period_elapsed and other matured auto-release signals.
Schedule::command('payments:accrue-late-fees')->dailyAt('05:30');
Schedule::command('escrow:sweep-release-conditions')->dailyAt('05:45');

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

// RFQ deadline reminders — hourly because the 2h tier needs to fire
// inside the same hour the RFQ crosses the 2h-out mark. The 48h /
// 24h tiers tolerate the hourly granularity comfortably.
Schedule::command('rfqs:deadline-reminders')
    ->hourly()
    ->withoutOverlapping();

// Daily payment overdue chase — 7/14/30 day tiers. 06:30 UTC right
// after the renewal alerts so the morning email is consolidated.
Schedule::command('payments:overdue-reminders')->dailyAt('06:30');

// Daily contract end-date reminder — 30/7/1 day tiers. Distinct from
// renewal-alerts because this targets ALL active contracts not just
// the ones flagged for auto-renewal.
Schedule::command('contracts:expiry-reminders')->dailyAt('06:15');

// Daily — sweep contracts that have been sitting in PENDING_SIGNATURES
// past their signing window and tell both parties the window has
// elapsed. Default window length is 14 days.
Schedule::command('contracts:expire-signature-windows')->dailyAt('05:00');

// Weekly — purge old read notifications from the database to keep
// the notifications table from growing unbounded. Anything read more
// than 60 days ago is safe to drop.
Schedule::command('notifications:cleanup')->weeklyOn(0, '02:30');

// Phase A — daily BG expiry reminders at 30/14/7/1 day tiers. Runs
// at 03:15 GST so the notifications land before the business day.
Schedule::command('bg:notify-expiring')->dailyAt('03:15');

// Phase G — daily retention release sweep. Creates a pending-approval
// payment for any contract past its retention_release_date with an
// un-released held balance.
Schedule::command('retention:release-due')->dailyAt('04:00');
