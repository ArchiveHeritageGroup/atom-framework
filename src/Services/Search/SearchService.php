<?php

declare(strict_types=1);

namespace AtomFramework\Services\Search;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone Elasticsearch search service for Heratio.
 *
 * Queries ES directly via curl HTTP. No Symfony or PHP client dependency.
 * Falls back to database LIKE queries if ES is unavailable.
 *
 * Usage:
 *   $results = SearchService::search('pottery', ['type' => 'archive']);
 *   $results = SearchService::autocomplete('pot', 'informationobject');
 *   $results = SearchService::browse([
 *       'type' => 'archive',
 *       'facets' => ['level', 'creator', 'subject'],
 *       'filters' => ['level' => 'File'],
 *       'sort' => 'relevance',
 *       'page' => 1,
 *       'limit' => 30,
 *   ]);
 */
class SearchService
{
    private static ?string $host = null;
    private static ?int $port = null;
    private static ?string $indexPrefix = null;

    /**
     * QubitTerm publication status constants.
     * Duplicated here to avoid Symfony dependency.
     */
    private const PUBLICATION_STATUS_DRAFT_ID = 159;
    private const PUBLICATION_STATUS_PUBLISHED_ID = 160;

    // ----------------------------------------------------------------
    // Configuration
    // ----------------------------------------------------------------

    /**
     * Initialize ES connection from config.
     * Reads from sfConfig if available, falls back to environment/defaults.
     */
    private static function init(): void
    {
        if (null !== self::$host) {
            return;
        }

        if (class_exists('\sfConfig', false)) {
            self::$host = \sfConfig::get('app_opensearch_host', 'localhost');
            self::$port = (int) \sfConfig::get('app_opensearch_port', 9200);

            $indexName = \sfConfig::get('app_opensearch_index_name', '');
            if (empty($indexName)) {
                try {
                    $indexName = DB::connection()->getDatabaseName();
                } catch (\Exception $e) {
                    $indexName = 'archive';
                }
            }
            self::$indexPrefix = $indexName;
        } else {
            self::$host = getenv('ES_HOST') ?: 'localhost';
            self::$port = (int) (getenv('ES_PORT') ?: 9200);
            self::$indexPrefix = getenv('ES_INDEX') ?: 'archive';
        }
    }

    /**
     * Get ES base URL.
     */
    private static function baseUrl(): string
    {
        self::init();

        return 'http://' . self::$host . ':' . self::$port;
    }

    /**
     * Get index name for entity type.
     */
    private static function indexName(string $entityType): string
    {
        self::init();

        $map = [
            'informationobject' => 'qubitinformationobject',
            'actor' => 'qubitactor',
            'repository' => 'qubitrepository',
            'term' => 'qubitterm',
            'accession' => 'qubitaccession',
            'functionobject' => 'qubitfunctionobject',
        ];

        $suffix = $map[$entityType] ?? $entityType;

        return self::$indexPrefix . '_' . $suffix;
    }

    // ----------------------------------------------------------------
    // Core Search
    // ----------------------------------------------------------------

