<?php

namespace App\Services\Sanctions;

use App\Models\SanctionsScreening;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenSanctions.org-backed implementation of {@see SanctionsProviderInterface}.
 *
 * OpenSanctions consolidates 200+ public watchlists (OFAC, UN, EU, HMT,
 * national PEP lists, etc.) into a single search endpoint. The free tier
 * is rate-limited but adequate for the H1 transaction volume — Phase 3
 * upgrades to Refinitiv World-Check for enterprise customers.
 *
 * The provider only handles the HTTP I/O. Caching, persistence, and
 * downstream effects (company status flip, admin alerts) all live in
 * {@see \App\Services\SanctionsScreeningService} so swapping providers
 * doesn't change behaviour.
 */
class OpenSanctionsProvider implements SanctionsProviderInterface
{
    private const ENDPOINT = 'https://api.opensanctions.org/match/default';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 8,
    ) {
    }

    public function code(): string
    {
        return 'opensanctions';
    }

    public function screen(string $name, ?string $country = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return $this->error('Empty company name passed to screening');
        }

        try {
            $payload = [
                'queries' => [
                    'q1' => [
                        'schema'     => 'Company',
                        'properties' => [
                            'name'    => [$name],
                            'country' => $country ? [strtolower($country)] : [],
                        ],
                    ],
                ],
            ];

            $request = Http::timeout($this->timeout)
                ->withHeaders(['Accept' => 'application/json']);

            // Optional authenticated tier — set OPENSANCTIONS_API_KEY in .env
            // when the customer has a paid plan to bypass rate limits.
            if ($this->apiKey) {
                $request = $request->withToken($this->apiKey);
            }

            $response = $request->post(self::ENDPOINT, $payload);

            if (!$response->successful()) {
                return $this->error("HTTP {$response->status()}");
            }

            $body    = $response->json();
            $results = $body['responses']['q1']['results'] ?? [];
            $count   = count($results);

            if ($count === 0) {
                return [
                    'result'           => SanctionsScreening::RESULT_CLEAN,
                    'match_count'      => 0,
                    'matched_entities' => null,
                    'notes'            => null,
                ];
            }

            // Slim the matched entities so we don't bloat the audit table
            // with the full OpenSanctions payload (each row can be 100kb+).
            $entities = array_map(fn ($r) => [
                'id'       => $r['id'] ?? null,
                'caption'  => $r['caption'] ?? null,
                'schema'   => $r['schema'] ?? null,
                'datasets' => $r['datasets'] ?? [],
                'topics'   => $r['properties']['topics'] ?? [],
                'score'    => $r['score'] ?? null,
            ], array_slice($results, 0, 10));

            // High-confidence matches (score > 0.85) are auto-flagged as hit.
            // Lower-confidence ones drop to "review" so admins can manually
            // adjudicate before transactions are blocked.
            $topScore = collect($entities)->max('score') ?? 0;
            $verdict = $topScore >= 0.85
                ? SanctionsScreening::RESULT_HIT
                : SanctionsScreening::RESULT_REVIEW;

            return [
                'result'           => $verdict,
                'match_count'      => $count,
                'matched_entities' => $entities,
                'notes'            => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('OpenSanctions screen failed', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /** @return array{result:string, match_count:int, matched_entities:null, notes:string} */
    private function error(string $note): array
    {
        return [
            'result'           => SanctionsScreening::RESULT_ERROR,
            'match_count'      => 0,
            'matched_entities' => null,
            'notes'            => $note,
        ];
    }
}
