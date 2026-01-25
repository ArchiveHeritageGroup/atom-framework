<?php
declare(strict_types=1);

namespace AtomFramework\Services\Search;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Search Integration Service
 * Bridges semantic search with standard AtoM browse
 */
class SearchIntegrationService
{
    private bool $enhancedEnabled = false;
    
    public function __construct()
    {
        $this->enhancedEnabled = $this->isEnhancedSearchEnabled();
    }
    
    /**
     * Check if enhanced search is enabled globally
     */
    public function isEnhancedSearchEnabled(): bool
    {
        try {
            $setting = DB::table('ahg_semantic_search_settings')
                ->where('setting_key', 'semantic_search_enabled')
                ->value('setting_value');
            return $setting === '1' || $setting === 'true' || $setting === true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Stem a word to its base form (simple English stemming)
     */
    private function stem(string $word): array
    {
        $word = strtolower(trim($word));
        $variants = [$word];
        
        // Remove common suffixes to get stem
        $suffixes = ['ing', 'ed', 'er', 'ers', 'es', 's', 'ment', 'tion', 'sion', 'ly', 'ness'];
        
        foreach ($suffixes as $suffix) {
            if (strlen($word) > strlen($suffix) + 2 && substr($word, -strlen($suffix)) === $suffix) {
                $stem = substr($word, 0, -strlen($suffix));
                if (strlen($stem) >= 3) {
                    $variants[] = $stem;
                    // Handle doubled consonants (e.g., "running" -> "run")
                    if (strlen($stem) > 2 && $stem[-1] === $stem[-2]) {
                        $variants[] = substr($stem, 0, -1);
                    }
                }
            }
        }
        
        // Add common variations
        if (substr($word, -1) === 's' && strlen($word) > 3) {
            $variants[] = substr($word, 0, -1); // Remove trailing s
        }
        if (substr($word, -2) === 'es' && strlen($word) > 4) {
            $variants[] = substr($word, 0, -2); // Remove trailing es
        }
        if (substr($word, -3) === 'ies' && strlen($word) > 5) {
            $variants[] = substr($word, 0, -3) . 'y'; // cities -> city
        }
        
        return array_unique($variants);
    }
    
    /**
     * Enhance a search query with synonyms
     */
    public function enhanceQuery(string $query, array $options = []): array
    {
        if (!$this->enhancedEnabled || empty(trim($query))) {
            return [
                'enhanced' => false,
                'original_query' => $query,
                'expanded_query' => $query,
                'synonyms' => [],
                'matched_terms' => [],
            ];
        }
        
        $words = preg_split('/\s+/', strtolower(trim($query)));
        $allSynonyms = [];
        $expandedTerms = [];
        $matchedTerms = [];
        
        foreach ($words as $word) {
            if (strlen($word) < 3) continue;
            
            // Skip common stop words
            $stopWords = ['the', 'and', 'for', 'from', 'with', 'that', 'this', 'are', 'was', 'were', 'been'];
            if (in_array($word, $stopWords)) continue;
            
            // Get word variants (stem + original)
            $variants = $this->stem($word);
            
            // Find matching thesaurus terms - EXACT match first, then prefix
            $term = null;
            
            foreach ($variants as $variant) {
                // First try exact match (case-insensitive)
                $term = DB::table('ahg_thesaurus_term')
                    ->whereRaw('LOWER(term) = ?', [$variant])
                    ->where('is_active', 1)
                    ->first();
                
                if ($term) break;
            }
            
            // If no exact match, try prefix match but only for short single words
            if (!$term) {
                foreach ($variants as $variant) {
                    $term = DB::table('ahg_thesaurus_term')
                        ->where('term', 'LIKE', $variant . '%')
                        ->where('is_active', 1)
                        ->whereRaw('LENGTH(term) < ?', [strlen($variant) + 5]) // Don't match long phrases
                        ->orderByRaw('LENGTH(term)') // Prefer shorter matches
                        ->first();
                    
                    if ($term) break;
                }
            }
            
            if ($term) {
                $matchedTerms[$word] = $term->term;
                
                // Get synonyms - using correct column name: synonym_text
                // Lowered threshold to 0.3 to include more synonyms
                $synonyms = DB::table('ahg_thesaurus_synonym')
                    ->where('term_id', $term->id)
                    ->where('is_active', 1)
                    ->where('weight', '>=', 0.3)
                    ->orderByDesc('weight')
                    ->pluck('synonym_text')
                    ->toArray();
                
                if (!empty($synonyms)) {
                    $allSynonyms[$word] = $synonyms;
                    $expandedTerms = array_merge($expandedTerms, $synonyms);
                }
            }
        }
        
        // Build expanded query
        $expandedQuery = $query;
        if (!empty($expandedTerms)) {
            $uniqueTerms = array_unique($expandedTerms);
            // Don't add terms already in the query
            $queryWords = array_map('strtolower', $words);
            $newTerms = array_filter($uniqueTerms, fn($t) => !in_array(strtolower($t), $queryWords));
            if (!empty($newTerms)) {
                $expandedQuery .= ' ' . implode(' ', array_slice($newTerms, 0, 15)); // Limit expansion
            }
        }
        
        return [
            'enhanced' => !empty($allSynonyms),
            'original_query' => $query,
            'expanded_query' => $expandedQuery,
            'synonyms' => $allSynonyms,
            'matched_terms' => $matchedTerms,
        ];
    }
    
    /**
     * Build Elasticsearch should clauses for synonym expansion
     */
    public function buildSynonymClauses(array $synonyms): array
    {
        $clauses = [];
        
        foreach ($synonyms as $term => $synonymList) {
            foreach ($synonymList as $synonym) {
                $clauses[] = [
                    'multi_match' => [
                        'query' => $synonym,
                        'fields' => [
                            'i18n.*.title^2',
                            'i18n.*.scopeAndContent',
                            'creators.i18n.*.authorizedFormOfName',
                            'subjects.i18n.*.name',
                            'places.i18n.*.name',
                        ],
                        'boost' => 0.7,
                        'fuzziness' => 'AUTO',
                    ],
                ];
            }
        }
        
        return $clauses;
    }
    
    /**
     * Get search suggestions
     */
    public function getSuggestions(string $prefix, int $limit = 8): array
    {
        $suggestions = [];
        
        // From search history
        try {
            $popular = DB::table('ahg_semantic_search_log')
                ->select('original_query')
                ->selectRaw('COUNT(*) as count')
                ->where('original_query', 'LIKE', $prefix . '%')
                ->whereNotNull('original_query')
                ->where('original_query', '!=', '')
                ->groupBy('original_query')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
            
            foreach ($popular as $p) {
                $suggestions[] = [
                    'text' => $p->original_query,
                    'type' => 'history',
                    'count' => $p->count,
                ];
            }
        } catch (\Exception $e) {}
        
        // From thesaurus
        try {
            $terms = DB::table('ahg_thesaurus_term')
                ->where('term', 'LIKE', $prefix . '%')
                ->where('is_active', 1)
                ->whereRaw('LENGTH(term) < 30') // Skip long phrases
                ->orderByRaw('LENGTH(term)')
                ->limit($limit - count($suggestions))
                ->get();
            
            foreach ($terms as $term) {
                $suggestions[] = [
                    'text' => $term->term,
                    'type' => 'thesaurus',
                    'domain' => $term->domain ?? null,
                ];
            }
        } catch (\Exception $e) {}
        
        return array_slice($suggestions, 0, $limit);
    }
    
    /**
     * Log search for learning
     */
    public function logSearch(string $query, int $resultCount): void
    {
        try {
            DB::table('ahg_semantic_search_log')->insert([
                'original_query' => $query ?: null,
                'search_time_ms' => 0,
                'session_id' => session_id() ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {}
    }
    
    /**
     * Get statistics about the thesaurus
     */
    public function getStats(): array
    {
        return [
            'terms' => DB::table('ahg_thesaurus_term')->where('is_active', 1)->count(),
            'synonyms' => DB::table('ahg_thesaurus_synonym')->where('is_active', 1)->count(),
            'searches_logged' => DB::table('ahg_semantic_search_log')->count(),
            'enabled' => $this->enhancedEnabled,
        ];
    }
}
