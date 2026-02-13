<?php

declare(strict_types=1);

namespace AtomFramework\Services\Pagination;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

/**
 * Standalone pagination service - replaces QubitPager + Propel Criteria.
 *
 * Builds paginated results from Laravel Query Builder and returns SimplePager
 * instances that are fully compatible with ahgThemeB5Plugin/_pager.php.
 *
 * Usage:
 *   $pager = PaginationService::paginate('actor', [
 *       'join' => ['actor_i18n' => ['actor.id', '=', 'actor_i18n.id']],
 *       'where' => [['actor_i18n.culture', '=', 'en']],
 *       'search' => ['actor_i18n.authorized_form_of_name' => $query],
 *       'orderBy' => ['object.updated_at' => 'desc'],
 *       'select' => ['actor.id', 'actor_i18n.authorized_form_of_name as name'],
 *       'slugJoin' => true,
 *   ], $page, $limit);
 */
class PaginationService
{
    /**
     * Paginate a query and return a SimplePager.
     *
     * @param string $table   Base table name
     * @param array  $options Query options:
     *                        - join: array of table => [col1, op, col2] or table => closure
     *                        - leftJoin: array of table => [col1, op, col2] or table => closure
     *                        - where: array of [column, operator, value] conditions
     *                        - whereRaw: array of raw WHERE strings with optional bindings
     *                        - search: array of column => search_term (LIKE %term%)
     *                        - orderBy: array of column => direction
     *                        - select: array of column expressions
     *                        - slugJoin: bool - join slug table and add slug to results
     *                        - groupBy: array of columns to group by
     * @param int    $page    Current page (1-based)
     * @param int    $limit   Items per page
     */
    public static function paginate(
        string $table,
        array $options = [],
        int $page = 1,
        int $limit = 30
    ): SimplePager {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        try {
            $query = DB::table($table);

            // Apply joins
            if (!empty($options['join'])) {
                foreach ($options['join'] as $joinTable => $condition) {
                    if ($condition instanceof \Closure) {
                        $query->join($joinTable, $condition);
                    } elseif (is_array($condition) && 3 === count($condition)) {
                        $query->join($joinTable, $condition[0], $condition[1], $condition[2]);
                    }
                }
            }

            // Apply left joins
            if (!empty($options['leftJoin'])) {
                foreach ($options['leftJoin'] as $joinTable => $condition) {
                    if ($condition instanceof \Closure) {
                        $query->leftJoin($joinTable, $condition);
                    } elseif (is_array($condition) && 3 === count($condition)) {
                        $query->leftJoin($joinTable, $condition[0], $condition[1], $condition[2]);
                    }
                }
            }

            // Join slug table if requested
            if (!empty($options['slugJoin'])) {
                $query->leftJoin('slug', "{$table}.id", '=', 'slug.object_id');
            }

            // Apply where conditions
            if (!empty($options['where'])) {
                foreach ($options['where'] as $condition) {
                    if (is_array($condition) && 3 === count($condition)) {
                        $query->where($condition[0], $condition[1], $condition[2]);
                    } elseif (is_array($condition) && 2 === count($condition)) {
                        $query->where($condition[0], '=', $condition[1]);
                    }
                }
            }

            // Apply raw WHERE clauses
            if (!empty($options['whereRaw'])) {
                foreach ($options['whereRaw'] as $raw) {
                    if (is_array($raw) && 2 === count($raw)) {
                        $query->whereRaw($raw[0], $raw[1]);
                    } elseif (is_string($raw)) {
                        $query->whereRaw($raw);
                    }
                }
            }

            // Apply LIKE search
            if (!empty($options['search'])) {
                $query->where(function (Builder $q) use ($options) {
                    $first = true;
                    foreach ($options['search'] as $column => $term) {
                        if (null === $term || '' === trim((string) $term)) {
                            continue;
                        }
                        $escapedTerm = '%' . trim((string) $term) . '%';
                        if ($first) {
                            $q->where($column, 'LIKE', $escapedTerm);
                            $first = false;
                        } else {
                            $q->orWhere($column, 'LIKE', $escapedTerm);
                        }
                    }
                });
            }

            // Apply group by
            if (!empty($options['groupBy'])) {
                foreach ($options['groupBy'] as $col) {
                    $query->groupBy($col);
                }
            }

            // Count total before pagination (clone to preserve the query)
            $countQuery = clone $query;
            if (!empty($options['groupBy'])) {
                // For grouped queries, wrap in a subquery to count distinct groups
                $total = DB::table(DB::raw("({$countQuery->toSql()}) as count_table"))
                    ->mergeBindings($countQuery)
                    ->count();
            } else {
                $total = $countQuery->count();
            }

            // Apply ordering
            if (!empty($options['orderBy'])) {
                foreach ($options['orderBy'] as $column => $direction) {
                    $query->orderBy($column, $direction);
                }
            }

            // Select columns
            if (!empty($options['select'])) {
                $selectCols = $options['select'];
                // Add slug to select if slugJoin is active and slug not already selected
                if (!empty($options['slugJoin'])) {
                    $hasSlug = false;
                    foreach ($selectCols as $col) {
                        if (false !== stripos((string) $col, 'slug')) {
                            $hasSlug = true;

                            break;
                        }
                    }
                    if (!$hasSlug) {
                        $selectCols[] = 'slug.slug';
                    }
                }
                $query->select($selectCols);
            } else {
                $selectCols = ["{$table}.*"];
                if (!empty($options['slugJoin'])) {
                    $selectCols[] = 'slug.slug';
                }
                $query->select($selectCols);
            }

            // Apply offset/limit for current page
            $skip = ($page - 1) * $limit;
            $rows = $query->skip($skip)->take($limit)->get();

            // Convert to arrays (templates access via $doc['slug'])
            $results = [];
            foreach ($rows as $row) {
                $results[] = (array) $row;
            }

            return new SimplePager($results, $total, $page, $limit);
        } catch (\Exception $e) {
            error_log('PaginationService::paginate error: ' . $e->getMessage());

            return new SimplePager([], 0, $page, $limit);
        }
    }

