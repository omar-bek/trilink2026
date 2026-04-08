<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — shared Claude API client. Every AI feature in Phase 5 (OCR,
 * negotiation assistant, risk analysis, predictive analytics, copilot)
 * goes through this single class so we have one place to:
 *
 *   - swap models / adjust max_tokens
 *   - add retries, rate-limit handling, caching
 *   - log requests for cost/usage attribution
 *   - degrade gracefully when no API key is configured (mock mode)
 *
 * The whole client is built around the assumption that every AI feature
 * has a deterministic fallback — when there's no key, the caller gets a
 * structured `mock` response and the platform stays demo-able.
 */
class AnthropicClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model = 'claude-haiku-4-5-20251001',
        private readonly int $timeout = 30,
    ) {}

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    /**
     * Send a single-turn message to Claude. Returns either the parsed JSON
     * response (if `expectJson` is true) or the raw text. Returns null on
     * any error so callers can fall through to their own fallback logic.
     *
     * @param  string  $system  System prompt
     * @param  string|array  $user  User message — string for text, array for vision (image + text blocks)
     * @return array|string|null
     */
    public function send(string $system, string|array $user, bool $expectJson = false, int $maxTokens = 1024): array|string|null
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $userContent = is_string($user) ? $user : $user;

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => $maxTokens,
                    'system'     => $system,
                    'messages'   => [
                        ['role' => 'user', 'content' => $userContent],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Anthropic API HTTP error', [
                    'status' => $response->status(),
                    'body'   => substr((string) $response->body(), 0, 500),
                ]);
                return null;
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            if (!$expectJson) {
                return $text;
            }

            return $this->parseJson($text);
        } catch (\Throwable $e) {
            Log::warning('Anthropic API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Multi-turn chat for the procurement copilot. Each message is
     * `['role' => 'user'|'assistant', 'content' => '...']`. Returns the
     * assistant text reply or null on error.
     */
    public function chat(string $system, array $messages, int $maxTokens = 1024): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => $maxTokens,
                    'system'     => $system,
                    'messages'   => $messages,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->json();
            return (string) ($body['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('Anthropic chat exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build a content array for vision requests. Used by the OCR service
     * to ship a PDF / image alongside the prompt. Anthropic accepts
     * base64-encoded images directly inline.
     */
    public function visionContent(string $text, string $mimeType, string $base64Data): array
    {
        return [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64Data]],
            ['type' => 'text', 'text' => $text],
        ];
    }

    /**
     * Best-effort JSON extraction. Claude usually obeys the "respond with
     * JSON only" instruction but occasionally wraps it in prose. Strip
     * everything outside the first `{` and last `}` before decoding.
     */
    private function parseJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }
}
