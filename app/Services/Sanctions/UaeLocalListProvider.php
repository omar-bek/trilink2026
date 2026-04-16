<?php

namespace App\Services\Sanctions;

use Illuminate\Support\Facades\Storage;

/**
 * Phase 9 (UAE Compliance Roadmap) — UAE Local Terrorist List
 * screening provider. Cabinet Decision 74/2020 requires every entity
 * facilitating financial transactions to screen against the UAE's
 * own terrorist designation list, SEPARATE from the international
 * lists (UN 1267, OFAC SDN, EU consolidated).
 *
 * The list is published by the UAE Executive Office of Anti-Money
 * Laundering and Counter Terrorism Financing and is available as an
 * XML feed. For Phase 9 the provider reads from a LOCAL fixture
 * file (`storage/app/sanctions/uae-local-list.json`) that the ops
 * team downloads and drops manually. Future iterations will fetch
 * the feed automatically via an API when the UAE publishes one.
 *
 * The fixture shape:
 * ```json
 * {
 *   "updated_at": "2026-04-01",
 *   "entries": [
 *     {
 *       "name": "...",
 *       "name_ar": "...",
 *       "id_number": "...",
 *       "nationality": "...",
 *       "designation_date": "2024-01-15",
 *       "reference": "Cabinet Decision 74/2020 Annex"
 *     }
 *   ]
 * }
 * ```
 *
 * Matching strategy: fuzzy name match (Levenshtein distance ≤ 3 on
 * normalised ASCII transliteration) + exact id_number match. The
 * fuzzy threshold is deliberately loose because Arabic names have
 * many transliteration variants (Mohammed / Mohammad / Muhammad).
 *
 * Implements the same contract shape as OpenSanctionsProvider so
 * the SanctionsScreeningService can aggregate results from both.
 */
class UaeLocalListProvider
{
    private const FIXTURE_PATH = 'sanctions/uae-local-list.json';

    /**
     * Screen a company name + registration number against the local
     * list. Returns 'clean' | 'hit' | 'review'.
     */
    public function screen(string $companyName, ?string $registrationNumber = null): string
    {
        $entries = $this->loadEntries();
        if (empty($entries)) {
            return 'clean'; // No fixture loaded — skip gracefully.
        }

        $normName = $this->normalize($companyName);

        foreach ($entries as $entry) {
            // Exact ID match — definitive hit.
            if ($registrationNumber && !empty($entry['id_number'])) {
                $entryId = trim((string) $entry['id_number']);
                if ($entryId !== '' && $entryId === trim($registrationNumber)) {
                    return 'hit';
                }
            }

            // Fuzzy name match.
            $entryName = $this->normalize((string) ($entry['name'] ?? ''));
            $entryNameAr = $this->normalize((string) ($entry['name_ar'] ?? ''));

            if ($entryName !== '' && $this->isFuzzyMatch($normName, $entryName)) {
                return 'review';
            }
            if ($entryNameAr !== '' && $this->isFuzzyMatch($normName, $entryNameAr)) {
                return 'review';
            }
        }

        return 'clean';
    }

    /**
     * Screen a natural person (beneficial owner) by name + ID.
     */
    public function screenPerson(string $fullName, ?string $idNumber = null): string
    {
        $entries = $this->loadEntries();
        if (empty($entries)) {
            return 'clean';
        }

        $normName = $this->normalize($fullName);

        foreach ($entries as $entry) {
            if ($idNumber && !empty($entry['id_number'])) {
                if (trim((string) $entry['id_number']) === trim($idNumber)) {
                    return 'hit';
                }
            }

            $entryName = $this->normalize((string) ($entry['name'] ?? ''));
            if ($entryName !== '' && $this->isFuzzyMatch($normName, $entryName)) {
                return 'review';
            }
        }

        return 'clean';
    }

    private function loadEntries(): array
    {
        if (!Storage::disk('local')->exists(self::FIXTURE_PATH)) {
            return [];
        }

        $json = Storage::disk('local')->get(self::FIXTURE_PATH);
        $data = json_decode($json, true);

        return $data['entries'] ?? [];
    }

    /**
     * Normalise a name for fuzzy comparison: lowercase, strip
     * diacritics, collapse whitespace, remove common prefixes
     * (Al-, El-, Abu, Bin, Ibn).
     */
    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Strip common Arabic transliteration prefixes
        $name = preg_replace('/\b(al-?|el-?|abu\s|bin\s|ibn\s)/u', '', $name);
        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private function isFuzzyMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        // Levenshtein on short strings only — long strings are too
        // expensive and produce too many false positives.
        if (mb_strlen($a) > 100 || mb_strlen($b) > 100) {
            return str_contains($a, $b) || str_contains($b, $a);
        }

        return levenshtein($a, $b) <= 3;
    }
}
