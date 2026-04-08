<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Suggests HS (Harmonized System) codes for product/line-item descriptions.
 *
 * Two modes, picked at runtime:
 *
 *   1. Live: when ANTHROPIC_API_KEY is configured, calls Claude with a
 *      system prompt that constrains it to return up to 3 HS code candidates
 *      with a confidence score and a one-line reason. Claude's training data
 *      covers the WCO HS nomenclature so its suggestions are usually within
 *      the right chapter even for niche items.
 *
 *   2. Fallback: a tiny keyword-to-chapter rule table. Doesn't cover every
 *      product but keeps the feature usable when there's no API key
 *      configured (early demos, CI runs, offline dev).
 *
 * Results are cached for 24 hours per (description, country) pair so the
 * same item description doesn't burn API tokens repeatedly.
 */
class HsCodeClassificationService
{
    /**
     * @param  string  $description  Free-form item/product description
     * @param  string|null  $country  Optional importing country (for context, not always used)
     * @return array{success:bool, source:string, suggestions:array<int,array{code:string,description:string,confidence:float,reason:?string}>}
     */
    public function suggest(string $description, ?string $country = null): array
    {
        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'source' => 'none', 'suggestions' => []];
        }

        $cacheKey = 'hs:' . md5($description . '|' . ($country ?? ''));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($description, $country) {
            $apiKey = config('services.anthropic.api_key');
            if ($apiKey) {
                $live = $this->callClaude($description, $country, $apiKey);
                if ($live['success']) {
                    return $live;
                }
                // Fall through to rule-based fallback if Claude failed.
            }
            return $this->fallbackRules($description);
        });
    }

    /**
     * Call Claude with a tightly-scoped prompt and parse the JSON response.
     */
    private function callClaude(string $description, ?string $country, string $apiKey): array
    {
        $model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');

        $systemPrompt = <<<TXT
You are a customs classification expert specialising in the WCO Harmonized System (HS) nomenclature.
Given a product description, return up to 3 most likely 6-digit HS codes ranked by confidence.

Respond with ONLY a JSON object in this exact shape, no prose, no markdown:
{
  "suggestions": [
    {"code": "841459", "description": "Fans, electric, with a self-contained motor not exceeding 125 W", "confidence": 0.85, "reason": "Brief one-line reason"}
  ]
}

Codes must be 6 digits. Confidence is 0.0-1.0. Empty suggestions array if you genuinely cannot classify.
TXT;

        $userPrompt = "Product description: {$description}";
        if ($country) {
            $userPrompt .= "\nImporting country: {$country}";
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $model,
                    'max_tokens' => 600,
                    'system'     => $systemPrompt,
                    'messages'   => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Claude HS classification HTTP error', ['status' => $response->status()]);
                return ['success' => false, 'source' => 'claude_error', 'suggestions' => []];
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            // Extract JSON even if Claude wrapped it in stray whitespace.
            $jsonStart = strpos($text, '{');
            $jsonEnd   = strrpos($text, '}');
            if ($jsonStart === false || $jsonEnd === false) {
                return ['success' => false, 'source' => 'claude_parse_error', 'suggestions' => []];
            }

            $parsed = json_decode(substr($text, $jsonStart, $jsonEnd - $jsonStart + 1), true);
            $suggestions = $parsed['suggestions'] ?? [];

            return [
                'success'     => true,
                'source'      => 'claude',
                'suggestions' => array_map(fn ($s) => [
                    'code'        => (string) ($s['code'] ?? ''),
                    'description' => (string) ($s['description'] ?? ''),
                    'confidence'  => (float) ($s['confidence'] ?? 0),
                    'reason'      => $s['reason'] ?? null,
                ], $suggestions),
            ];
        } catch (\Throwable $e) {
            Log::warning('Claude HS classification exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'source' => 'claude_exception', 'suggestions' => []];
        }
    }

    /**
     * Keyword-based fallback. Maps coarse English terms to representative
     * HS chapter codes. Intentionally tiny — the goal is to keep the
     * feature usable end-to-end without an API key, not to compete with
     * Claude.
     */
    private function fallbackRules(string $description): array
    {
        $haystack = strtolower($description);

        $rules = [
            ['keywords' => ['steel', 'iron bar', 'rebar'],         'code' => '721410', 'desc' => 'Bars and rods of iron or non-alloy steel'],
            ['keywords' => ['cement', 'portland'],                  'code' => '252321', 'desc' => 'Portland cement, white'],
            ['keywords' => ['cable', 'electric wire'],              'code' => '854442', 'desc' => 'Electric conductors with connectors, ≤1000V'],
            ['keywords' => ['pipe', 'tube', 'stainless'],           'code' => '730630', 'desc' => 'Tubes, pipes and hollow profiles, of iron or steel'],
            ['keywords' => ['paint', 'coating'],                    'code' => '320990', 'desc' => 'Other paints and varnishes based on synthetic polymers'],
            ['keywords' => ['t-shirt', 'cotton', 'apparel'],        'code' => '610910', 'desc' => 'T-shirts of cotton, knitted or crocheted'],
            ['keywords' => ['laptop', 'notebook computer'],         'code' => '847130', 'desc' => 'Portable automatic data processing machines'],
            ['keywords' => ['mobile phone', 'smartphone'],          'code' => '851712', 'desc' => 'Telephones for cellular networks'],
            ['keywords' => ['rice'],                                'code' => '100630', 'desc' => 'Semi-milled or wholly milled rice'],
            ['keywords' => ['olive oil'],                           'code' => '150910', 'desc' => 'Virgin olive oil'],
            ['keywords' => ['plastic bag', 'polyethylene'],         'code' => '392321', 'desc' => 'Sacks and bags of polymers of ethylene'],
            ['keywords' => ['solar panel', 'photovoltaic'],         'code' => '854142', 'desc' => 'Photovoltaic cells assembled in modules'],
            ['keywords' => ['pump', 'centrifugal'],                 'code' => '841370', 'desc' => 'Centrifugal pumps for liquids'],
            ['keywords' => ['valve'],                               'code' => '848180', 'desc' => 'Other taps, cocks, valves and similar appliances'],
            ['keywords' => ['generator', 'diesel'],                 'code' => '850239', 'desc' => 'Generating sets with compression-ignition engines'],
        ];

        $matches = [];
        foreach ($rules as $rule) {
            foreach ($rule['keywords'] as $kw) {
                if (str_contains($haystack, $kw)) {
                    $matches[] = [
                        'code'        => $rule['code'],
                        'description' => $rule['desc'],
                        'confidence'  => 0.55,
                        'reason'      => "Matched keyword: {$kw}",
                    ];
                    break;
                }
            }
        }

        return [
            'success'     => true,
            'source'      => 'rules',
            'suggestions' => array_slice($matches, 0, 3),
        ];
    }
}
