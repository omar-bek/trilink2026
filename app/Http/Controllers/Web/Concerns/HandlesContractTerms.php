<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Enums\ContractStatus;
use App\Jobs\SendContractNotificationsJob;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Shared helpers for any controller that reads/writes contract terms or
 * acts on an amendment lifecycle. Extracted from ContractController so
 * the sub-controllers (Signing, Amendment, Versions) can call the same
 * logic without duplication or inheritance.
 *
 * Depends on the FormatsForViews trait being applied to the consumer
 * (for statusValue()) — every controller in this package already uses it.
 */
trait HandlesContractTerms
{
    /**
     * Decode the contract's `terms` column into a flat array of
     * `{title, items[]}` sections in a specific locale. Three storage
     * shapes are supported: bilingual envelope {en, ar}, flat array, or
     * plain text (legacy).
     *
     * @return array<int, array{title:string, items: array<int,string>}>
     */
    protected function parseTermsSections(mixed $terms, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();

        if (is_array($terms)) {
            if (isset($terms['en']) || isset($terms['ar'])) {
                $picked = $terms[$locale] ?? $terms['en'] ?? $terms['ar'] ?? [];
                return $this->parseTermsSections($picked, $locale);
            }

            return collect($terms)->map(fn ($section) => [
                'title' => (string) ($section['title'] ?? ''),
                'items' => array_values(array_filter((array) ($section['items'] ?? []))),
            ])->all();
        }

        if (is_string($terms) && trim($terms) !== '') {
            $decoded = json_decode($terms, true);
            if (is_array($decoded)) {
                return $this->parseTermsSections($decoded, $locale);
            }

            $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $terms))));
            if (empty($lines)) {
                return [];
            }
            return [[
                'title' => __('contracts.terms_conditions'),
                'items' => $lines,
            ]];
        }

        return [];
    }

    protected function termsAreBilingual(mixed $terms): bool
    {
        if (is_string($terms)) {
            $terms = json_decode($terms, true);
        }
        return is_array($terms) && (isset($terms['en']) || isset($terms['ar']));
    }

    /**
     * Server-side gate for the bilateral amendment window. Clause wording
     * is settled BEFORE the e-signature is collected; after the contract
     * is fully signed, the only way to change a clause is to terminate
     * and re-issue.
     */
    protected function canAmendNow(Contract $contract): bool
    {
        $statusValue = $this->statusValue($contract->status);
        $preSignatureStatus = in_array(
            $statusValue,
            [ContractStatus::DRAFT->value, ContractStatus::PENDING_SIGNATURES->value],
            true
        );
        return $preSignatureStatus && !$contract->allPartiesHaveSigned();
    }

    protected function displayName(User $user): string
    {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->email ?? 'A party');
    }

    /**
     * Fan out an amendment-related notification to every party company
     * except the excluded one (typically the actor's own company).
     *
     * Wrapped in try/catch so a queue outage can NEVER roll back a
     * contract action — the contract row is the source of truth, the
     * notification is best-effort.
     */
    protected function notifyAmendment(
        Contract $contract,
        ContractAmendment $amendment,
        Notification $notification,
        ?int $excludeCompanyId = null,
    ): void {
        try {
            $partyCompanyIds = collect($contract->parties ?? [])
                ->pluck('company_id')
                ->push($contract->buyer_company_id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($partyCompanyIds)) {
                return;
            }

            SendContractNotificationsJob::dispatch(
                companyIds: $partyCompanyIds,
                notification: $notification,
                excludeCompanyId: $excludeCompanyId,
            );
        } catch (\Throwable $e) {
            Log::warning('Amendment notification dispatch failed', [
                'contract_id'  => $contract->id,
                'amendment_id' => $amendment->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
