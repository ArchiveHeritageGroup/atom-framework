#!/usr/bin/env php
<?php
/**
 * Test enhanced search functionality
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use AtomFramework\Services\Search\SearchIntegrationService;

$query = $argv[1] ?? 'photographs of miners';

echo "=== Enhanced Search Test ===\n\n";

$integration = new SearchIntegrationService();

$stats = $integration->getStats();
echo "Thesaurus: {$stats['terms']} terms, {$stats['synonyms']} synonyms\n";
echo "Enhanced search enabled: " . ($stats['enabled'] ? 'YES' : 'NO') . "\n\n";

echo "Testing query: \"$query\"\n";
echo str_repeat('-', 60) . "\n";

$result = $integration->enhanceQuery($query, ['culture' => 'en']);

echo "Enhanced: " . ($result['enhanced'] ? 'YES' : 'NO') . "\n";
echo "Original: {$result['original_query']}\n";
echo "Expanded: {$result['expanded_query']}\n";

if (!empty($result['matched_terms'])) {
    echo "\nMatched thesaurus terms:\n";
    foreach ($result['matched_terms'] as $word => $term) {
        echo "  '$word' → '$term'\n";
    }
}

if (!empty($result['synonyms'])) {
    echo "\nSynonyms found:\n";
    foreach ($result['synonyms'] as $term => $synonyms) {
        echo "  $term => " . implode(', ', array_slice($synonyms, 0, 5));
        if (count($synonyms) > 5) echo " ... (" . count($synonyms) . " total)";
        echo "\n";
    }
} else {
    echo "\nNo synonyms found for query terms.\n";
}

// Test suggestions
echo "\n" . str_repeat('-', 60) . "\n";
echo "Autocomplete suggestions for 'photo':\n";
$suggestions = $integration->getSuggestions('photo', 5);
if (empty($suggestions)) {
    echo "  No suggestions found.\n";
} else {
    foreach ($suggestions as $s) {
        echo "  [{$s['type']}] {$s['text']}\n";
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Autocomplete suggestions for 'mine':\n";
$suggestions = $integration->getSuggestions('mine', 5);
if (empty($suggestions)) {
    echo "  No suggestions found.\n";
} else {
    foreach ($suggestions as $s) {
        echo "  [{$s['type']}] {$s['text']}\n";
    }
}

echo "\nDone.\n";
