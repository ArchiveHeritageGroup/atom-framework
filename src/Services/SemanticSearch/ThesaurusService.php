<?php

declare(strict_types=1);

namespace AtomFramework\Services\SemanticSearch;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Thesaurus Service - Core thesaurus management
 *
 * Manages thesaurus terms, synonyms, and query expansion for semantic search.
 * Supports importing from WordNet (via Datamuse), Wikidata, and local JSON files.
 *
 * @package AtomFramework\Services\SemanticSearch
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class ThesaurusService
{
    private $logger = null;
    private array $config;
    private array $settingsCache = [];

    // Relationship types
    public const REL_SYNONYM = 'synonym';
    public const REL_BROADER = 'broader';
    public const REL_NARROWER = 'narrower';
    public const REL_RELATED = 'related';
    public const REL_USE_FOR = 'use_for';

    // Sources
    public const SOURCE_WORDNET = 'wordnet';
    public const SOURCE_WIKIDATA = 'wikidata';
    public const SOURCE_LOCAL = 'local';

    // Domains
    public const DOMAIN_ARCHIVAL = 'archival';
    public const DOMAIN_LIBRARY = 'library';
    public const DOMAIN_MUSEUM = 'museum';
    public const DOMAIN_GENERAL = 'general';

    public function __construct(array $config = [])
    {
        $logDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';

        $frameworkRoot = class_exists('sfConfig')
            ? \sfConfig::get('sf_root_dir', '/usr/share/nginx/atom') . '/atom-framework'
            : '/usr/share/nginx/archive/atom-framework';

        $this->config = array_merge([
            'log_path' => $logDir . '/thesaurus.log',
            'synonyms_dir' => $frameworkRoot . '/data/synonyms',
            'es_synonyms_path' => '/etc/elasticsearch/synonyms/ahg_synonyms.txt',
            'default_language' => 'en',
            'expansion_limit' => 5,
            'min_weight' => 0.6,
        ], $config);

        // Try to load Monolog if available
        try {
            if (class_exists('Monolog\\Logger')) {
                $this->logger = new \Monolog\Logger('thesaurus');
                if (is_writable(dirname($this->config['log_path']))) {
                    $this->logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($this->config['log_path'], 30, \Monolog\Logger::INFO));
                }
            }
        } catch (\Throwable $e) {
            // Logger not available, continue without logging
            $this->logger = null;
        }

        $this->loadSettings();
    }

    /**
     * Log a message if logger is available
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level($message, $context);
        }
    }

    // ========================================================================
    // Settings Management
    // ========================================================================

    /**
     * Load settings from database
     */
    private function loadSettings(): void
    {
        try {
            $settings = DB::table('ahg_semantic_search_settings')->get();
            foreach ($settings as $setting) {
                $value = $setting->setting_value;
                switch ($setting->setting_type) {
                    case 'int':
                        $value = (int) $value;
                        break;
                    case 'bool':
                        $value = $value === '1' || $value === 'true';
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                $this->settingsCache[$setting->setting_key] = $value;
            }
        } catch (\Exception $e) {
            $this->log('warning', 'Failed to load settings', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get a setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settingsCache[$key] ?? $default;
    }

    /**
     * Update a setting
     */
    public function setSetting(string $key, $value): bool
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        $updated = DB::table('ahg_semantic_search_settings')
            ->where('setting_key', $key)
            ->update([
                'setting_value' => $stringValue,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($updated) {
            $this->settingsCache[$key] = $value;
        }

        return $updated > 0;
    }

    // ========================================================================
    // Term Management
    // ========================================================================

    /**
     * Add or update a term
     */
    public function addTerm(
        string $term,
        string $source,
        string $language = 'en',
        array $options = []
    ): ?int {
        $normalized = $this->normalizeTerm($term);

        $data = [
            'term' => $term,
            'normalized_term' => $normalized,
            'language' => $language,
            'source' => $source,
            'source_id' => $options['source_id'] ?? null,
            'definition' => $options['definition'] ?? null,
            'pos' => $options['pos'] ?? null,
            'domain' => $options['domain'] ?? self::DOMAIN_GENERAL,
            'is_preferred' => $options['is_preferred'] ?? false,
            'is_active' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Check if exists
        $existing = DB::table('ahg_thesaurus_term')
            ->where('normalized_term', $normalized)
            ->where('source', $source)
            ->where('language', $language)
            ->first();

        if ($existing) {
            DB::table('ahg_thesaurus_term')
                ->where('id', $existing->id)
                ->update($data);
            return (int) $existing->id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        return (int) DB::table('ahg_thesaurus_term')->insertGetId($data);
    }

    /**
     * Get term by ID
     */
    public function getTerm(int $id): ?object
    {
        return DB::table('ahg_thesaurus_term')
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find term by text
     */
    public function findTerm(string $term, ?string $source = null, string $language = 'en'): ?object
    {
        $normalized = $this->normalizeTerm($term);

        $query = DB::table('ahg_thesaurus_term')
            ->where('normalized_term', $normalized)
            ->where('language', $language)
            ->where('is_active', true);

        if ($source) {
            $query->where('source', $source);
        }

        return $query->first();
    }

    /**
     * Search terms
     */
    public function searchTerms(string $query, int $limit = 20): array
    {
        $normalized = $this->normalizeTerm($query);

        return DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->where(function ($q) use ($query, $normalized) {
                $q->where('term', 'LIKE', '%' . $query . '%')
                    ->orWhere('normalized_term', 'LIKE', '%' . $normalized . '%');
            })
            ->orderByRaw('CASE WHEN normalized_term = ? THEN 0 ELSE 1 END', [$normalized])
            ->orderBy('frequency', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Normalize a term for matching
     */
    public function normalizeTerm(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = preg_replace('/\s+/', ' ', $term);
        return $term;
    }

    // ========================================================================
    // Synonym Management
    // ========================================================================

    /**
     * Add a synonym relationship
     */
    public function addSynonym(
        int $termId,
        string $synonymText,
        string $source,
        string $relationshipType = self::REL_SYNONYM,
        float $weight = 1.0,
        ?int $synonymTermId = null
    ): ?int {
        $data = [
            'term_id' => $termId,
            'synonym_text' => $synonymText,
            'synonym_term_id' => $synonymTermId,
            'relationship_type' => $relationshipType,
            'weight' => $weight,
            'source' => $source,
            'is_bidirectional' => in_array($relationshipType, [self::REL_SYNONYM, self::REL_RELATED]),
            'is_active' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Check if exists
        $existing = DB::table('ahg_thesaurus_synonym')
            ->where('term_id', $termId)
            ->where('synonym_text', $synonymText)
            ->where('relationship_type', $relationshipType)
            ->first();

        if ($existing) {
            DB::table('ahg_thesaurus_synonym')
                ->where('id', $existing->id)
                ->update($data);
            return (int) $existing->id;
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        return (int) DB::table('ahg_thesaurus_synonym')->insertGetId($data);
    }

    /**
     * Get synonyms for a term
     */
    public function getSynonyms(
        int $termId,
        ?string $relationshipType = null,
        ?float $minWeight = null,
        int $limit = 10
    ): array {
        $minWeight = $minWeight ?? $this->config['min_weight'];

        $query = DB::table('ahg_thesaurus_synonym')
            ->where('term_id', $termId)
            ->where('is_active', true)
            ->where('weight', '>=', $minWeight);

        if ($relationshipType) {
            $query->where('relationship_type', $relationshipType);
        }

        return $query
            ->orderBy('weight', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get all synonyms for a term text (including reverse lookups)
     */
    public function getSynonymsForText(string $term, string $language = 'en'): array
    {
        $normalized = $this->normalizeTerm($term);
        $synonyms = [];

        // Find the term
        $termRecord = $this->findTerm($term, null, $language);

        if ($termRecord) {
            // Get direct synonyms
            $direct = $this->getSynonyms(
                (int) $termRecord->id,
                null,
                $this->config['min_weight'],
                $this->config['expansion_limit']
            );

            foreach ($direct as $syn) {
                $synonyms[$syn->synonym_text] = [
                    'text' => $syn->synonym_text,
                    'weight' => (float) $syn->weight,
                    'type' => $syn->relationship_type,
                    'source' => $syn->source,
                ];
            }
        }

        // Also find reverse synonyms (where this term is listed as a synonym of another)
        $reverse = DB::table('ahg_thesaurus_synonym as s')
            ->join('ahg_thesaurus_term as t', 's.term_id', '=', 't.id')
            ->where('s.synonym_text', $normalized)
            ->where('s.is_bidirectional', true)
            ->where('s.is_active', true)
            ->where('t.is_active', true)
            ->where('t.language', $language)
            ->where('s.weight', '>=', $this->config['min_weight'])
            ->select('t.term', 's.weight', 's.relationship_type', 's.source')
            ->limit($this->config['expansion_limit'])
            ->get();

        foreach ($reverse as $rev) {
            if (!isset($synonyms[$rev->term])) {
                $synonyms[$rev->term] = [
                    'text' => $rev->term,
                    'weight' => (float) $rev->weight,
                    'type' => $rev->relationship_type,
                    'source' => $rev->source,
                ];
            }
        }

        // Sort by weight
        uasort($synonyms, fn($a, $b) => $b['weight'] <=> $a['weight']);

        return array_slice($synonyms, 0, $this->config['expansion_limit']);
    }

    // ========================================================================
    // Query Expansion
    // ========================================================================

    /**
     * Expand a search query with synonyms
     */
    public function expandQuery(string $query, string $language = 'en'): array
    {
        $words = $this->tokenize($query);
        $expansions = [];
        $expandedTerms = [];

        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }

            $synonyms = $this->getSynonymsForText($word, $language);

            if (!empty($synonyms)) {
                $expandedTerms[$word] = array_column($synonyms, 'text');
                $expansions = array_merge($expansions, $synonyms);
            }
        }

        // Build expanded query
        $expandedQuery = $query;
        $synonymTexts = array_unique(array_column($expansions, 'text'));

        if (!empty($synonymTexts)) {
            $expandedQuery .= ' ' . implode(' ', $synonymTexts);
        }

        return [
            'original_query' => $query,
            'expanded_query' => $expandedQuery,
            'expanded_terms' => $expandedTerms,
            'expansions' => $expansions,
            'expansion_count' => count($synonymTexts),
        ];
    }

    /**
     * Tokenize a query into words
     */
    private function tokenize(string $query): array
    {
        // Remove punctuation and split (including hyphens, underscores)
        $query = preg_replace('/[^\w\s]/u', ' ', $query);
        $query = preg_replace('/[_-]+/', ' ', $query);
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        // Filter stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        return array_filter($words, fn($w) => !in_array(strtolower($w), $stopWords));
    }

    // ========================================================================
    // Elasticsearch Export
    // ========================================================================

    /**
     * Export synonyms to Elasticsearch format
     */
    public function exportToElasticsearch(?string $outputPath = null): string
    {
        $outputPath = $outputPath ?? $this->config['es_synonyms_path'];

        $lines = [
            '# AtoM Semantic Search Synonyms',
            '# Generated: ' . date('Y-m-d H:i:s'),
            '# Format: term => synonym1, synonym2, synonym3',
            '',
        ];

        // Get all active terms
        $terms = DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->orderBy('normalized_term')
            ->get();

        $exported = 0;

        foreach ($terms as $term) {
            $synonyms = $this->getSynonyms(
                (int) $term->id,
                self::REL_SYNONYM,
                0.5,
                10
            );

            if (!empty($synonyms)) {
                $synonymTexts = array_column($synonyms, 'synonym_text');
                $lines[] = $term->term . ' => ' . implode(', ', $synonymTexts);
                $exported++;
            }
        }

        $content = implode("\n", $lines);

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $content);

        $this->log('info', 'Elasticsearch synonyms exported', [
            'path' => $outputPath,
            'terms' => $exported,
        ]);

        return $outputPath;
    }

    /**
     * Generate Elasticsearch synonym analyzer config
     */
    public function getElasticsearchConfig(): array
    {
        return [
            'analysis' => [
                'filter' => [
                    'ahg_synonyms' => [
                        'type' => 'synonym',
                        'synonyms_path' => $this->config['es_synonyms_path'],
                        'updateable' => true,
                    ],
                ],
                'analyzer' => [
                    'ahg_semantic_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'ahg_synonyms',
                            'snowball',
                        ],
                    ],
                ],
            ],
        ];
    }

    // ========================================================================
    // Local JSON Import
    // ========================================================================

    /**
     * Import synonyms from local JSON files
     */
    public function importLocalSynonyms(?string $domain = null): array
    {
        $dir = $this->config['synonyms_dir'];
        $stats = [
            'files_processed' => 0,
            'terms_added' => 0,
            'synonyms_added' => 0,
            'errors' => [],
        ];

        if (!is_dir($dir)) {
            $stats['errors'][] = "Synonyms directory not found: {$dir}";
            return $stats;
        }

        $files = glob($dir . '/*.json');

        foreach ($files as $file) {
            $filename = basename($file, '.json');

            // Filter by domain if specified
            if ($domain && $filename !== $domain) {
                continue;
            }

            try {
                $content = file_get_contents($file);
                $data = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $stats['errors'][] = "Invalid JSON in {$filename}: " . json_last_error_msg();
                    continue;
                }

                $result = $this->importSynonymData($data, $filename);
                $stats['terms_added'] += $result['terms_added'];
                $stats['synonyms_added'] += $result['synonyms_added'];
                $stats['files_processed']++;

            } catch (\Exception $e) {
                $stats['errors'][] = "Error processing {$filename}: " . $e->getMessage();
            }
        }

        $this->log('info', 'Local synonyms imported', $stats);

        return $stats;
    }

    /**
     * Import synonym data array
     */
    private function importSynonymData(array $data, string $domain): array
    {
        $stats = ['terms_added' => 0, 'synonyms_added' => 0];

        $terms = $data['terms'] ?? $data;

        foreach ($terms as $termData) {
            if (is_string($termData)) {
                continue;
            }

            $term = $termData['term'] ?? null;
            if (!$term) {
                continue;
            }

            $termId = $this->addTerm(
                $term,
                self::SOURCE_LOCAL,
                $termData['language'] ?? 'en',
                [
                    'definition' => $termData['definition'] ?? null,
                    'domain' => $domain,
                    'is_preferred' => $termData['preferred'] ?? false,
                ]
            );

            if ($termId) {
                $stats['terms_added']++;

                $synonyms = $termData['synonyms'] ?? [];
                foreach ($synonyms as $syn) {
                    $synText = is_string($syn) ? $syn : ($syn['text'] ?? null);
                    $weight = is_array($syn) ? ($syn['weight'] ?? 1.0) : 1.0;

                    if ($synText) {
                        $this->addSynonym($termId, $synText, self::SOURCE_LOCAL, self::REL_SYNONYM, $weight);
                        $stats['synonyms_added']++;
                    }
                }

                // Handle broader/narrower terms
                foreach (['broader', 'narrower', 'related'] as $relType) {
                    $relatedTerms = $termData[$relType] ?? [];
                    foreach ($relatedTerms as $related) {
                        $relText = is_string($related) ? $related : ($related['text'] ?? null);
                        if ($relText) {
                            $this->addSynonym($termId, $relText, self::SOURCE_LOCAL, $relType, 0.8);
                            $stats['synonyms_added']++;
                        }
                    }
                }
            }
        }

        return $stats;
    }

    // ========================================================================
    // Statistics
    // ========================================================================

    /**
     * Get thesaurus statistics
     */
    public function getStats(): array
    {
        $termCount = DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->count();

        $synonymCount = DB::table('ahg_thesaurus_synonym')
            ->where('is_active', true)
            ->count();

        $bySource = DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        $byDomain = DB::table('ahg_thesaurus_term')
            ->where('is_active', true)
            ->selectRaw('domain, COUNT(*) as count')
            ->groupBy('domain')
            ->pluck('count', 'domain')
            ->toArray();

        $recentSyncs = DB::table('ahg_thesaurus_sync_log')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'total_terms' => $termCount,
            'total_synonyms' => $synonymCount,
            'by_source' => $bySource,
            'by_domain' => $byDomain,
            'recent_syncs' => $recentSyncs,
            'settings' => $this->settingsCache,
        ];
    }

    // ========================================================================
    // Sync Logging
    // ========================================================================

    /**
     * Create a sync log entry
     */
    public function createSyncLog(string $source, string $syncType): int
    {
        return (int) DB::table('ahg_thesaurus_sync_log')->insertGetId([
            'source' => $source,
            'sync_type' => $syncType,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update sync log status
     */
    public function updateSyncLog(int $logId, array $data): void
    {
        DB::table('ahg_thesaurus_sync_log')
            ->where('id', $logId)
            ->update($data);
    }
}