    /**
     * Convenience: paginate actors (donors, rights holders, repositories, etc.)
     *
     * Builds the standard actor browse query:
     *   JOIN object ON actor_table.id = object.id
     *   JOIN actor ON actor_table.id = actor.id
     *   JOIN actor_i18n ON actor.id = actor_i18n.id AND culture = $culture
     *   LEFT JOIN slug ON actor_table.id = slug.object_id
     *
     * @param string      $actorTable Actor sub-table (e.g., 'donor', 'rights_holder', 'repository')
     * @param string      $culture    i18n culture code (e.g., 'en')
     * @param string|null $search     Optional search string for authorized_form_of_name
     * @param string      $sort       Sort key: 'name_asc', 'name_desc', 'updated_at_desc',
     *                                'updated_at_asc', 'created_at_desc', 'identifier_asc', etc.
     *                                Also accepts legacy keys: 'alphabetic', 'lastUpdated', 'identifier'
     * @param int         $page       Current page (1-based)
     * @param int         $limit      Items per page
     */
    public static function paginateActors(
        string $actorTable,
        string $culture,
        ?string $search = null,
        string $sort = 'updated_at_desc',
        int $page = 1,
        int $limit = 30
    ): SimplePager {
        $sortMap = [
            'name' => 'actor_i18n.authorized_form_of_name',
            'updated_at' => 'object.updated_at',
            'created_at' => 'object.created_at',
            'identifier' => 'actor.description_identifier',
            // Legacy aliases used by existing browse actions
            'alphabetic' => 'actor_i18n.authorized_form_of_name',
            'lastUpdated' => 'object.updated_at',
        ];

        [$orderColumn, $orderDir] = self::parseSort($sort, $sortMap);

        $options = [
            'join' => [
                'object' => ["{$actorTable}.id", '=', 'object.id'],
                'actor' => ["{$actorTable}.id", '=', 'actor.id'],
                'actor_i18n' => function ($join) use ($actorTable, $culture) {
                    $join->on("{$actorTable}.id", '=', 'actor_i18n.id')
                        ->where('actor_i18n.culture', '=', $culture);
                },
            ],
            'leftJoin' => [
                'slug' => ["{$actorTable}.id", '=', 'slug.object_id'],
            ],
            'select' => [
                "{$actorTable}.id",
                'slug.slug',
                'actor_i18n.authorized_form_of_name as name',
                'actor.description_identifier as identifier',
                'object.created_at',
                'object.updated_at',
            ],
            'orderBy' => [$orderColumn => $orderDir],
        ];

        // Apply search filter
        if (null !== $search && '' !== trim($search)) {
            $options['search'] = [
                'actor_i18n.authorized_form_of_name' => $search,
            ];
        }

        return self::paginate($actorTable, $options, $page, $limit);
    }

