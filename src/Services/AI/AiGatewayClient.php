<?php

declare(strict_types=1);

namespace AtomFramework\Services\AI;

use AtomFramework\Services\HttpClientService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * AI Gateway Client — canonical, fleet-compliant entry point for AI calls.
 *
 * Every application AI call (embeddings, chat/RAG generation, model probes)
 * MUST route through the AHG AI gateway at https://ai.theahg.co.za/ai/v1/...
 * — never directly to a GPU node's inference port. The gateway enforces the
 * API key + `gateway` scope + per-key quota, logs every call for metering, and
 * does DB-driven node selection / failover. See the host-wide gateway rule.
 *
 * This client deliberately rides the gateway's Ollama passthrough
 * (`/ai/v1/ollama/...`), which is the live, KM-proven surface:
 *   - embeddings : POST /ai/v1/ollama/api/embeddings   {"model","prompt"}
 *   - chat       : POST /ai/v1/ollama/api/chat          {"model","messages",...}
 *   - generate   : POST /ai/v1/ollama/api/generate      {"model","prompt",...}
 *   - health     : GET  /ai/v1/health
 *
 * It uses HttpClientService, whose SSRF guard blocks private IPs — so a
 * misconfiguration pointing back at a node IP (192.168.x) fails closed rather
 * than silently bypassing the gateway.
 *
 * Configuration (ahg_ai_settings, feature='gateway'), with safe fallbacks to
 * the existing feature='general' api_key so it works the moment a gateway key
 * is provisioned, without a schema change:
 *   base_url         default https://ai.theahg.co.za/ai/v1
 *   api_key          (falls back to feature='general' api_key)
 *   timeout          default 120 (seconds)
 *   embedding_model  default nomic-embed-text
 *   chat_model       default qwen3:14b
 *
 * @author The Archive and Heritage Group
 */
class AiGatewayClient
{
    public const DEFAULT_BASE_URL = 'https://ai.theahg.co.za/ai/v1';
    public const DEFAULT_EMBEDDING_MODEL = 'nomic-embed-text';
    public const DEFAULT_CHAT_MODEL = 'qwen3:14b';

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private string $embeddingModel;
    private string $chatModel;

