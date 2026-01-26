<?php
declare(strict_types=1);

namespace AtomFramework\Services\Search;

/**
 * Browse Search Enhancer
 * 
 * Hooks into AtoM's browse action to enhance search with synonyms.
 * Call this from a modified browseAction or via event/filter.
 */
class BrowseSearchEnhancer
{
    private static ?SearchIntegrationService $service = null;
    private static ?array $lastEnhancement = null;
    
    /**
     * Get the integration service (singleton)
     */
    private static function getService(): SearchIntegrationService
    {
        if (self::$service === null) {
            self::$service = new SearchIntegrationService();
        }
        return self::$service;
    }
    
    /**
     * Enhance a search query and return expanded terms
     * 
     * @param string $query The user's search query
     * @return array Enhancement data including synonyms
     */
    public static function enhance(string $query): array
    {
        $service = self::getService();
        
        if (!$service->isEnhancedSearchEnabled()) {
            self::$lastEnhancement = ['enhanced' => false, 'synonyms' => []];
            return self::$lastEnhancement;
        }
        
        self::$lastEnhancement = $service->enhanceQuery($query);
        return self::$lastEnhancement;
    }
    
    /**
     * Get the last enhancement result (for display in UI)
     */
    public static function getLastEnhancement(): ?array
    {
        return self::$lastEnhancement;
    }
    
    /**
     * Apply synonym expansion to an Elastica BoolQuery
     * 
     * @param \Elastica\Query\BoolQuery $boolQuery
     * @param string $query
     * @return \Elastica\Query\BoolQuery Modified query
     */
    public static function applyToQuery(\Elastica\Query\BoolQuery $boolQuery, string $query): \Elastica\Query\BoolQuery
    {
        $enhancement = self::enhance($query);
        
        if (!$enhancement['enhanced'] || empty($enhancement['synonyms'])) {
            return $boolQuery;
        }
        
        $service = self::getService();
        $clauses = $service->buildSynonymClauses($enhancement['synonyms']);
        
        foreach ($clauses as $clause) {
            try {
                $multiMatch = new \Elastica\Query\MultiMatch();
                $multiMatch->setQuery($clause['multi_match']['query']);
                $multiMatch->setFields($clause['multi_match']['fields']);
                $multiMatch->setParam('boost', $clause['multi_match']['boost'] ?? 0.7);
                
                if (isset($clause['multi_match']['fuzziness'])) {
                    $multiMatch->setFuzziness($clause['multi_match']['fuzziness']);
                }
                
                $boolQuery->addShould($multiMatch);
            } catch (\Exception $e) {
                // Skip invalid clauses
            }
        }
        
        return $boolQuery;
    }
    
    /**
     * Check if enhanced search is enabled
     */
    public static function isEnabled(): bool
    {
        return self::getService()->isEnhancedSearchEnabled();
    }
    
    /**
     * Log a search query
     */
    public static function logSearch(string $query, int $resultCount): void
    {
        self::getService()->logSearch($query, $resultCount);
    }
}