    /**
     * Full-text search with facets and filters.
     *
     * Options:
     *   'entityType'        => 'informationobject' (default)
     *   'culture'           => 'en'
     *   'filters'           => ['level' => 123, 'creator' => 'John', ...]
     *   'facets'            => ['level', 'creator', 'subject', 'place', 'media_type', 'repository']
     *   'sort'              => 'relevance'|'title_asc'|'title_desc'|'date_asc'|'date_desc'|'updated'|'identifier'
     *   'page'              => 1
     *   'limit'             => 30
     *   'publicationStatus' => 'published' (default) | 'draft' | 'all'
     *   '_match_all'        => true  (internal, set by browse())
     *
     * Returns:
     *   [
     *     'hits'   => [['id'=>1, 'slug'=>'x', 'title'=>'y', '_score'=>1.5], ...],
     *     'total'  => 150,
     *     'facets' => ['level' => ['File'=>30, 'Item'=>20], ...],
     *     'page'   => 1,
     *     'limit'  => 30,
     *   ]
     */
    public static function search(string $query, array $options = []): array
    {
        $entityType = $options['entityType'] ?? 'informationobject';
        $culture = $options['culture'] ?? 'en';
        $filters = $options['filters'] ?? [];
        $requestedFacets = $options['facets'] ?? [];
        $sort = $options['sort'] ?? 'relevance';
        $page = max(1, (int) ($options['page'] ?? 1));
        $limit = max(1, min(200, (int) ($options['limit'] ?? 30)));
        $pubStatus = $options['publicationStatus'] ?? 'published';
        $matchAll = !empty($options['_match_all']);

        // Build the bool query
        $must = [];
        $filterClauses = [];

        // Main query clause
        if ($matchAll || '' === trim($query)) {
            $must[] = ['match_all' => new \stdClass()];
        } else {
            $must[] = self::buildQueryClause($query, $entityType, $culture);
        }

        // Publication status filter (information objects only)
        if ('informationobject' === $entityType) {
            if ('published' === $pubStatus) {
                $filterClauses[] = ['term' => ['publicationStatusId' => self::PUBLICATION_STATUS_PUBLISHED_ID]];
            } elseif ('draft' === $pubStatus) {
                $filterClauses[] = ['term' => ['publicationStatusId' => self::PUBLICATION_STATUS_DRAFT_ID]];
            }
            // 'all' => no filter
        }

        // User-supplied filters
        foreach ($filters as $filterName => $filterValue) {
            $clause = self::buildFilterClause($filterName, $filterValue, $culture);
            if (null !== $clause) {
                $filterClauses[] = $clause;
            }
        }

        // Sort
        $sortArray = self::buildSort($sort, $culture);

        // Aggregations
        $aggs = [];
        if (!empty($requestedFacets)) {
            foreach ($requestedFacets as $facetName) {
                $agg = self::buildAggregation($facetName, $culture);
                if (null !== $agg) {
                    $aggs[$facetName] = $agg;
                }
            }
        }

        // Source fields
        $source = self::sourceFields($entityType, $culture);

        // Assemble body
        $body = [
            'query' => [
                'bool' => array_filter([
                    'must' => $must,
                    'filter' => $filterClauses ?: null,
                ]),
            ],
            'sort' => $sortArray,
            'from' => ($page - 1) * $limit,
            'size' => $limit,
            '_source' => $source,
        ];

        if (!empty($aggs)) {
            $body['aggs'] = $aggs;
        }

        // Execute
        $index = self::indexName($entityType);
        $raw = self::esQuery($index, $body);

        if (null === $raw) {
            // ES unavailable — try database fallback
            $fallback = self::searchFallback($query, $entityType, $culture, $page, $limit);
            $fallback['page'] = $page;
            $fallback['limit'] = $limit;

            return $fallback;
        }

        $parsed = self::parseResponse($raw, $entityType, $culture);
        $parsed['page'] = $page;
        $parsed['limit'] = $limit;

        return $parsed;
    }

    /**
     * Autocomplete search (fast, edge-ngram).
     *
     * Returns: [['id'=>1, 'slug'=>'x', 'title'=>'y'], ...]
     */
    public static function autocomplete(
        string $prefix,
        string $entityType = 'informationobject',
        string $culture = 'en',
        int $limit = 10
    ): array {
        if ('' === trim($prefix)) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        // Build the autocomplete field based on entity type
        $autocompleteField = self::autocompleteField($entityType, $culture);

        $filterClauses = [];
        if ('informationobject' === $entityType) {
            $filterClauses[] = ['term' => ['publicationStatusId' => self::PUBLICATION_STATUS_PUBLISHED_ID]];
        }

        $body = [
            'query' => [
                'bool' => array_filter([
                    'must' => [
                        ['match' => [$autocompleteField => ['query' => $prefix, 'analyzer' => 'standard']]],
                    ],
                    'filter' => $filterClauses ?: null,
                ]),
            ],
            'size' => $limit,
            '_source' => self::autocompleteSourceFields($entityType, $culture),
        ];

        $index = self::indexName($entityType);
        $raw = self::esQuery($index, $body);

        if (null === $raw) {
            return [];
        }

        return self::parseAutocompleteResponse($raw, $entityType, $culture);
    }

