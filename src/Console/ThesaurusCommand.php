<?php

namespace AtomFramework\Console;

use AtomFramework\Services\SemanticSearch\ThesaurusService;
use AtomFramework\Services\SemanticSearch\WordNetSyncService;
use AtomFramework\Services\SemanticSearch\WikidataSyncService;
use AtomFramework\Services\SemanticSearch\EmbeddingService;
use AtomFramework\Services\SemanticSearch\SemanticSearchService;

/**
 * Thesaurus CLI Command
 *
 * Manages the semantic search thesaurus: sync from WordNet/Wikidata,
 * import local synonyms, generate embeddings, and export to Elasticsearch.
 *
 * @package AtomFramework\Console
 * @author Johan Pieterse - The Archive and Heritage Group
 */
class ThesaurusCommand
{
    protected array $argv;
    protected ThesaurusService $thesaurus;

    protected array $commands = [
        'stats' => 'Show thesaurus statistics',
        'sync-wordnet' => 'Sync terms from WordNet (Datamuse API)',
        'sync-wikidata' => 'Sync terms from Wikidata SPARQL',
        'import-local' => 'Import local JSON synonym files',
        'export-elasticsearch' => 'Export synonyms to Elasticsearch format',
        'search' => 'Search for terms in the thesaurus',
        'expand' => 'Test query expansion',
        'embeddings' => 'Generate vector embeddings (requires Ollama)',
    ];

    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->thesaurus = new ThesaurusService();
    }

    public function run(): int
    {
        $command = $this->argv[1] ?? 'help';
        $args = array_slice($this->argv, 2);

        try {
            return match ($command) {
                'stats' => $this->showStats(),
                'sync-wordnet' => $this->syncWordNet($args),
                'sync-wikidata' => $this->syncWikidata($args),
                'import-local' => $this->importLocal($args),
                'export-elasticsearch', 'export-es' => $this->exportElasticsearch($args),
                'search' => $this->search($args),
                'expand' => $this->expand($args),
                'embeddings' => $this->embeddings($args),
                'help', '--help', '-h' => $this->showHelp(),
                default => $this->unknownCommand($command),
            };
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    // ========================================================================
    // Commands
    // ========================================================================

    /**
     * Show thesaurus statistics
     */
    protected function showStats(): int
    {
        $this->header('Thesaurus Statistics');

        $stats = $this->thesaurus->getStats();

        $this->line('');
        $this->info("Total Terms:    {$stats['total_terms']}");
        $this->info("Total Synonyms: {$stats['total_synonyms']}");

        $this->line('');
        $this->line('By Source:');
        foreach ($stats['by_source'] as $source => $count) {
            $this->line("  - {$source}: {$count}");
        }

        $this->line('');
        $this->line('By Domain:');
        foreach ($stats['by_domain'] as $domain => $count) {
            $this->line("  - {$domain}: {$count}");
        }

        if (!empty($stats['recent_syncs'])) {
            $this->line('');
            $this->line('Recent Syncs:');
            foreach ($stats['recent_syncs'] as $sync) {
                $status = $sync->status === 'completed' ? "\033[32m{$sync->status}\033[0m" : $sync->status;
                $this->line("  - {$sync->source} ({$sync->sync_type}): {$status} - {$sync->terms_added} terms added");
            }
        }

        return 0;
    }

    /**
     * Sync from WordNet via Datamuse API
     */
    protected function syncWordNet(array $args): int
    {
        $this->header('WordNet Sync (Datamuse API)');

        $sync = new WordNetSyncService($this->thesaurus);

        // Check for domain flags
        $archival = in_array('--archival', $args);
        $library = in_array('--library', $args);
        $museum = in_array('--museum', $args);
        $general = in_array('--general', $args);
        $southAfrican = in_array('--south-african', $args) || in_array('--sa', $args);
        $historical = in_array('--historical', $args);
        $all = in_array('--all', $args);

        // Get custom terms (non-flag arguments)
        $customTerms = array_filter($args, fn($a) => !str_starts_with($a, '--'));

        if (!empty($customTerms)) {
            $this->info('Syncing custom terms: ' . implode(', ', $customTerms));
            $result = $sync->syncCustomTerms($customTerms);
            $this->showSyncResults($result);
            return 0;
        }

        if ($all) {
            $this->info('Syncing ALL domains (comprehensive vocabulary)...');
            $this->line('This includes: archival, library, museum, general, SA-specific, historical');
            $this->line('Approximately 730+ seed terms will be synced.');
            $this->line('');
            $results = $sync->syncAllDomains();

            foreach (['archival', 'library', 'museum', 'general', 'south_african', 'historical'] as $domain) {
                if (isset($results[$domain])) {
                    $this->line('');
                    $domainLabel = str_replace('_', ' ', ucfirst($domain));
                    $this->info("{$domainLabel} domain:");
                    $this->showSyncResults($results[$domain]);
                }
            }

            $this->line('');
            $totals = $results['totals'];
            $this->success(sprintf(
                "Totals: %d terms processed (%d new, %d updated), %d synonyms added",
                $totals['total_terms_processed'],
                $totals['total_terms_added'],
                $totals['total_terms_updated'],
                $totals['total_synonyms_added']
            ));
            return 0;
        }

        $synced = false;
        $hasSpecificFlag = $archival || $library || $museum || $general || $southAfrican || $historical;

        if ($archival || !$hasSpecificFlag) {
            $this->info('Syncing archival terms (~150 terms)...');
            $result = $sync->syncArchivalTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if ($library) {
            $this->line('');
            $this->info('Syncing library terms (~55 terms)...');
            $result = $sync->syncLibraryTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if ($museum) {
            $this->line('');
            $this->info('Syncing museum terms (~65 terms)...');
            $result = $sync->syncMuseumTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if ($general) {
            $this->line('');
            $this->info('Syncing general vocabulary (~300 terms)...');
            $result = $sync->syncGeneralTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if ($southAfrican) {
            $this->line('');
            $this->info('Syncing South African terms (~120 terms)...');
            $result = $sync->syncSouthAfricanTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if ($historical) {
            $this->line('');
            $this->info('Syncing historical terms (~40 terms)...');
            $result = $sync->syncHistoricalTerms();
            $this->showSyncResults($result);
            $synced = true;
        }

        if (!$synced) {
            $this->warn('No domain specified. Use --archival, --library, --museum, --general, --south-african, --historical, or --all');
            return 1;
        }

        return 0;
    }

    /**
     * Sync from Wikidata SPARQL
     */
    protected function syncWikidata(array $args): int
    {
        $this->header('Wikidata Sync (SPARQL)');

        $sync = new WikidataSyncService($this->thesaurus);

        $heritage = in_array('--heritage', $args);
        $sa = in_array('--south-african', $args) || in_array('--sa', $args);

        if ($sa) {
            $this->info('Syncing South African heritage terms...');
            $result = $sync->syncSouthAfricanTerms();
            $this->showSyncResults($result);
            return 0;
        }

        if ($heritage || count($args) === 0) {
            $this->info('Syncing heritage terms...');
            $result = $sync->syncHeritageTerms();
            $this->showSyncResults($result);
            return 0;
        }

        return 0;
    }

    /**
     * Import local JSON synonym files
     */
    protected function importLocal(array $args): int
    {
        $this->header('Import Local Synonyms');

        $domain = $args[0] ?? null;

        if ($domain) {
            $this->info("Importing {$domain} synonyms...");
        } else {
            $this->info('Importing all local synonym files...');
        }

        $result = $this->thesaurus->importLocalSynonyms($domain);

        $this->line('');
        $this->info("Files processed: {$result['files_processed']}");
        $this->info("Terms added:     {$result['terms_added']}");
        $this->info("Synonyms added:  {$result['synonyms_added']}");

        if (!empty($result['errors'])) {
            $this->line('');
            $this->warn('Errors:');
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return empty($result['errors']) ? 0 : 1;
    }

    /**
     * Export to Elasticsearch synonym file
     */
    protected function exportElasticsearch(array $args): int
    {
        $this->header('Export to Elasticsearch');

        $outputPath = $args[0] ?? null;

        $path = $this->thesaurus->exportToElasticsearch($outputPath);

        $this->success("Synonyms exported to: {$path}");
        $this->line('');
        $this->info('To use in Elasticsearch, add this to your index settings:');
        $this->line('');
        $this->line('  "analysis": {');
        $this->line('    "filter": {');
        $this->line('      "ahg_synonyms": {');
        $this->line('        "type": "synonym",');
        $this->line('        "synonyms_path": "' . $path . '"');
        $this->line('      }');
        $this->line('    }');
        $this->line('  }');

        return 0;
    }

    /**
     * Search the thesaurus
     */
    protected function search(array $args): int
    {
        if (empty($args)) {
            $this->error('Please provide a search term');
            return 1;
        }

        $query = implode(' ', $args);
        $this->header("Search: {$query}");

        $results = $this->thesaurus->searchTerms($query);

        if (empty($results)) {
            $this->warn('No terms found');
            return 0;
        }

        $this->line('');
        $this->line(sprintf('%-30s %-15s %-15s', 'Term', 'Source', 'Domain'));
        $this->line(str_repeat('-', 60));

        foreach ($results as $term) {
            $this->line(sprintf('%-30s %-15s %-15s',
                $this->truncate($term->term, 30),
                $term->source,
                $term->domain ?? 'general'
            ));

            // Show synonyms
            $synonyms = $this->thesaurus->getSynonyms((int) $term->id, null, null, 5);
            if (!empty($synonyms)) {
                $synTexts = array_column($synonyms, 'synonym_text');
                $this->line("  → " . implode(', ', $synTexts));
            }
        }

        return 0;
    }

    /**
     * Test query expansion
     */
    protected function expand(array $args): int
    {
        if (empty($args)) {
            $this->error('Please provide a query to expand');
            return 1;
        }

        $query = implode(' ', $args);
        $this->header("Query Expansion: {$query}");

        $result = $this->thesaurus->expandQuery($query);

        $this->line('');
        $this->info("Original query:  {$result['original_query']}");
        $this->info("Expanded query:  {$result['expanded_query']}");
        $this->info("Expansion count: {$result['expansion_count']}");

        if (!empty($result['expanded_terms'])) {
            $this->line('');
            $this->line('Expansions:');
            foreach ($result['expanded_terms'] as $term => $synonyms) {
                $this->line("  {$term} → " . implode(', ', $synonyms));
            }
        }

        return 0;
    }

    /**
     * Generate vector embeddings
     */
    protected function embeddings(array $args): int
    {
        $this->header('Vector Embeddings');

        $generateAll = in_array('--generate-all', $args);

        // Check if EmbeddingService exists
        $embeddingServicePath = dirname(__DIR__) . '/Services/SemanticSearch/EmbeddingService.php';
        if (!file_exists($embeddingServicePath)) {
            $this->warn('EmbeddingService not yet implemented');
            $this->info('This feature requires Ollama to be running locally');
            return 1;
        }

        $embedding = new EmbeddingService($this->thesaurus);

        if ($generateAll) {
            $this->info('Generating embeddings for all terms...');
            $result = $embedding->generateAllEmbeddings();
            $this->info("Generated: {$result['generated']}");
            $this->info("Skipped:   {$result['skipped']}");
            $this->info("Errors:    {$result['errors']}");
            return 0;
        }

        // Test with a single term
        $term = $args[0] ?? 'archive';
        $this->info("Testing embedding for: {$term}");

        $vector = $embedding->getEmbedding($term);

        if ($vector) {
            $this->success('Embedding generated successfully');
            $this->info("Dimension: " . count($vector));
            $this->info("Sample values: " . implode(', ', array_slice($vector, 0, 5)) . '...');
        } else {
            $this->error('Failed to generate embedding. Is Ollama running?');
            return 1;
        }

        return 0;
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    protected function showSyncResults(array $result): void
    {
        $this->line('');
        $this->info("Terms processed: {$result['terms_processed']}");
        $this->info("Terms added:     {$result['terms_added']}");
        $this->info("Synonyms added:  {$result['synonyms_added']}");

        if (!empty($result['errors'])) {
            $this->warn("Errors: " . count($result['errors']));
        }
    }

    protected function showHelp(): int
    {
        $this->line('');
        $this->line("\033[1mAtoM Thesaurus Manager\033[0m");
        $this->line('');
        $this->line("\033[33mUsage:\033[0m");
        $this->line('  php bin/atom thesaurus:<command> [options]');
        $this->line('');
        $this->line("\033[33mCommands:\033[0m");

        foreach ($this->commands as $cmd => $desc) {
            $this->line(sprintf("  %-25s %s", $cmd, $desc));
        }

        $this->line('');
        $this->line("\033[33mWordNet Sync Options:\033[0m");
        $this->line('  --archival              Sync archival domain terms (~150 terms)');
        $this->line('  --library               Sync library domain terms (~55 terms)');
        $this->line('  --museum                Sync museum domain terms (~65 terms)');
        $this->line('  --general               Sync general vocabulary (~300 terms)');
        $this->line('  --south-african, --sa   Sync South African specific terms (~120 terms)');
        $this->line('  --historical            Sync historical period terms (~40 terms)');
        $this->line('  --all                   Sync ALL domains (730+ terms - comprehensive)');
        $this->line('  <term1> <term2> ...     Sync custom terms');
        $this->line('');
        $this->line("\033[33mWikidata Sync Options:\033[0m");
        $this->line('  --heritage              Sync heritage terminology');
        $this->line('  --south-african, --sa   Sync South African heritage');
        $this->line('');
        $this->line("\033[33mExamples:\033[0m");
        $this->line('  php bin/atom thesaurus:stats');
        $this->line('  php bin/atom thesaurus:sync-wordnet --archival');
        $this->line('  php bin/atom thesaurus:sync-wordnet --all     # Sync ALL 730+ terms');
        $this->line('  php bin/atom thesaurus:sync-wordnet archive document record');
        $this->line('  php bin/atom thesaurus:sync-wikidata --heritage');
        $this->line('  php bin/atom thesaurus:import-local archival');
        $this->line('  php bin/atom thesaurus:export-elasticsearch');
        $this->line('  php bin/atom thesaurus:expand "historical documents"');
        $this->line('');

        return 0;
    }

    protected function unknownCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->showHelp();
        return 1;
    }

    // ========================================================================
    // Output Helpers
    // ========================================================================

    protected function header(string $title): void
    {
        $this->line('');
        $this->line("\033[1m{$title}\033[0m");
        $this->line(str_repeat('═', strlen($title) + 4));
    }

    protected function line(string $text): void
    {
        echo $text . "\n";
    }

    protected function info(string $text): void
    {
        echo "\033[36m{$text}\033[0m\n";
    }

    protected function success(string $text): void
    {
        echo "\033[32m✓ {$text}\033[0m\n";
    }

    protected function warn(string $text): void
    {
        echo "\033[33m⚠ {$text}\033[0m\n";
    }

    protected function error(string $text): void
    {
        echo "\033[31m✗ {$text}\033[0m\n";
    }

    protected function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