    /**
     * Convenience: paginate information objects.
     *
     * Standard IO browse query with i18n join, optional parent/level filters.
     *
     * @param string      $culture  i18n culture code
     * @param string|null $search   Optional search string for title
     * @param int|null    $parentId Filter by parent information_object.id
     * @param int|null    $levelId  Filter by level_of_description_id
     * @param string      $sort     Sort key (e.g., 'title_asc', 'updated_at_desc', 'identifier_asc')
     * @param int         $page     Current page (1-based)
     * @param int         $limit    Items per page
     */
    public static function paginateInformationObjects(
        string $culture,
        ?string $search = null,
        ?int $parentId = null,
        ?int $levelId = null,
        string $sort = 'updated_at_desc',
        int $page = 1,
        int $limit = 30
    ): SimplePager {
        $sortMap = [
            'title' => 'information_object_i18n.title',
            'updated_at' => 'object.updated_at',
            'created_at' => 'object.created_at',
            'identifier' => 'information_object.identifier',
            // Legacy aliases
            'alphabetic' => 'information_object_i18n.title',
            'lastUpdated' => 'object.updated_at',
        ];

        [$orderColumn, $orderDir] = self::parseSort($sort, $sortMap);

        $options = [
            'join' => [
                'object' => ['information_object.id', '=', 'object.id'],
                'information_object_i18n' => function ($join) use ($culture) {
                    $join->on('information_object.id', '=', 'information_object_i18n.id')
                        ->where('information_object_i18n.culture', '=', $culture);
                },
            ],
            'leftJoin' => [
                'slug' => ['information_object.id', '=', 'slug.object_id'],
            ],
            'select' => [
                'information_object.id',
                'slug.slug',
                'information_object_i18n.title as title',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.parent_id',
                'information_object.publication_status_id',
                'object.created_at',
                'object.updated_at',
            ],
            'orderBy' => [$orderColumn => $orderDir],
            'where' => [],
        ];

        // Filter by parent
        if (null !== $parentId) {
            $options['where'][] = ['information_object.parent_id', '=', $parentId];
        }

        // Filter by level of description
        if (null !== $levelId) {
            $options['where'][] = ['information_object.level_of_description_id', '=', $levelId];
        }

        // Apply search filter
        if (null !== $search && '' !== trim($search)) {
            $options['search'] = [
                'information_object_i18n.title' => $search,
            ];
        }

        return self::paginate('information_object', $options, $page, $limit);
    }

    /**
     * Convenience: paginate physical objects.
     *
     * Physical object browse with i18n join.
     *
     * @param string      $culture i18n culture code
     * @param string|null $search  Optional search string (matches name or location)
     * @param string      $sort    Sort key (e.g., 'name_asc', 'name_desc', 'location_asc')
     * @param int         $page    Current page (1-based)
     * @param int         $limit   Items per page
     */
    public static function paginatePhysicalObjects(
        string $culture,
        ?string $search = null,
        string $sort = 'name_asc',
        int $page = 1,
        int $limit = 30
    ): SimplePager {
        $sortMap = [
            'name' => 'physical_object_i18n.name',
            'location' => 'physical_object_i18n.location',
            // Legacy aliases from StorageBrowseService
            'nameUp' => 'physical_object_i18n.name',
            'nameDown' => 'physical_object_i18n.name',
            'locationUp' => 'physical_object_i18n.location',
            'locationDown' => 'physical_object_i18n.location',
        ];

        // Handle legacy sort keys that encode direction
        $legacyDirMap = [
            'nameUp' => 'asc',
            'nameDown' => 'desc',
            'locationUp' => 'asc',
            'locationDown' => 'desc',
        ];

        if (isset($legacyDirMap[$sort])) {
            $orderColumn = $sortMap[$sort];
            $orderDir = $legacyDirMap[$sort];
        } else {
            [$orderColumn, $orderDir] = self::parseSort($sort, $sortMap);
        }

        $options = [
            'join' => [
                'physical_object_i18n' => function ($join) use ($culture) {
                    $join->on('physical_object.id', '=', 'physical_object_i18n.id')
                        ->where('physical_object_i18n.culture', '=', $culture);
                },
            ],
            'leftJoin' => [
                'slug' => ['physical_object.id', '=', 'slug.object_id'],
                'term_i18n as type_i18n' => function ($join) use ($culture) {
                    $join->on('physical_object.type_id', '=', 'type_i18n.id')
                        ->where('type_i18n.culture', '=', $culture);
                },
            ],
            'select' => [
                'physical_object.id',
                'slug.slug',
                'physical_object_i18n.name',
                'physical_object_i18n.location',
                'type_i18n.name as type_name',
            ],
            'orderBy' => [$orderColumn => $orderDir],
        ];

        // Apply search filter (match name OR location OR type)
        if (null !== $search && '' !== trim($search)) {
            $options['search'] = [
                'physical_object_i18n.name' => $search,
                'physical_object_i18n.location' => $search,
                'type_i18n.name' => $search,
            ];
        }

        return self::paginate('physical_object', $options, $page, $limit);
    }

