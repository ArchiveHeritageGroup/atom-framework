<?php

declare(strict_types=1);

namespace AtomFramework\Heritage\Discovery;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Query Understanding Service.
 *
 * Parses natural language queries into structured search parameters.
 * Handles intent classification, entity extraction, and query expansion.
 */
class QueryUnderstandingService
{
    /**
     * Query intents.
     */
    public const INTENT_FIND = 'find';           // Looking for specific items
    public const INTENT_EXPLORE = 'explore';     // Browsing/discovering
    public const INTENT_IDENTIFY = 'identify';   // "What is this?"
    public const INTENT_COMPARE = 'compare';     // Comparing items
    public const INTENT_TRACE = 'trace';         // Following relationships
    public const INTENT_LOCATE = 'locate';       // Finding physical location

    /**
     * Entity types.
     */
    public const ENTITY_PERSON = 'person';
    public const ENTITY_ORGANIZATION = 'organization';
    public const ENTITY_PLACE = 'place';
    public const ENTITY_DATE = 'date';
    public const ENTITY_SUBJECT = 'subject';
    public const ENTITY_FORMAT = 'format';

    /**
     * Cached learned terms.
     */
    private static ?array $learnedTerms = null;

    /**
     * Current culture for queries.
     */
    private string $culture = 'en';

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;

