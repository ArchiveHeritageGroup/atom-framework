<?php

declare(strict_types=1);

namespace AtomFramework\Services\SemanticSearch;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Embedding Service - Vector Embeddings for Semantic Search
 *
 * Generates and manages vector embeddings using Ollama for semantic similarity.
 * Supports finding similar terms, clustering, and semantic search enhancement.
 *
 * Requires Ollama running locally with an embedding model like nomic-embed-text.
 *
 * @package AtomFramework\Services\SemanticSearch
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class EmbeddingService
{
    private Logger $logger;
    private ThesaurusService $thesaurus;
    private array $config;
    private ?string $ollamaEndpoint = null;

    // Default embedding models
    public const MODEL_NOMIC = 'nomic-embed-text';
    public const MODEL_MXBAI = 'mxbai-embed-large';
    public const MODEL_ALL_MINILM = 'all-minilm';

    public function __construct(?ThesaurusService $thesaurus = null, array $config = [])
    {
        $logDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';

        $this->config = array_merge([
            'log_path' => $logDir . '/embeddings.log',
            'ollama_endpoint' => 'http://localhost:11434',
            'model' => self::MODEL_NOMIC,
            'timeout' => 30,
            'batch_size' => 10,
        ], $config);

        $this->logger = new Logger('embeddings');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }

        $this->thesaurus = $thesaurus ?? new ThesaurusService();
        $this->ollamaEndpoint = $this->thesaurus->getSetting('ollama_endpoint', $this->config['ollama_endpoint']);
    }

    // ========================================================================
    // Availability Check
    // ========================================================================

    /**
     * Check if Ollama is available
     */
    public function isAvailable(): bool
    {
        try {
            $url = rtrim($this->ollamaEndpoint, '/') . '/api/tags';

            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET',
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of available models
     */
    public function getAvailableModels(): array
    {
        try {
            $url = rtrim($this->ollamaEndpoint, '/') . '/api/tags';

            $response = @file_get_contents($url);

            if ($response === false) {
                return [];
            }

            $data = json_decode($response, true);

            return array_column($data['models'] ?? [], 'name');
        } catch (\Exception $e) {
            return [];
        }
    }

    // ========================================================================
    // Embedding Generation
    // ========================================================================

    /**
     * Generate embedding for a single text
     */
    public function getEmbedding(string $text, ?string $model = null): ?array
    {
        $model = $model ?? $this->config['model'];

        try {
            $url = rtrim($this->ollamaEndpoint, '/') . '/api/embeddings';

            $payload = json_encode([
                'model' => $model,
                'prompt' => $text,
            ]);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'timeout' => $this->config['timeout'],
                    'header' => [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($payload),
                    ],
                    'content' => $payload,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $error = error_get_last();
                $this->logger->error('Ollama request failed', ['error' => $error['message'] ?? 'Unknown']);
                return null;
            }

            $data = json_decode($response, true);

            if (isset($data['embedding'])) {
                return $data['embedding'];
            }

            $this->logger->error('Invalid Ollama response', ['response' => $data]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Embedding generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts
     */
    public function getEmbeddings(array $texts, ?string $model = null): array
    {
        $embeddings = [];

        foreach ($texts as $key => $text) {
            $embedding = $this->getEmbedding($text, $model);

            if ($embedding) {
                $embeddings[$key] = $embedding;
            }
        }

        return $embeddings;
    }

    // ========================================================================
    // Term Embedding Management
    // ========================================================================

    /**
     * Generate and store embedding for a thesaurus term
     */
    public function generateTermEmbedding(int $termId, ?string $model = null): bool
    {
        $model = $model ?? $this->config['model'];

        $term = $this->thesaurus->getTerm($termId);

        if (!$term) {
            return false;
        }

        // Generate embedding for term + definition
        $text = $term->term;
        if (!empty($term->definition)) {
            $text .= ': ' . $term->definition;
        }

        $embedding = $this->getEmbedding($text, $model);

        if (!$embedding) {
            return false;
        }

        // Store in database
        return $this->storeEmbedding($termId, $model, $embedding);
    }

    /**
     * Store an embedding in the database
     */
    private function storeEmbedding(int $termId, string $model, array $embedding): bool
    {
        // Serialize the embedding
        $serialized = serialize($embedding);

        // Delete existing
        DB::table('ahg_thesaurus_embedding')
            ->where('term_id', $termId)
            ->where('model', $model)
            ->delete();

        // Insert new
        DB::table('ahg_thesaurus_embedding')->insert([
            'term_id' => $termId,
            'model' => $model,
            'embedding' => $serialized,
            'embedding_dimension' => count($embedding),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Get stored embedding for a term
     */
    public function getTermEmbedding(int $termId, ?string $model = null): ?array
    {
        $model = $model ?? $this->config['model'];

        $record = DB::table('ahg_thesaurus_embedding')
            ->where('term_id', $termId)
            ->where('model', $model)
            ->first();

        if (!$record) {
            return null;
        }

        return unserialize($record->embedding);
    }

    /**
     * Generate embeddings for all terms that don't have one
     */
    public function generateAllEmbeddings(?string $model = null): array
    {
        $model = $model ?? $this->config['model'];

        $stats = [
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Get terms without embeddings for this model
        $terms = DB::table('ahg_thesaurus_term as t')
            ->leftJoin('ahg_thesaurus_embedding as e', function ($join) use ($model) {
                $join->on('t.id', '=', 'e.term_id')
                    ->where('e.model', '=', $model);
            })
            ->whereNull('e.id')
            ->where('t.is_active', true)
            ->select('t.id')
            ->get();

        foreach ($terms as $term) {
            $success = $this->generateTermEmbedding((int) $term->id, $model);

            if ($success) {
                $stats['generated']++;
            } else {
                $stats['errors']++;
            }

            // Rate limiting
            usleep(100000); // 100ms
        }

        $this->logger->info('Embedding generation complete', $stats);

        return $stats;
    }

    // ========================================================================
    // Similarity Search
    // ========================================================================

    /**
     * Calculate cosine similarity between two embeddings
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException('Embeddings must have same dimension');
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Find similar terms using embedding similarity
     */
    public function findSimilarTerms(string $query, int $limit = 10, float $minSimilarity = 0.7): array
    {
        $model = $this->config['model'];

        // Generate embedding for query
        $queryEmbedding = $this->getEmbedding($query, $model);

        if (!$queryEmbedding) {
            return [];
        }

        // Get all embeddings
        $embeddings = DB::table('ahg_thesaurus_embedding as e')
            ->join('ahg_thesaurus_term as t', 'e.term_id', '=', 't.id')
            ->where('e.model', $model)
            ->where('t.is_active', true)
            ->select('t.id', 't.term', 't.definition', 'e.embedding')
            ->get();

        $similarities = [];

        foreach ($embeddings as $record) {
            $termEmbedding = unserialize($record->embedding);
            $similarity = $this->cosineSimilarity($queryEmbedding, $termEmbedding);

            if ($similarity >= $minSimilarity) {
                $similarities[] = [
                    'term_id' => $record->id,
                    'term' => $record->term,
                    'definition' => $record->definition,
                    'similarity' => round($similarity, 4),
                ];
            }
        }

        // Sort by similarity descending
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }

    /**
     * Find related terms using semantic similarity
     */
    public function findRelatedTerms(int $termId, int $limit = 10): array
    {
        $model = $this->config['model'];

        // Get the term embedding
        $termEmbedding = $this->getTermEmbedding($termId, $model);

        if (!$termEmbedding) {
            // Generate it
            $this->generateTermEmbedding($termId, $model);
            $termEmbedding = $this->getTermEmbedding($termId, $model);

            if (!$termEmbedding) {
                return [];
            }
        }

        // Get all other embeddings
        $embeddings = DB::table('ahg_thesaurus_embedding as e')
            ->join('ahg_thesaurus_term as t', 'e.term_id', '=', 't.id')
            ->where('e.model', $model)
            ->where('e.term_id', '!=', $termId)
            ->where('t.is_active', true)
            ->select('t.id', 't.term', 't.definition', 'e.embedding')
            ->get();

        $similarities = [];

        foreach ($embeddings as $record) {
            $otherEmbedding = unserialize($record->embedding);
            $similarity = $this->cosineSimilarity($termEmbedding, $otherEmbedding);

            $similarities[] = [
                'term_id' => $record->id,
                'term' => $record->term,
                'definition' => $record->definition,
                'similarity' => round($similarity, 4),
            ];
        }

        // Sort by similarity descending
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }

    // ========================================================================
    // Statistics
    // ========================================================================

    /**
     * Get embedding statistics
     */
    public function getStats(): array
    {
        $totalTerms = DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->count();

        $byModel = DB::table('ahg_thesaurus_embedding')
            ->selectRaw('model, COUNT(*) as count, AVG(embedding_dimension) as avg_dimension')
            ->groupBy('model')
            ->get()
            ->toArray();

        return [
            'total_terms' => $totalTerms,
            'embeddings_by_model' => $byModel,
            'ollama_available' => $this->isAvailable(),
            'current_model' => $this->config['model'],
        ];
    }
}
