<?php

namespace App\Console\Commands;

use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Daily digest job — Phase 1 / task 1.6.
 *
 * Walks every active SavedSearch, runs the corresponding query against
 * the database, filters the rows by the owner's `rfq_match_threshold`
 * preference (task 1.7, default 50%), and emails one digest per user
 * bundling all of their saved searches that produced new hits.
 *
 * "New" means: created since the saved search's `last_notified_at` (or
 * since `created_at` on first run). After a successful send, every
 * touched saved search has its `last_notified_at` bumped to now() so
 * tomorrow's run only picks up the next cohort.
 */
class SendSavedSearchDigests extends Command
{
    protected $signature   = 'digest:saved-searches';
    protected $description = 'Email daily digests of new matches for every active saved search';

    public function handle(): int
    {
        $byUser = SavedSearch::query()
            ->where('is_active', true)
            ->orderBy('user_id')
            ->get()
            ->groupBy('user_id');

        $sent = 0;

        foreach ($byUser as $userId => $searches) {
            $user = User::with('company.categories')->find($userId);
            if (! $user) {
                continue;
            }

            $threshold  = $this->matchThreshold($user);
            $bundles    = [];
            $touchedIds = [];

            foreach ($searches as $search) {
                $hits = $this->evaluateSearch($search, $user, $threshold);
                if ($hits['count'] === 0) {
                    continue;
                }

                $bundles[]    = $hits;
                $touchedIds[] = $search->id;
            }

            if ($bundles === []) {
                continue;
            }

            try {
                Notification::send($user, new SavedSearchDigestNotification($bundles));
                SavedSearch::whereIn('id', $touchedIds)->update(['last_notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->info("Sent {$sent} digest email(s).");

        return self::SUCCESS;
    }

    /**
     * Evaluate a single saved search and return a bundle in the shape
     * the notification expects: { label, count, url, items[] }.
     */
    private function evaluateSearch(SavedSearch $search, User $user, int $threshold): array
    {
        return match ($search->resource_type) {
            'rfqs'    => $this->evaluateRfqSearch($search, $user, $threshold),
            default   => ['label' => $search->label, 'count' => 0, 'url' => '', 'items' => []],
        };
    }

    /**
     * RFQ saved search — re-applies the same filters that
     * RfqController::supplierIndex uses, then scores each row against
     * the supplier company and keeps only the ones at or above the
     * user's match threshold.
     */
    private function evaluateRfqSearch(SavedSearch $search, User $user, int $threshold): array
    {
        $filters = $search->filters ?? [];
        $since   = $search->last_notified_at ?? $search->created_at;

        $base = Rfq::query()
            ->where('status', RfqStatus::OPEN->value)
            ->where('created_at', '>=', $since);

        if ($user->company_id) {
            $base->where('company_id', '!=', $user->company_id);
        }

        if (! empty($filters['q'])) {
            $base->search($filters['q'], ['title', 'rfq_number']);
        }
        if (! empty($filters['category'])) {
            $base->where('category_id', (int) $filters['category']);
        }

        // Only score against the supplier when there's a viewing company.
        $supplierCompany = $user->company_id
            ? Company::with('categories:id,parent_id')->find($user->company_id)
            : null;

        $rfqs = $base->with(['category', 'company'])->limit(50)->get();

        $scored = $rfqs
            ->map(fn (Rfq $r) => [
                'rfq'   => $r,
                'score' => $supplierCompany ? $r->matchScoreFor($supplierCompany) : 0,
            ])
            ->filter(fn ($row) => $row['score'] >= $threshold)
            ->values();

        return [
            'label' => $search->label,
            'count' => $scored->count(),
            'url'   => url('/dashboard/rfqs?' . $search->toQueryString()),
            'items' => $scored->take(5)->map(fn ($row) => [
                'rfq_number' => $row['rfq']->rfq_number,
                'title'      => $row['rfq']->title,
                'match'      => $row['score'],
            ])->all(),
        ];
    }

    /**
     * Look up the match threshold from the user's notification prefs.
     * Stored under `custom_permissions['notifications']['rfq_match_threshold']`
     * — same JSON column that the existing notification preferences live in.
     * Default 50% so saved searches don't spam the user out of the gate.
     */
    private function matchThreshold(User $user): int
    {
        $prefs = is_array($user->custom_permissions ?? null)
            ? ($user->custom_permissions['notifications'] ?? [])
            : [];

        $value = $prefs['rfq_match_threshold'] ?? 50;

        return (int) max(0, min(100, $value));
    }
}