    /**
     * Browse with faceted navigation (for GLAM display).
     *
     * Returns same structure as search() but may use match_all for empty query.
     */
    public static function browse(array $options = []): array
    {
        $query = $options['query'] ?? '';
        if ('' === trim($query)) {
            $options['_match_all'] = true;
        }

        return self::search($query, $options);
    }

    // ----------------------------------------------------------------
    // Query Building Helpers
    // ----------------------------------------------------------------

    /**
     * Build the main query clause for full-text search.
     */
    private static function buildQueryClause(string $query, string $entityType, string $culture): array
    {
        if ('informationobject' === $entityType) {
            return [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        "i18n.{$culture}.title^3",
                        "i18n.{$culture}.title.autocomplete",
                        'identifier^2',
                        'referenceCode',
                        "i18n.{$culture}.scopeAndContent",
                        "i18n.{$culture}.archivalHistory",
                        "i18n.{$culture}.extentAndMedium",
                        'all',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ('actor' === $entityType) {
            return [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        "i18n.{$culture}.authorizedFormOfName^3",
                        "i18n.{$culture}.authorizedFormOfName.autocomplete",
                        'descriptionIdentifier^2',
                        'all',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ('repository' === $entityType) {
            return [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        "i18n.{$culture}.authorizedFormOfName^3",
                        "i18n.{$culture}.authorizedFormOfName.autocomplete",
                        'identifier^2',
                        'all',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ('term' === $entityType) {
            return [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        "i18n.{$culture}.name^3",
                        "i18n.{$culture}.name.autocomplete",
                        'all',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if ('accession' === $entityType) {
            return [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        "i18n.{$culture}.title^3",
                        'identifier^2',
                        "i18n.{$culture}.scopeAndContent",
                        'all',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Generic fallback
        return [
            'multi_match' => [
                'query' => $query,
                'fields' => ['all'],
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',
            ],
        ];
    }

    /**
     * Build an ES filter clause from a named filter.
     */
    private static function buildFilterClause(string $name, $value, string $culture): ?array
    {
        if ('' === (string) $value) {
            return null;
        }

        $fieldMap = [
            'level' => 'levelOfDescriptionId',
            'levelOfDescriptionId' => 'levelOfDescriptionId',
            'creator' => "creators.i18n.{$culture}.authorizedFormOfName.untouched",
            'subject' => "subjects.i18n.{$culture}.name.untouched",
            'place' => "places.i18n.{$culture}.name.untouched",
            'genre' => "genres.i18n.{$culture}.name.untouched",
            'media_type' => 'digitalObject.mediaTypeId',
            'mediaTypeId' => 'digitalObject.mediaTypeId',
            'repository' => 'repository.slug',
            'repositoryId' => 'repository.id',
            'repository.id' => 'repository.id',
            'publicationStatusId' => 'publicationStatusId',
            'taxonomyId' => 'taxonomyId',
            'entityTypeId' => 'entityTypeId',
            'parent' => 'parentId',
            'parentId' => 'parentId',
            'ancestor' => 'ancestors',
            'ancestors' => 'ancestors',
            'hasDigitalObject' => 'hasDigitalObject',
            'copyrightStatusId' => 'copyrightStatusId',
            'materialTypeId' => 'materialTypeId',
        ];

        $field = $fieldMap[$name] ?? $name;

        // Handle array values (terms query)
        if (is_array($value)) {
            return ['terms' => [$field => $value]];
        }

        // Handle boolean
        if (is_bool($value)) {
            return ['term' => [$field => $value]];
        }

        // Handle integer IDs
        if (is_numeric($value)) {
            return ['term' => [$field => (int) $value]];
        }

        // Handle string keyword values
        return ['term' => [$field => $value]];
    }

    /**
     * Build sort array for ES.
     */
    private static function buildSort(string $sort, string $culture): array
    {
        $sortMap = [
            'relevance' => [['_score' => 'desc']],
            'title_asc' => [["i18n.{$culture}.title.untouched" => ['order' => 'asc', 'unmapped_type' => 'keyword']]],
            'title_desc' => [["i18n.{$culture}.title.untouched" => ['order' => 'desc', 'unmapped_type' => 'keyword']]],
            'date_asc' => [['startDateSort' => ['order' => 'asc', 'unmapped_type' => 'date']]],
            'date_desc' => [['startDateSort' => ['order' => 'desc', 'unmapped_type' => 'date']]],
            'updated' => [['updatedAt' => ['order' => 'desc', 'unmapped_type' => 'date']]],
            'identifier' => [['identifier.untouched' => ['order' => 'asc', 'unmapped_type' => 'keyword']]],
            'name_asc' => [["i18n.{$culture}.authorizedFormOfName.untouched" => ['order' => 'asc', 'unmapped_type' => 'keyword']]],
            'name_desc' => [["i18n.{$culture}.authorizedFormOfName.untouched" => ['order' => 'desc', 'unmapped_type' => 'keyword']]],
        ];

        return $sortMap[$sort] ?? $sortMap['relevance'];
    }

    /**
     * Build aggregation definition for a facet name.
     */
    private static function buildAggregation(string $facetName, string $culture): ?array
    {
        $aggMap = [
            'level' => ['terms' => ['field' => 'levelOfDescriptionId', 'size' => 20]],
            'creator' => ['terms' => ['field' => "creators.i18n.{$culture}.authorizedFormOfName.untouched", 'size' => 20]],
            'subject' => ['terms' => ['field' => "subjects.i18n.{$culture}.name.untouched", 'size' => 30]],
            'place' => ['terms' => ['field' => "places.i18n.{$culture}.name.untouched", 'size' => 30]],
            'genre' => ['terms' => ['field' => "genres.i18n.{$culture}.name.untouched", 'size' => 30]],
            'media_type' => ['terms' => ['field' => 'digitalObject.mediaTypeId', 'size' => 10]],
            'repository' => ['terms' => ['field' => 'repository.slug', 'size' => 20]],
            'has_digital_object' => ['terms' => ['field' => 'hasDigitalObject', 'size' => 2]],
            'copyright_status' => ['terms' => ['field' => 'copyrightStatusId', 'size' => 10]],
            'entity_type' => ['terms' => ['field' => 'entityTypeId', 'size' => 10]],
            'taxonomy' => ['terms' => ['field' => 'taxonomyId', 'size' => 50]],
        ];

        return $aggMap[$facetName] ?? null;
    }

    /**
     * Get _source fields for search results by entity type.
     */
    private static function sourceFields(string $entityType, string $culture): array
    {
        if ('informationobject' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.title",
                'i18n.en.title',
                'identifier',
                'referenceCode',
                'levelOfDescriptionId',
                'publicationStatusId',
                'hasDigitalObject',
                'digitalObject.mediaTypeId',
                'digitalObject.thumbnailPath',
                "creators.i18n.{$culture}.authorizedFormOfName",
                'repository.slug',
                'repository.id',
                'startDateSort',
                'createdAt',
                'updatedAt',
            ];
        }

        if ('actor' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.authorizedFormOfName",
                'i18n.en.authorizedFormOfName',
                'descriptionIdentifier',
                'entityTypeId',
                'hasDigitalObject',
                'digitalObject.thumbnailPath',
                'createdAt',
                'updatedAt',
            ];
        }

        if ('repository' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.authorizedFormOfName",
                'i18n.en.authorizedFormOfName',
                'identifier',
                'logoPath',
                'createdAt',
                'updatedAt',
            ];
        }

        if ('term' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.name",
                'i18n.en.name',
                'taxonomyId',
                'numberOfDescendants',
                'createdAt',
                'updatedAt',
            ];
        }

        if ('accession' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.title",
                'i18n.en.title',
                'identifier',
                'date',
                'createdAt',
                'updatedAt',
            ];
        }

        return ['slug', 'createdAt', 'updatedAt'];
    }

    /**
     * Get autocomplete field name based on entity type.
     */
    private static function autocompleteField(string $entityType, string $culture): string
    {
        if ('actor' === $entityType || 'repository' === $entityType) {
            return "i18n.{$culture}.authorizedFormOfName.autocomplete";
        }

        if ('term' === $entityType) {
            return "i18n.{$culture}.name.autocomplete";
        }

        // informationobject, accession, default
        return "i18n.{$culture}.title.autocomplete";
    }

    /**
     * Get _source fields for autocomplete results.
     */
    private static function autocompleteSourceFields(string $entityType, string $culture): array
    {
        if ('actor' === $entityType || 'repository' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.authorizedFormOfName",
                'i18n.en.authorizedFormOfName',
            ];
        }

        if ('term' === $entityType) {
            return [
                'slug',
                "i18n.{$culture}.name",
                'i18n.en.name',
                'taxonomyId',
            ];
        }

        // informationobject
        return [
            'slug',
            "i18n.{$culture}.title",
            'i18n.en.title',
            'identifier',
            'levelOfDescriptionId',
        ];
    }

    // ----------------------------------------------------------------
    // Database Fallback
    // ----------------------------------------------------------------

    /**
     * Fallback search using database LIKE queries when ES is unavailable.
     */
    public static function searchFallback(
        string $query,
        string $entityType = 'informationobject',
        string $culture = 'en',
        int $page = 1,
        int $limit = 30
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;
        $likeQuery = '%' . $query . '%';

        if ('informationobject' === $entityType) {
            return self::searchFallbackInformationObject($likeQuery, $culture, $limit, $offset);
        }

        if ('actor' === $entityType) {
            return self::searchFallbackActor($likeQuery, $culture, $limit, $offset);
        }

        if ('repository' === $entityType) {
            return self::searchFallbackRepository($likeQuery, $culture, $limit, $offset);
        }

        if ('term' === $entityType) {
            return self::searchFallbackTerm($likeQuery, $culture, $limit, $offset);
        }

        if ('accession' === $entityType) {
            return self::searchFallbackAccession($likeQuery, $culture, $limit, $offset);
        }

        return ['hits' => [], 'total' => 0, 'facets' => []];
    }

    /**
     * Fallback: information_object.
     */
    private static function searchFallbackInformationObject(string $likeQuery, string $culture, int $limit, int $offset): array
    {
        $baseQuery = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as ioi', function ($join) use ($culture) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('status as st', function ($join) {
                $join->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', 158); // publicationStatus type
            })
            ->where('io.id', '!=', 1) // Skip root
            ->where(function ($q) use ($likeQuery) {
                $q->where('ioi.title', 'LIKE', $likeQuery)
                    ->orWhere('io.identifier', 'LIKE', $likeQuery);
            });

        // Only published by default
        $baseQuery->where(function ($q) {
            $q->where('st.status_id', '=', self::PUBLICATION_STATUS_PUBLISHED_ID)
                ->orWhereNull('st.status_id');
        });

        $total = $baseQuery->count();

        $rows = (clone $baseQuery)
            ->select(
                'io.id',
                's.slug',
                'ioi.title',
                'io.identifier',
                'io.level_of_description_id',
                'o.updated_at'
            )
            ->orderBy('o.updated_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? '',
                'title' => $row->title ?? '',
                'identifier' => $row->identifier ?? '',
                'level' => $row->level_of_description_id ? (int) $row->level_of_description_id : null,
                'has_digital_object' => false,
                'created_at' => '',
                'updated_at' => $row->updated_at ?? '',
                '_score' => 0,
            ];
        }

        return ['hits' => $hits, 'total' => $total, 'facets' => []];
    }

    /**
     * Fallback: actor.
     */
    private static function searchFallbackActor(string $likeQuery, string $culture, int $limit, int $offset): array
    {
        $baseQuery = DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'a.id', '=', 's.object_id')
            ->where('a.id', '!=', 1) // Skip root
            ->where('ai.authorized_form_of_name', 'LIKE', $likeQuery);

        $total = $baseQuery->count();

        $rows = (clone $baseQuery)
            ->select('a.id', 's.slug', 'ai.authorized_form_of_name', 'a.entity_type_id')
            ->orderBy('ai.authorized_form_of_name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? '',
                'title' => $row->authorized_form_of_name ?? '',
                'entity_type_id' => $row->entity_type_id ? (int) $row->entity_type_id : null,
                '_score' => 0,
            ];
        }