    public function __construct(array $config = [])
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? self::DEFAULT_BASE_URL), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 120);
        $this->embeddingModel = (string) ($config['embedding_model'] ?? self::DEFAULT_EMBEDDING_MODEL);
        $this->chatModel = (string) ($config['chat_model'] ?? self::DEFAULT_CHAT_MODEL);
    }

    /**
     * Build a client from ahg_ai_settings. feature='gateway' is authoritative;
     * api_key falls back to feature='general' (the existing key location) so the
     * client is usable the moment any gateway-scoped key is configured.
     */
    public static function fromSettings(): self
    {
        $config = [];

        try {
            $rows = DB::table('ahg_ai_settings')
                ->where('feature', 'gateway')
                ->get();
            foreach ($rows as $row) {
                $config[$row->setting_key] = $row->setting_value;
            }
        } catch (\Throwable $e) {
            // Table/feature may not exist yet; defaults below still apply.
        }

        if (empty($config['api_key'])) {
            try {
                $general = DB::table('ahg_ai_settings')
                    ->where('feature', 'general')
                    ->where('setting_key', 'api_key')
                    ->value('setting_value');
                if (!empty($general)) {
                    $config['api_key'] = $general;
                }
            } catch (\Throwable $e) {
                // ignore — leaves api_key empty, isConfigured() will be false.
            }
        }

        return new self($config);
    }

    /**
     * True when an API key is present. Callers should check this and fall back
     * to their legacy path (or degrade gracefully) when the gateway key has not
     * been provisioned yet.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getEmbeddingModel(): string
    {
        return $this->embeddingModel;
    }

    public function getChatModel(): string
    {
        return $this->chatModel;
    }

    /**
     * Liveness probe. GET /ai/v1/health (no auth required upstream).
     */
    public function isAvailable(): bool
    {
        try {
            $res = HttpClientService::get(
                $this->baseUrl . '/health',
                $this->authHeaders(),
                ['timeout' => 5, 'connectTimeout' => 3]
            );

            return ($res['status'] ?? 0) === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generate an embedding vector for a single piece of text.
     *
     * @return float[]|null The embedding vector, or null on failure / no key.
     */
    public function embed(string $text, ?string $model = null): ?array
    {
        $text = trim($text);
        if ($text === '' || !$this->isConfigured()) {
            return null;
        }

        $payload = json_encode([
            'model' => $model ?: $this->embeddingModel,
            'prompt' => $text,
        ]);

        $data = $this->postJson('/ollama/api/embeddings', $payload, $this->timeout);
        if ($data === null) {
            return null;
        }

        // Ollama /api/embeddings → {"embedding":[...]}.
        $vec = $data['embedding'] ?? null;
        if (is_array($vec) && $vec !== []) {
            return array_map('floatval', $vec);
        }

        // Newer /api/embed shape → {"embeddings":[[...]]}; tolerate it.
        if (isset($data['embeddings'][0]) && is_array($data['embeddings'][0])) {
            return array_map('floatval', $data['embeddings'][0]);
        }

        return null;
    }

    /**
     * Embed several texts. Ollama's embeddings endpoint is single-input, so
     * this loops; a null entry marks a failed item (callers can filter).
     *
     * @param string[] $texts
     * @return array<int, float[]|null>
     */
    public function embedBatch(array $texts, ?string $model = null): array
    {
        $out = [];
        foreach ($texts as $i => $text) {
            $out[$i] = $this->embed((string) $text, $model);
        }

        return $out;
    }

    /**
     * Chat / RAG completion via the gateway's Ollama passthrough.
     *
     * @param array $messages [['role'=>'system','content'=>'...'], ['role'=>'user','content'=>'...']]
     * @param array $options   model, temperature, max_tokens (num_predict), num_ctx, timeout
     * @return array{success:bool, text:string, model:string, error:?string, raw:?array}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->chatError('AI gateway API key not configured');
        }

        $ollamaOptions = [];
        if (isset($options['temperature'])) {
            $ollamaOptions['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $ollamaOptions['num_predict'] = (int) $options['max_tokens'];
        }
        if (isset($options['num_ctx'])) {
            $ollamaOptions['num_ctx'] = (int) $options['num_ctx'];
        }

        $body = [
            'model' => $options['model'] ?? $this->chatModel,
            'messages' => array_values($messages),
            'stream' => false,
        ];
        if ($ollamaOptions !== []) {
            $body['options'] = $ollamaOptions;
        }

        $timeout = (int) ($options['timeout'] ?? $this->timeout);
        $data = $this->postJson('/ollama/api/chat', json_encode($body), $timeout);
        if ($data === null) {
            return $this->chatError('AI gateway request failed or returned invalid JSON');
        }

        // Ollama /api/chat → {"message":{"role":"assistant","content":"..."},...}
        $text = $data['message']['content'] ?? '';

        return [
            'success' => $text !== '',
            'text' => (string) $text,
            'model' => (string) ($data['model'] ?? ($body['model'])),
            'error' => $text === '' ? 'Empty response from model' : null,
            'raw' => $data,
        ];
    }

    /**
     * Single-prompt generation convenience over chat().
     */
    public function generate(string $prompt, ?string $system = null, array $options = []): array
    {
        $messages = [];
        if ($system !== null && $system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $this->chat($messages, $options);
    }

    /**
     * Vision generation via the gateway's Ollama passthrough — POST
     * /ai/v1/ollama/api/generate with base64 image(s). Native Ollama "generate"
     * shape; returns the same contract as chat().
     *
     * @param string[] $base64Images raw base64 (no "data:" prefix)
     * @param array     $options       temperature, seed, num_predict (→ max_tokens), timeout
     * @return array{success:bool, text:string, model:string, error:?string, raw:?array}
     */
    public function visionGenerate(string $prompt, array $base64Images, ?string $model = null, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->chatError('AI gateway API key not configured');
        }

        $ollamaOptions = [];
        if (isset($options['temperature'])) {
            $ollamaOptions['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['seed'])) {
            $ollamaOptions['seed'] = (int) $options['seed'];
        }
        if (isset($options['num_predict'])) {
            $ollamaOptions['num_predict'] = (int) $options['num_predict'];
        }

        $body = [
            'model' => $model ?: $this->chatModel,
            'prompt' => $prompt,
            'images' => array_values($base64Images),
            'stream' => false,
        ];
        if ($ollamaOptions !== []) {
            $body['options'] = $ollamaOptions;
        }

        $timeout = (int) ($options['timeout'] ?? $this->timeout);
        $data = $this->postJson('/ollama/api/generate', json_encode($body), $timeout);
        if ($data === null) {
            return $this->chatError('AI gateway request failed or returned invalid JSON');
        }

        // Ollama /api/generate → {"response":"...","model":"..."}.
        $text = $data['response'] ?? '';

        return [
            'success' => $text !== '',
            'text' => (string) $text,
            'model' => (string) ($data['model'] ?? $body['model']),
            'error' => $text === '' ? 'Empty response from model' : null,
            'raw' => $data,
        ];
    }

    // =========================================================================
    // Worker routes (translation) — gateway /ai/v1/* with the same X-API-Key
    // =========================================================================

    /**
     * Translate text via the gateway's worker route (POST /ai/v1/translate).
     *
     * @return array<string,mixed>|null Decoded response (worker shape:
     *   {success, translated, error}), or null on failure / no key.
     */
    public function translate(string $text, string $sourceLang, string $targetLang, ?int $maxLength = null): ?array
    {
        if (trim($text) === '' || !$this->isConfigured()) {
            return null;
        }

        $payload = ['text' => $text, 'source' => $sourceLang, 'target' => $targetLang];
        if ($maxLength !== null) {
            $payload['max_length'] = $maxLength;
        }

        return $this->postJson('/translate', json_encode($payload), $this->timeout);
    }

    /**
     * List supported translation languages (GET /ai/v1/translate/languages).
     *
     * @return array<string,mixed>|null Decoded response, or null on failure / no key.
     */
    public function translateLanguages(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $res = HttpClientService::get(
                $this->baseUrl . '/translate/languages',
                $this->authHeaders(),
                ['timeout' => 10, 'connectTimeout' => 5]
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (($res['status'] ?? 0) !== 200 || empty($res['body'])) {
            return null;
        }

        $data = json_decode($res['body'], true);

        return is_array($data) ? $data : null;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * @return array<string,mixed>|null Decoded JSON object, or null on any failure.
     */
    private function postJson(string $path, string $body, int $timeout): ?array
    {
        try {
            $res = HttpClientService::post(
                $this->baseUrl . $path,
                $body,
                $this->authHeaders(['Content-Type' => 'application/json']),
                ['timeout' => $timeout]
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (($res['status'] ?? 0) !== 200 || empty($res['body'])) {
            return null;
        }

        $data = json_decode($res['body'], true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string,string> $extra
     * @return array<string,string>
     */
    private function authHeaders(array $extra = []): array
    {
        $headers = ['X-API-Key' => $this->apiKey];

        return array_merge($headers, $extra);
    }

    private function chatError(string $message): array
    {
        return [
            'success' => false,
            'text' => '',
            'model' => $this->chatModel,
            'error' => $message,
            'raw' => null,
        ];
    }
}