        return $this;
    }

    /**
     * Get current culture.
     */
    public function getCulture(): string
    {
        return $this->culture;
    }

    /**
     * Parse a natural language query.
     *
     * @param string $query Raw user query
     * @return array Structured query object
     */
    public function parse(string $query): array
    {
        $query = trim($query);

        if (empty($query)) {
            return $this->emptyResult();
        }

        $result = [
            'original_query' => $query,
            'normalized_query' => $this->normalizeQuery($query),
            'language' => $this->detectLanguage($query),
            'intent' => $this->classifyIntent($query),
            'entities' => $this->extractEntities($query),
            'time_references' => $this->parseTimeReferences($query),
            'keywords' => $this->extractKeywords($query),
            'phrases' => $this->extractPhrases($query),
            'expanded_terms' => [],
            'filters' => [],
        ];

        // Expand query with synonyms and related terms
        $result['expanded_terms'] = $this->expandQuery($result);

        // Convert entities to filter suggestions
        $result['filters'] = $this->entitiesToFilters($result['entities'], $result['time_references']);

        return $result;
    }

    /**
     * Detect query language.
     */
    public function detectLanguage(string $query): string
    {
        // Simple heuristic detection
        // Could be enhanced with language detection library

        $afrikaansPatterns = [
            '/\b(van|die|en|met|vir|nie|het|sal|was|kan|deur)\b/i',
            '/\b(foto|dokument|ou|nuwe)\b/i',
        ];

        foreach ($afrikaansPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return 'af';
            }
        }

        // Default to English
        return 'en';
    }

    /**
     * Classify the user's intent.
     */
    public function classifyIntent(string $query): string
    {
        $query = strtolower($query);

        // Explore intent - browsing, discovering
        $explorePatterns = [
            '/^(show|browse|explore|discover|see|view)\b/i',
            '/^(what|which)\s+(types?|kinds?|categories)/i',
            '/\b(overview|collection|all)\b/i',
        ];

        foreach ($explorePatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return self::INTENT_EXPLORE;
            }
        }

        // Identify intent - "what is this?"
        $identifyPatterns = [
            '/^(what|who)\s+(is|was|are|were)\b/i',
            '/\b(identify|recognize|tell me about)\b/i',
        ];

        foreach ($identifyPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return self::INTENT_IDENTIFY;
            }
        }

        // Compare intent
        $comparePatterns = [
            '/\b(compare|versus|vs|difference|between)\b/i',
            '/\b(similar|like|related to)\b/i',
        ];

        foreach ($comparePatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return self::INTENT_COMPARE;
            }
        }

        // Trace intent - following relationships
        $tracePatterns = [
            '/\b(history|origin|provenance|came from|belonged to)\b/i',
            '/\b(related|connected|linked)\b/i',
        ];

        foreach ($tracePatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return self::INTENT_TRACE;
            }
        }

        // Locate intent - physical location
        $locatePatterns = [
            '/\b(where|location|located|stored|kept|find)\b/i',
            '/\b(shelf|box|folder|repository)\b/i',
        ];

        foreach ($locatePatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return self::INTENT_LOCATE;
            }
        }

        // Default: Find intent
        return self::INTENT_FIND;
    }

    /**
     * Extract entities from query.
     */
    public function extractEntities(string $query): array
    {
        $entities = [];

        // Extract person names (basic pattern)
        $entities = array_merge($entities, $this->extractPersons($query));

        // Extract organizations
        $entities = array_merge($entities, $this->extractOrganizations($query));

        // Extract places
        $entities = array_merge($entities, $this->extractPlaces($query));

        // Extract format types
        $entities = array_merge($entities, $this->extractFormats($query));

        // Extract subjects from taxonomy matches
        $entities = array_merge($entities, $this->extractSubjects($query));

        return $entities;
    }

    /**
     * Extract person names.
     */
    private function extractPersons(string $query): array
    {
        $entities = [];

        // Pattern: "by [Name]" or "[Name]'s"
        if (preg_match('/\bby\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/u', $query, $matches)) {
            $entities[] = [
                'type' => self::ENTITY_PERSON,
                'value' => $matches[1],
                'confidence' => 0.8,
            ];
        }

        // Pattern: possessive
        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\'s?\b/u', $query, $matches)) {
            $entities[] = [
                'type' => self::ENTITY_PERSON,
                'value' => $matches[1],
                'confidence' => 0.7,
            ];
        }

        // Check against known actors in database
        $names = $this->findMatchingActors($query);
        foreach ($names as $name) {
            $entities[] = [
                'type' => self::ENTITY_PERSON,
                'value' => $name->authorized_form_of_name,
                'id' => $name->id,
                'confidence' => 0.9,
            ];
        }

        return $entities;
    }

    /**
     * Extract organization names.
     */
    private function extractOrganizations(string $query): array
    {
        $entities = [];

        $orgPatterns = [
            '/\b(company|corporation|corp|inc|ltd|limited|association|society|institute|university|college|school|museum|library|archive|department|ministry|government)\b/i',
        ];

        foreach ($orgPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                // Extract surrounding context
                if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\s+(?:company|corporation|corp|inc|ltd|limited|association|society|institute|university|college|school|museum|library|archive))/i', $query, $matches)) {
                    $entities[] = [
                        'type' => self::ENTITY_ORGANIZATION,
                        'value' => $matches[1],
                        'confidence' => 0.75,
                    ];
                }
            }
        }

        return $entities;
    }

    /**
     * Extract place names.
     */
    private function extractPlaces(string $query): array
    {
        $entities = [];

        // Pattern: "in [Place]" or "from [Place]" or "at [Place]"
        if (preg_match('/\b(?:in|from|at|near)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/u', $query, $matches)) {
            $entities[] = [
                'type' => self::ENTITY_PLACE,
                'value' => $matches[1],
                'confidence' => 0.75,
            ];
        }

        // Check against known places in database (place access points)
        $places = $this->findMatchingPlaces($query);
        foreach ($places as $place) {
            $entities[] = [
                'type' => self::ENTITY_PLACE,
                'value' => $place->name,
                'id' => $place->id,
                'confidence' => 0.85,
            ];
        }

        return $entities;
    }

    /**
     * Extract format/media types.
     */
    private function extractFormats(string $query): array
    {
        $entities = [];

        $formatMap = [
            'photo' => 'Photograph',
            'photos' => 'Photograph',
            'photograph' => 'Photograph',
            'photographs' => 'Photograph',
            'picture' => 'Photograph',
            'pictures' => 'Photograph',
            'image' => 'Photograph',
            'images' => 'Photograph',
            'map' => 'Map',
            'maps' => 'Map',
            'letter' => 'Correspondence',
            'letters' => 'Correspondence',
            'document' => 'Textual record',
            'documents' => 'Textual record',
            'video' => 'Moving image',
            'videos' => 'Moving image',
            'film' => 'Moving image',
            'films' => 'Moving image',
            'audio' => 'Sound recording',
            'recording' => 'Sound recording',
            'recordings' => 'Sound recording',
            'newspaper' => 'Newspaper',
            'newspapers' => 'Newspaper',
            'poster' => 'Graphic material',
            'posters' => 'Graphic material',
            'drawing' => 'Graphic material',
            'drawings' => 'Graphic material',
            'painting' => 'Graphic material',
            'artifact' => 'Object',
            'artefact' => 'Object',
            'object' => 'Object',
        ];

        $queryLower = strtolower($query);
        foreach ($formatMap as $term => $format) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/', $queryLower)) {
                $entities[] = [
                    'type' => self::ENTITY_FORMAT,
                    'value' => $format,
                    'matched_term' => $term,
                    'confidence' => 0.9,
                ];
                break; // Only take first match
            }
        }

        return $entities;
    }

    /**
     * Extract subjects from taxonomy.
     */
    private function extractSubjects(string $query): array
    {
        $entities = [];

        // Find matching subject terms
        $subjects = $this->findMatchingSubjects($query);
        foreach ($subjects as $subject) {
            $entities[] = [
                'type' => self::ENTITY_SUBJECT,
                'value' => $subject->name,
                'id' => $subject->id,
                'confidence' => 0.85,
            ];
        }

        return $entities;
    }

    /**
     * Parse time references from query.
     */
    public function parseTimeReferences(string $query): array
    {
        $references = [];

        // Specific years
        if (preg_match_all('/\b(1[0-9]{3}|20[0-2][0-9])\b/', $query, $matches)) {
            foreach ($matches[1] as $year) {
                $references[] = [
                    'type' => 'year',
                    'value' => $year,
                    'start' => $year . '-01-01',
                    'end' => $year . '-12-31',
                ];
            }
        }

        // Decades (1950s, 50s)
        if (preg_match_all('/\b(1[0-9])([0-9])0s\b/i', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $decade = $match[1] . $match[2] . '0';
                $references[] = [
                    'type' => 'decade',
                    'value' => $decade . 's',
                    'start' => $decade . '-01-01',
                    'end' => ((int) $decade + 9) . '-12-31',
                ];
            }
        }

        // Short decades (50s, 60s)
        if (preg_match_all('/\b([2-9])0s\b/i', $query, $matches)) {
            foreach ($matches[1] as $d) {
                $decade = '19' . $d . '0';
                $references[] = [
                    'type' => 'decade',
                    'value' => $d . '0s',
                    'start' => $decade . '-01-01',
                    'end' => ((int) $decade + 9) . '-12-31',
                ];
            }
        }

        // Year ranges (1950-1960)
        if (preg_match_all('/\b(1[0-9]{3}|20[0-2][0-9])\s*[-–to]+\s*(1[0-9]{3}|20[0-2][0-9])\b/', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $references[] = [
                    'type' => 'range',
                    'value' => $match[1] . '-' . $match[2],
                    'start' => $match[1] . '-01-01',
                    'end' => $match[2] . '-12-31',
                ];
            }
        }

        // Century references
        $centuryMap = [
            '19th century' => ['1800-01-01', '1899-12-31'],
            'nineteenth century' => ['1800-01-01', '1899-12-31'],
            '20th century' => ['1900-01-01', '1999-12-31'],
            'twentieth century' => ['1900-01-01', '1999-12-31'],
            '21st century' => ['2000-01-01', '2099-12-31'],
            'twenty-first century' => ['2000-01-01', '2099-12-31'],
        ];

        foreach ($centuryMap as $term => $range) {
            if (stripos($query, $term) !== false) {
                $references[] = [
                    'type' => 'century',
                    'value' => $term,
                    'start' => $range[0],
                    'end' => $range[1],
                ];
            }
        }

        // Era references
        $eraMap = [
            'victorian' => ['1837-01-01', '1901-12-31'],
            'edwardian' => ['1901-01-01', '1910-12-31'],
            'pre-war' => ['1900-01-01', '1913-12-31'],
            'inter-war' => ['1918-01-01', '1939-12-31'],
            'post-war' => ['1945-01-01', '1960-12-31'],
            'world war i' => ['1914-01-01', '1918-12-31'],
            'world war ii' => ['1939-01-01', '1945-12-31'],
            'wwi' => ['1914-01-01', '1918-12-31'],
            'wwii' => ['1939-01-01', '1945-12-31'],
            'apartheid' => ['1948-01-01', '1994-12-31'],
            'colonial' => ['1652-01-01', '1910-12-31'],
        ];

        foreach ($eraMap as $term => $range) {
            if (stripos($query, $term) !== false) {
                $references[] = [
                    'type' => 'era',
                    'value' => $term,
                    'start' => $range[0],
                    'end' => $range[1],
                ];
            }
        }

        return $references;
    }

    /**
     * Expand query with synonyms and related terms.
     */
    public function expandQuery(array $parsed): array
    {
        $expanded = [];
        $terms = array_merge($parsed['keywords'], array_column($parsed['entities'], 'value'));

        foreach ($terms as $term) {
            $related = $this->getRelatedTerms(strtolower($term));
            foreach ($related as $rel) {
                $expanded[$rel->related_term] = [
                    'term' => $rel->related_term,
                    'relationship' => $rel->relationship_type,
                    'confidence' => (float) $rel->confidence_score,
                    'source_term' => $term,
                ];
            }
        }

        return array_values($expanded);
    }

    /**
     * Extract keywords (remove stop words).
     */
    private function extractKeywords(string $query): array
    {
        $stopWords = [
            'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
            'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
            'dare', 'ought', 'used', 'this', 'that', 'these', 'those', 'i', 'you',
            'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'whom',
            'show', 'me', 'find', 'search', 'looking', 'want', 'see', 'about',
            'any', 'all', 'some', 'no', 'not', 'only', 'own', 'same', 'so',
            'than', 'too', 'very', 'just', 'also', 'now', 'here', 'there',
        ];

        // Normalize and split
        $words = preg_split('/[\s,.;:!?\'"()[\]{}]+/', strtolower($query));

        // Filter
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 1 && !in_array($word, $stopWords) && !is_numeric($word);
        });

        return array_values($keywords);
    }

    /**
     * Extract quoted phrases.
     */
    private function extractPhrases(string $query): array
    {
        $phrases = [];

        // Quoted phrases
        if (preg_match_all('/"([^"]+)"|\'([^\']+)\'/', $query, $matches)) {
            foreach ($matches[1] as $phrase) {
                if (!empty($phrase)) {
                    $phrases[] = $phrase;
                }
            }
            foreach ($matches[2] as $phrase) {
                if (!empty($phrase)) {
                    $phrases[] = $phrase;
                }
            }
        }

        return $phrases;
    }

    /**
     * Convert entities to filter suggestions.
     */
    private function entitiesToFilters(array $entities, array $timeRefs): array
    {
        $filters = [];

        foreach ($entities as $entity) {
            switch ($entity['type']) {
                case self::ENTITY_FORMAT:
                    $filters['content_type'][] = $entity['value'];
                    break;
                case self::ENTITY_PERSON:
                    if (isset($entity['id'])) {
                        $filters['creator'][] = $entity['id'];
                    }
                    break;
                case self::ENTITY_PLACE:
                    if (isset($entity['id'])) {
                        $filters['place'][] = $entity['id'];
                    }
                    break;
                case self::ENTITY_SUBJECT:
                    if (isset($entity['id'])) {
                        $filters['subject'][] = $entity['id'];
                    }
                    break;
            }
        }

        // Add time filters
        if (!empty($timeRefs)) {
            $filters['time_ranges'] = array_map(function ($ref) {
                return ['start' => $ref['start'], 'end' => $ref['end']];
            }, $timeRefs);
        }

        return $filters;
    }

    /**
     * Normalize query text.
     */
    private function normalizeQuery(string $query): string
    {
        // Lowercase
        $normalized = strtolower($query);

        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Remove special characters except quotes
        $normalized = preg_replace('/[^\w\s\'".-]/', '', $normalized);

        return trim($normalized);
    }

    /**
     * Return empty result structure.
     */
    private function emptyResult(): array
    {
        return [
            'original_query' => '',
            'normalized_query' => '',
            'language' => $this->culture,
            'intent' => self::INTENT_EXPLORE,
            'entities' => [],
            'time_references' => [],
            'keywords' => [],
            'phrases' => [],
            'expanded_terms' => [],
            'filters' => [],
        ];
    }

    /**
     * Find actors matching query terms.
     */
    private function findMatchingActors(string $query): array
    {
        $words = preg_split('/\s+/', $query);
        $properNouns = array_filter($words, function ($w) {
            return preg_match('/^[A-Z][a-z]+$/', $w);
        });

        if (empty($properNouns)) {
            return [];
        }

        return DB::table('actor_i18n')
            ->where('culture', $this->culture)
            ->where(function ($q) use ($properNouns) {
                foreach ($properNouns as $name) {
                    $q->orWhere('authorized_form_of_name', 'LIKE', '%' . $name . '%');
                }
            })
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Find places matching query terms.
     */
    private function findMatchingPlaces(string $query): array
    {
        // Place access points taxonomy = 42
        return DB::table('term_i18n as ti')
            ->join('term as t', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', 42)
            ->where('ti.culture', $this->culture)
            ->where('ti.name', 'LIKE', '%' . $query . '%')
            ->limit(5)
            ->select('t.id', 'ti.name')
            ->get()
            ->toArray();
    }

    /**
     * Find subjects matching query terms.
     */
    private function findMatchingSubjects(string $query): array
    {
        // Subject access points taxonomy = 35
        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            return [];
        }

        return DB::table('term_i18n as ti')
            ->join('term as t', 'ti.id', '=', 't.id')
            ->where('t.taxonomy_id', 35)
            ->where('ti.culture', $this->culture)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('ti.name', 'LIKE', '%' . $kw . '%');
                }
            })
            ->limit(10)
            ->select('t.id', 'ti.name')
            ->get()
            ->toArray();
    }

    /**
     * Get related terms from learned vocabulary.
     */
    private function getRelatedTerms(string $term): array
    {
        return DB::table('heritage_learned_term')
            ->where('term', $term)
            ->where('is_enabled', 1)
            ->orderByDesc('confidence_score')
            ->limit(5)
            ->get()
            ->toArray();
    }
}