        return ['hits' => $hits, 'total' => $total, 'facets' => []];
    }

    /**
     * Fallback: repository.
     */
    private static function searchFallbackRepository(string $likeQuery, string $culture, int $limit, int $offset): array
    {
        $baseQuery = DB::table('repository as r')
            ->join('actor as a', 'r.id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'r.id', '=', 's.object_id')
            ->where('r.id', '!=', 1) // Skip root
            ->where('ai.authorized_form_of_name', 'LIKE', $likeQuery);

        $total = $baseQuery->count();

        $rows = (clone $baseQuery)
            ->select('r.id', 's.slug', 'ai.authorized_form_of_name', 'r.identifier')
            ->orderBy('ai.authorized_form_of_name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? '',
                'title' => $row->authorized_form_of_name ?? '',
                'identifier' => $row->identifier ?? '',
                '_score' => 0,
            ];
        }

        return ['hits' => $hits, 'total' => $total, 'facets' => []];
    }

    /**
     * Fallback: term.
     */
    private static function searchFallbackTerm(string $likeQuery, string $culture, int $limit, int $offset): array
    {
        $baseQuery = DB::table('term as t')
            ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('ti.name', 'LIKE', $likeQuery);

        $total = $baseQuery->count();

        $rows = (clone $baseQuery)
            ->select('t.id', 's.slug', 'ti.name', 't.taxonomy_id')
            ->orderBy('ti.name', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? '',
                'title' => $row->name ?? '',
                'taxonomy_id' => $row->taxonomy_id ? (int) $row->taxonomy_id : null,
                '_score' => 0,
            ];
        }

        return ['hits' => $hits, 'total' => $total, 'facets' => []];
    }

    /**
     * Fallback: accession.
     */
    private static function searchFallbackAccession(string $likeQuery, string $culture, int $limit, int $offset): array
    {
        $baseQuery = DB::table('accession as acc')
            ->leftJoin('accession_i18n as acci', function ($join) use ($culture) {
                $join->on('acc.id', '=', 'acci.id')
                    ->where('acci.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'acc.id', '=', 's.object_id')
            ->where(function ($q) use ($likeQuery) {
                $q->where('acci.title', 'LIKE', $likeQuery)
                    ->orWhere('acc.identifier', 'LIKE', $likeQuery);
            });

        $total = $baseQuery->count();

        $rows = (clone $baseQuery)
            ->select('acc.id', 's.slug', 'acci.title', 'acc.identifier', 'acc.date')
            ->orderBy('acc.date', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? '',
                'title' => $row->title ?? '',
                'identifier' => $row->identifier ?? '',
                'date' => $row->date ?? '',
                '_score' => 0,
            ];
        }

        return ['hits' => $hits, 'total' => $total, 'facets' => []];
    }

    // ----------------------------------------------------------------
    // HTTP Layer
    // ----------------------------------------------------------------

    /**
     * Execute ES query via curl.
     *
     * Returns parsed JSON response or null if ES is unreachable/errored.
     */
    private static function esQuery(string $index, array $body): ?array
    {
        $url = self::baseUrl() . '/' . $index . '/_search';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $response || $httpCode >= 400) {
            error_log(sprintf(
                'SearchService: ES query failed — HTTP %d, index: %s',
                $httpCode,
                $index
            ));

            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Check if ES is available.
     */
    public static function isAvailable(): bool
    {
        $url = self::baseUrl();

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_NOBODY => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return false !== $response && 200 === $httpCode;
    }

    // ----------------------------------------------------------------
    // Response Parsing
    // ----------------------------------------------------------------

    /**
     * Parse ES response into standard result format.
     */
    private static function parseResponse(array $response, string $entityType, string $culture = 'en'): array
    {
        $hits = [];

        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $hits[] = self::normalizeHit($hit, $source, $entityType, $culture);
        }

        $facets = [];
        foreach ($response['aggregations'] ?? [] as $name => $agg) {
            $facets[$name] = [];
            foreach ($agg['buckets'] ?? [] as $bucket) {
                $facets[$name][$bucket['key']] = $bucket['doc_count'];
            }
        }

        return [
            'hits' => $hits,
            'total' => $response['hits']['total']['value'] ?? $response['hits']['total'] ?? 0,
            'facets' => $facets,
        ];
    }

    /**
     * Normalize a single ES hit into a standard array.
     */
    private static function normalizeHit(array $hit, array $source, string $entityType, string $culture): array
    {
        $id = (int) ($source['id'] ?? $hit['_id'] ?? 0);
        $slug = $source['slug'] ?? '';
        $score = $hit['_score'] ?? 0;

        if ('informationobject' === $entityType) {
            return [
                'id' => $id,
                'slug' => $slug,
                'title' => $source['i18n'][$culture]['title']
                    ?? $source['i18n']['en']['title']
                    ?? '',
                'identifier' => $source['identifier'] ?? '',
                'reference_code' => $source['referenceCode'] ?? '',
                'level' => isset($source['levelOfDescriptionId']) ? (int) $source['levelOfDescriptionId'] : null,
                'publication_status' => isset($source['publicationStatusId']) ? (int) $source['publicationStatusId'] : null,
                'has_digital_object' => $source['hasDigitalObject'] ?? false,
                'media_type_id' => $source['digitalObject']['mediaTypeId'] ?? null,
                'thumbnail_path' => $source['digitalObject']['thumbnailPath'] ?? null,
                'creator' => $source['creators'][0]['i18n'][$culture]['authorizedFormOfName'] ?? null,
                'repository_slug' => $source['repository']['slug'] ?? null,
                'start_date' => $source['startDateSort'] ?? null,
                'created_at' => $source['createdAt'] ?? '',
                'updated_at' => $source['updatedAt'] ?? '',
                '_score' => $score,
            ];
        }

        if ('actor' === $entityType) {
            return [
                'id' => $id,
                'slug' => $slug,
                'title' => $source['i18n'][$culture]['authorizedFormOfName']
                    ?? $source['i18n']['en']['authorizedFormOfName']
                    ?? '',
                'entity_type_id' => $source['entityTypeId'] ?? null,
                'has_digital_object' => $source['hasDigitalObject'] ?? false,
                'thumbnail_path' => $source['digitalObject']['thumbnailPath'] ?? null,
                'created_at' => $source['createdAt'] ?? '',
                'updated_at' => $source['updatedAt'] ?? '',
                '_score' => $score,
            ];
        }

        if ('repository' === $entityType) {
            return [
                'id' => $id,
                'slug' => $slug,
                'title' => $source['i18n'][$culture]['authorizedFormOfName']
                    ?? $source['i18n']['en']['authorizedFormOfName']
                    ?? '',
                'identifier' => $source['identifier'] ?? '',
                'logo_path' => $source['logoPath'] ?? null,
                'created_at' => $source['createdAt'] ?? '',
                'updated_at' => $source['updatedAt'] ?? '',
                '_score' => $score,
            ];
        }

        if ('term' === $entityType) {
            return [
                'id' => $id,
                'slug' => $slug,
                'title' => $source['i18n'][$culture]['name']
                    ?? $source['i18n']['en']['name']
                    ?? '',
                'taxonomy_id' => $source['taxonomyId'] ?? null,
                'number_of_descendants' => $source['numberOfDescendants'] ?? 0,
                'created_at' => $source['createdAt'] ?? '',
                'updated_at' => $source['updatedAt'] ?? '',
                '_score' => $score,
            ];
        }

        if ('accession' === $entityType) {
            return [
                'id' => $id,
                'slug' => $slug,
                'title' => $source['i18n'][$culture]['title']
                    ?? $source['i18n']['en']['title']
                    ?? '',
                'identifier' => $source['identifier'] ?? '',
                'date' => $source['date'] ?? '',
                'created_at' => $source['createdAt'] ?? '',
                'updated_at' => $source['updatedAt'] ?? '',
                '_score' => $score,
            ];
        }

        // Generic
        return [
            'id' => $id,
            'slug' => $slug,
            'title' => '',
            'created_at' => $source['createdAt'] ?? '',
            'updated_at' => $source['updatedAt'] ?? '',
            '_score' => $score,
        ];
    }

    /**
     * Parse autocomplete ES response.
     */
    private static function parseAutocompleteResponse(array $response, string $entityType, string $culture): array
    {
        $results = [];

        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $id = (int) ($source['id'] ?? $hit['_id'] ?? 0);
            $slug = $source['slug'] ?? '';

            if ('informationobject' === $entityType) {
                $results[] = [
                    'id' => $id,
                    'slug' => $slug,
                    'title' => $source['i18n'][$culture]['title']
                        ?? $source['i18n']['en']['title']
                        ?? '',
                    'identifier' => $source['identifier'] ?? '',
                    'level' => isset($source['levelOfDescriptionId']) ? (int) $source['levelOfDescriptionId'] : null,
                ];
            } elseif ('actor' === $entityType || 'repository' === $entityType) {
                $results[] = [
                    'id' => $id,
                    'slug' => $slug,
                    'title' => $source['i18n'][$culture]['authorizedFormOfName']
                        ?? $source['i18n']['en']['authorizedFormOfName']
                        ?? '',
                ];
            } elseif ('term' === $entityType) {
                $results[] = [
                    'id' => $id,
                    'slug' => $slug,
                    'title' => $source['i18n'][$culture]['name']
                        ?? $source['i18n']['en']['name']
                        ?? '',
                    'taxonomy_id' => $source['taxonomyId'] ?? null,
                ];
            } else {
                $results[] = [
                    'id' => $id,
                    'slug' => $slug,
                    'title' => '',
                ];
            }
        }

        return $results;
    }

    // ----------------------------------------------------------------
    // Utilities
    // ----------------------------------------------------------------

    /**
     * Reset cached config (for testing).
     */
    public static function reset(): void
    {
        self::$host = null;
        self::$port = null;
        self::$indexPrefix = null;
    }

    /**
     * Override configuration programmatically (for testing or standalone use).
     */
    public static function configure(string $host, int $port, string $indexPrefix): void
    {
        self::$host = $host;
        self::$port = $port;
        self::$indexPrefix = $indexPrefix;
    }

    /**
     * Get configured ES host info (for diagnostics).
     */
    public static function getConnectionInfo(): array
    {
        self::init();

        return [
            'host' => self::$host,
            'port' => self::$port,
            'index_prefix' => self::$indexPrefix,
            'base_url' => self::baseUrl(),
            'available' => self::isAvailable(),
        ];
    }
}
