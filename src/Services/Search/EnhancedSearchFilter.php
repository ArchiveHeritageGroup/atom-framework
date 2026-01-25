<?php
declare(strict_types=1);

namespace AtomFramework\Services\Search;

/**
 * Enhanced Search Filter
 * Applies synonym expansion to Elastica queries
 */
class EnhancedSearchFilter
{
    private SearchIntegrationService $integration;
    
    public function __construct()
    {
        $this->integration = new SearchIntegrationService();
    }
    
    /**
     * Apply enhanced search to an Elastica BoolQuery
     */
    public function apply(\Elastica\Query\BoolQuery $boolQuery, string $queryString, array $options = []): array
    {
        $enhancement = $this->integration->enhanceQuery($queryString, $options);
        
        if (!$enhancement['enhanced'] || empty($enhancement['synonyms'])) {
            return [
                'query' => $boolQuery,
                'enhancement' => $enhancement,
            ];
        }
        
        // Add synonym expansions as "should" clauses
        $synonymClauses = $this->integration->buildSynonymClauses($enhancement['synonyms']);
        
        foreach ($synonymClauses as $clause) {
            $multiMatch = new \Elastica\Query\MultiMatch();
            $multiMatch->setQuery($clause['multi_match']['query']);
            $multiMatch->setFields($clause['multi_match']['fields']);
            $multiMatch->setParam('boost', $clause['multi_match']['boost']);
            if (isset($clause['multi_match']['fuzziness'])) {
                $multiMatch->setFuzziness($clause['multi_match']['fuzziness']);
            }
            $boolQuery->addShould($multiMatch);
        }
        
        return [
            'query' => $boolQuery,
            'enhancement' => $enhancement,
        ];
    }
    
    /**
     * Check if enhanced search is available
     */
    public function isAvailable(): bool
    {
        return $this->integration->isEnhancedSearchEnabled();
    }
}
