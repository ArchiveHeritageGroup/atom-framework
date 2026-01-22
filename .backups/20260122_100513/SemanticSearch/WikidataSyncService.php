<?php

declare(strict_types=1);

namespace AtomFramework\Services\SemanticSearch;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Wikidata Sync Service
 *
 * Synchronizes thesaurus data from Wikidata via SPARQL queries.
 * Fetches heritage, archival, and museum-related terminology with multilingual labels.
 *
 * SPARQL Endpoint: https://query.wikidata.org/sparql
 *
 * @package AtomFramework\Services\SemanticSearch
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class WikidataSyncService
{
    private Logger $logger;
    private ThesaurusService $thesaurus;
    private array $config;

    private const SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';
    private const DEFAULT_RATE_LIMIT_MS = 500;

    // Wikidata property IDs
    private const PROP_SUBCLASS_OF = 'P279';
    private const PROP_INSTANCE_OF = 'P31';
    private const PROP_ALIAS = 'skos:altLabel';

    // Key Wikidata items for heritage domain
    private const HERITAGE_CLASSES = [
        'Q210272' => 'cultural heritage',
        'Q2668072' => 'archive',
        'Q7075' => 'library',
        'Q33506' => 'museum',
        'Q11032' => 'newspaper',
        'Q7725634' => 'literary work',
        'Q4502142' => 'visual artwork',
        'Q35127' => 'website',
        'Q234460' => 'historical document',
        'Q131569' => 'treaty',
        'Q46721' => 'photograph',
        'Q860861' => 'sculpture',
        'Q4989906' => 'monument',
        'Q811979' => 'architectural structure',
        'Q12131' => 'written work',
    ];

    // South African specific items
    private const SA_HERITAGE_ITEMS = [
        'Q258' => 'South Africa',
        'Q1001079' => 'apartheid',
        'Q193760' => 'African National Congress',
        'Q215518' => 'Truth and Reconciliation Commission',
    ];

    public function __construct(?ThesaurusService $thesaurus = null, array $config = [])
    {
        $logDir = class_exists('sfConfig')
            ? \sfConfig::get('sf_log_dir', '/var/log/atom')
            : '/var/log/atom';

        $this->config = array_merge([
            'log_path' => $logDir . '/wikidata_sync.log',
            'rate_limit_ms' => self::DEFAULT_RATE_LIMIT_MS,
            'timeout' => 30,
            'languages' => ['en', 'af', 'zu', 'xh'],  // English, Afrikaans, Zulu, Xhosa
            'max_results' => 100,
        ], $config);

        $this->logger = new Logger('wikidata_sync');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }

        $this->thesaurus = $thesaurus ?? new ThesaurusService();
    }

    // ========================================================================
    // Sync Operations
    // ========================================================================

    /**
     * Sync heritage-related terms from Wikidata
     */
    public function syncHeritageTerms(): array
    {
        $logId = $this->thesaurus->createSyncLog(ThesaurusService::SOURCE_WIKIDATA, 'heritage');

        $this->thesaurus->updateSyncLog($logId, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $stats = [
            'terms_processed' => 0,
            'terms_added' => 0,
            'synonyms_added' => 0,
            'errors' => [],
        ];

        foreach (self::HERITAGE_CLASSES as $qid => $label) {
            try {
                $result = $this->syncClassAndSubclasses($qid, ThesaurusService::DOMAIN_ARCHIVAL);

                $stats['terms_processed'] += $result['terms_processed'];
                $stats['terms_added'] += $result['terms_added'];
                $stats['synonyms_added'] += $result['synonyms_added'];

                // Rate limiting
                usleep($this->config['rate_limit_ms'] * 1000);

            } catch (\Exception $e) {
                $stats['errors'][] = "{$qid} ({$label}): " . $e->getMessage();
                $this->logger->error("Failed to sync Wikidata class: {$qid}", ['error' => $e->getMessage()]);
            }
        }

        $this->thesaurus->updateSyncLog($logId, [
            'status' => empty($stats['errors']) ? 'completed' : 'completed_with_errors',
            'completed_at' => date('Y-m-d H:i:s'),
            'terms_processed' => $stats['terms_processed'],
            'terms_added' => $stats['terms_added'],
            'synonyms_added' => $stats['synonyms_added'],
            'errors' => !empty($stats['errors']) ? json_encode($stats['errors']) : null,
        ]);

        $this->logger->info('Wikidata heritage sync completed', $stats);

        return $stats;
    }

    /**
     * Sync a Wikidata class and its subclasses
     */
    public function syncClassAndSubclasses(string $qid, string $domain): array
    {
        $stats = [
            'terms_processed' => 0,
            'terms_added' => 0,
            'synonyms_added' => 0,
        ];

        // Fetch the main class
        $mainItem = $this->fetchItem($qid);

        if ($mainItem) {
            $result = $this->importWikidataItem($mainItem, $domain);
            $stats['terms_processed']++;
            if ($result['term_id']) {
                $stats['terms_added']++;
                $stats['synonyms_added'] += $result['synonyms_added'];
            }
        }

        // Fetch subclasses
        $subclasses = $this->fetchSubclasses($qid);

        foreach ($subclasses as $subclass) {
            $result = $this->importWikidataItem($subclass, $domain);
            $stats['terms_processed']++;

            if ($result['term_id']) {
                $stats['terms_added']++;
                $stats['synonyms_added'] += $result['synonyms_added'];
            }

            // Rate limiting within batch
            if ($stats['terms_processed'] % 10 === 0) {
                usleep($this->config['rate_limit_ms'] * 1000);
            }
        }

        return $stats;
    }

    /**
     * Import a Wikidata item into thesaurus
     */
    private function importWikidataItem(array $item, string $domain): array
    {
        $result = [
            'term_id' => null,
            'synonyms_added' => 0,
        ];

        $label = $item['label'] ?? null;
        if (!$label) {
            return $result;
        }

        // Add the main term
        $termId = $this->thesaurus->addTerm(
            $label,
            ThesaurusService::SOURCE_WIKIDATA,
            $item['language'] ?? 'en',
            [
                'source_id' => $item['qid'] ?? null,
                'definition' => $item['description'] ?? null,
                'domain' => $domain,
            ]
        );

        $result['term_id'] = $termId;

        if (!$termId) {
            return $result;
        }

        // Add aliases as synonyms
        $aliases = $item['aliases'] ?? [];
        foreach ($aliases as $alias) {
            $this->thesaurus->addSynonym(
                $termId,
                $alias,
                ThesaurusService::SOURCE_WIKIDATA,
                ThesaurusService::REL_SYNONYM,
                0.9
            );
            $result['synonyms_added']++;
        }

        // Add translations as synonyms (lower weight)
        $translations = $item['translations'] ?? [];
        foreach ($translations as $lang => $translation) {
            if ($lang !== ($item['language'] ?? 'en')) {
                $this->thesaurus->addSynonym(
                    $termId,
                    $translation,
                    ThesaurusService::SOURCE_WIKIDATA,
                    ThesaurusService::REL_RELATED,
                    0.7
                );
                $result['synonyms_added']++;
            }
        }

        return $result;
    }

    // ========================================================================
    // SPARQL Queries
    // ========================================================================

    /**
     * Fetch a single Wikidata item with labels and aliases
     */
    public function fetchItem(string $qid): ?array
    {
        $languages = implode(',', $this->config['languages']);

        $query = <<<SPARQL
SELECT ?item ?itemLabel ?itemDescription ?itemAltLabel WHERE {
  BIND(wd:{$qid} AS ?item)
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "{$languages},en".
  }
}
SPARQL;

        $results = $this->executeSparql($query);

        if (empty($results)) {
            return null;
        }

        $row = $results[0];

        return [
            'qid' => $qid,
            'label' => $row['itemLabel']['value'] ?? null,
            'description' => $row['itemDescription']['value'] ?? null,
            'aliases' => $this->parseAliases($row['itemAltLabel']['value'] ?? ''),
            'language' => 'en',
        ];
    }

    /**
     * Fetch subclasses of a Wikidata class
     */
    public function fetchSubclasses(string $parentQid, int $limit = null): array
    {
        $limit = $limit ?? $this->config['max_results'];
        $languages = implode(',', $this->config['languages']);

        $query = <<<SPARQL
SELECT DISTINCT ?item ?itemLabel ?itemDescription ?itemAltLabel WHERE {
  ?item wdt:P279* wd:{$parentQid} .
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "{$languages},en".
  }
}
LIMIT {$limit}
SPARQL;

        $results = $this->executeSparql($query);
        $items = [];

        foreach ($results as $row) {
            $qid = $this->extractQid($row['item']['value'] ?? '');

            if ($qid) {
                $items[] = [
                    'qid' => $qid,
                    'label' => $row['itemLabel']['value'] ?? null,
                    'description' => $row['itemDescription']['value'] ?? null,
                    'aliases' => $this->parseAliases($row['itemAltLabel']['value'] ?? ''),
                    'language' => 'en',
                ];
            }
        }

        return $items;
    }

    /**
     * Fetch archive-related items
     */
    public function fetchArchiveTerms(): array
    {
        $query = <<<SPARQL
SELECT DISTINCT ?item ?itemLabel ?itemDescription ?itemAltLabel WHERE {
  {
    ?item wdt:P31/wdt:P279* wd:Q2668072 .  # instance of archive
  } UNION {
    ?item wdt:P279* wd:Q2668072 .  # subclass of archive
  } UNION {
    ?item wdt:P31 wd:Q5633421 .  # scholarly journal
  } UNION {
    ?item wdt:P31 wd:Q87167 .  # manuscript
  }
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,af".
  }
}
LIMIT 200
SPARQL;

        return $this->executeSparqlAndParse($query);
    }

    /**
     * Fetch South African heritage items
     */
    public function fetchSouthAfricanHeritage(): array
    {
        $query = <<<SPARQL
SELECT DISTINCT ?item ?itemLabel ?itemDescription ?itemAltLabel WHERE {
  {
    ?item wdt:P17 wd:Q258 .  # country: South Africa
    ?item wdt:P31/wdt:P279* wd:Q210272 .  # instance of cultural heritage
  } UNION {
    ?item wdt:P31 wd:Q570116 .  # tourist attraction in South Africa
    ?item wdt:P17 wd:Q258 .
  } UNION {
    ?item wdt:P31 wd:Q839954 .  # archaeological site
    ?item wdt:P17 wd:Q258 .
  }
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,af,zu,xh".
  }
}
LIMIT 100
SPARQL;

        return $this->executeSparqlAndParse($query);
    }

    /**
     * Execute SPARQL query and return raw results
     */
    private function executeSparql(string $query): array
    {
        $url = self::SPARQL_ENDPOINT . '?' . http_build_query([
            'query' => $query,
            'format' => 'json',
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->config['timeout'],
                'header' => [
                    'User-Agent: AtoM-Framework/1.0 (https://theahg.co.za; johan@theahg.co.za)',
                    'Accept: application/sparql-results+json',
                ],
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            $this->logger->error('SPARQL request failed', ['error' => $error['message'] ?? 'Unknown']);
            return [];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid SPARQL JSON response');
            return [];
        }

        return $data['results']['bindings'] ?? [];
    }

    /**
     * Execute SPARQL and parse into items
     */
    private function executeSparqlAndParse(string $query): array
    {
        $results = $this->executeSparql($query);
        $items = [];

        foreach ($results as $row) {
            $qid = $this->extractQid($row['item']['value'] ?? '');

            if ($qid) {
                $items[] = [
                    'qid' => $qid,
                    'label' => $row['itemLabel']['value'] ?? null,
                    'description' => $row['itemDescription']['value'] ?? null,
                    'aliases' => $this->parseAliases($row['itemAltLabel']['value'] ?? ''),
                    'language' => 'en',
                ];
            }
        }

        return $items;
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Extract QID from Wikidata URI
     */
    private function extractQid(string $uri): ?string
    {
        if (preg_match('/Q\d+$/', $uri, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Parse comma-separated aliases
     */
    private function parseAliases(string $aliasString): array
    {
        if (empty($aliasString)) {
            return [];
        }

        return array_map('trim', explode(',', $aliasString));
    }

    /**
     * Sync South African heritage terminology
     */
    public function syncSouthAfricanTerms(): array
    {
        $logId = $this->thesaurus->createSyncLog(ThesaurusService::SOURCE_WIKIDATA, 'south_african');

        $this->thesaurus->updateSyncLog($logId, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $stats = [
            'terms_processed' => 0,
            'terms_added' => 0,
            'synonyms_added' => 0,
            'errors' => [],
        ];

        try {
            $items = $this->fetchSouthAfricanHeritage();

            foreach ($items as $item) {
                $result = $this->importWikidataItem($item, ThesaurusService::DOMAIN_ARCHIVAL);
                $stats['terms_processed']++;

                if ($result['term_id']) {
                    $stats['terms_added']++;
                    $stats['synonyms_added'] += $result['synonyms_added'];
                }
            }

        } catch (\Exception $e) {
            $stats['errors'][] = $e->getMessage();
            $this->logger->error('SA heritage sync failed', ['error' => $e->getMessage()]);
        }

        $this->thesaurus->updateSyncLog($logId, [
            'status' => empty($stats['errors']) ? 'completed' : 'completed_with_errors',
            'completed_at' => date('Y-m-d H:i:s'),
            'terms_processed' => $stats['terms_processed'],
            'terms_added' => $stats['terms_added'],
            'synonyms_added' => $stats['synonyms_added'],
            'errors' => !empty($stats['errors']) ? json_encode($stats['errors']) : null,
        ]);

        return $stats;
    }
}
