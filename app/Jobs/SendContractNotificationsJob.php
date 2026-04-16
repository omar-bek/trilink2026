<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Async fan-out for contract-related notifications. Resolves the
 * recipient list at job run time (not at dispatch time) so a
 * recipient_roles change between dispatch and run is honoured.
 *
 * Why a job (and not inline Notification::send):
 *   1. Inline send is synchronous — bid acceptance with a 40-user
 *      counter-party blocks the request thread for the duration of
 *      40 database inserts + 40 mail driver calls.
 *   2. The job runs on the `notifications` queue, same as the
 *      individual ShouldQueue notifications, so the request returns
 *      immediately and the worker handles the fan-out.
 *
 * The notification class itself is passed as a fully serialised
 * Notification instance (Laravel handles the SerializesModels
 * magic), so this single job can deliver any contract notification
 * — created, signed, amendment proposed/decided/message, renewal,
 * etc.
 *
 * Recipient resolution:
 *   - $companyIds — every company that should receive the
 *     notification (typically buyer + supplier party ids)
 *   - $excludeCompanyId — optional, the actor's own company so
 *     they don't get pinged about their own action
 *   - For each company, the recipient list is filtered by
 *     `notification_recipient_roles` if set; otherwise every user
 *     of that company is included (legacy behaviour).
 */
class SendContractNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        /** @var array<int, int> */
        public readonly array $companyIds,
        public readonly \Illuminate\Notifications\Notification $notification,
        public readonly ?int $excludeCompanyId = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $companyIds = collect($this->companyIds)
            ->filter()
            ->unique()
            ->reject(fn ($cid) => $this->excludeCompanyId !== null && (int) $cid === (int) $this->excludeCompanyId)
            ->values()
            ->all();

        if (empty($companyIds)) {
            return;
        }

        // Pre-fetch the recipient_roles config for every party
        // company in one query so the per-company filter is just an
        // array_intersect, not a fresh DB hit.
        $companyRoles = Company::whereIn('id', $companyIds)
            ->get(['id', 'notification_recipient_roles'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->notification_recipient_roles ?? null])
            ->all();

        $allRecipients = collect();
        foreach ($companyIds as $cid) {
            $configuredRoles = $companyRoles[$cid] ?? null;
            $query = User::query()->where('company_id', $cid);

            if (is_array($configuredRoles) && ! empty($configuredRoles)) {
                $query->whereIn('role', $configuredRoles);
            }
            // No filter when null/empty → legacy "notify everyone".

            $allRecipients = $allRecipients->merge($query->get());
        }

        $allRecipients = $allRecipients->unique('id');

        if ($allRecipients->isNotEmpty()) {
            try {
                Notification::send($allRecipients, $this->notification);
            } catch (\Throwable $e) {
                \Log::warning('SendContractNotificationsJob delivery failed', [
                    'recipients' => $allRecipients->count(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