    /**
     * Convenience: paginate terms within a taxonomy.
     *
     * @param int         $taxonomyId Taxonomy ID to browse
     * @param string      $culture    i18n culture code
     * @param string|null $search     Optional search string for term name
     * @param string      $sort       Sort key (e.g., 'name_asc', 'name_desc', 'updated_at_desc')
     * @param int         $page       Current page (1-based)
     * @param int         $limit      Items per page
     */
    public static function paginateTerms(
        int $taxonomyId,
        string $culture,
        ?string $search = null,
        string $sort = 'name_asc',
        int $page = 1,
        int $limit = 30
    ): SimplePager {
        $sortMap = [
            'name' => 'term_i18n.name',
            'updated_at' => 'object.updated_at',
            'created_at' => 'object.created_at',
            // Legacy aliases
            'alphabetic' => 'term_i18n.name',
            'lastUpdated' => 'object.updated_at',
        ];

        [$orderColumn, $orderDir] = self::parseSort($sort, $sortMap);

        $options = [
            'join' => [
                'object' => ['term.id', '=', 'object.id'],
                'term_i18n' => function ($join) use ($culture) {
                    $join->on('term.id', '=', 'term_i18n.id')
                        ->where('term_i18n.culture', '=', $culture);
                },
            ],
            'leftJoin' => [
                'slug' => ['term.id', '=', 'slug.object_id'],
            ],
            'where' => [
                ['term.taxonomy_id', '=', $taxonomyId],
            ],
            'select' => [
                'term.id',
                'slug.slug',
                'term_i18n.name',
                'term.taxonomy_id',
                'term.parent_id',
                'object.created_at',
                'object.updated_at',
            ],
            'orderBy' => [$orderColumn => $orderDir],
        ];

        // Apply search filter
        if (null !== $search && '' !== trim($search)) {
            $options['search'] = [
                'term_i18n.name' => $search,
            ];
        }

        return self::paginate('term', $options, $page, $limit);
    }

    /**
     * Parse a sort parameter string into [column, direction].
     *
     * Accepts formats:
     *   - 'column_direction' (e.g., 'updated_at_desc', 'name_asc')
     *   - Legacy keys (e.g., 'alphabetic', 'lastUpdated') resolved via $columnMap
     *
     * @param string $sort      Sort parameter string
     * @param array  $columnMap Map of sort key => database column
     *
     * @return array [column, direction] e.g., ['object.updated_at', 'desc']
     */
    private static function parseSort(string $sort, array $columnMap): array
    {
        // Check if the entire sort string is a legacy key
        if (isset($columnMap[$sort])) {
            // Determine default direction: time-based columns default to desc
            $column = $columnMap[$sort];
            $direction = (false !== strpos($column, '_at') || false !== strpos($column, 'updated') || false !== strpos($column, 'created'))
                ? 'desc'
                : 'asc';

            return [$column, $direction];
        }

        // Parse 'column_direction' format (e.g., 'name_asc', 'updated_at_desc')
        // Try splitting from the end to handle column names with underscores
        if (preg_match('/^(.+)_(asc|desc)$/i', $sort, $matches)) {
            $key = $matches[1];
            $direction = strtolower($matches[2]);

            if (isset($columnMap[$key])) {
                return [$columnMap[$key], $direction];
            }
        }

        // Fallback: use the first column in the map with asc
        $firstColumn = reset($columnMap);
        if (false !== $firstColumn) {
            return [$firstColumn, 'asc'];
        }

        // Ultimate fallback
        return ['id', 'asc'];
    }
}
