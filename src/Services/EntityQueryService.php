<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone entity query service -- replaces QubitObject::getBySlug(),
 * QubitQuery, and MPTT hierarchy traversal for standalone Heratio mode.
 *
 * All methods return plain objects (stdClass) or arrays, never Propel objects.
 * Compatible with template rendering via LightweightResource __get() magic.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class EntityQueryService
{
    // ----------------------------------------------------------------
    //  Class -> Table Mapping
    // ----------------------------------------------------------------

    private static array $classMap = [
        'QubitInformationObject' => [
            'table' => 'information_object',
            'i18n' => 'information_object_i18n',
            'i18n_fields' => [
                'title', 'alternate_title', 'edition', 'extent_and_medium',
                'archival_history', 'acquisition', 'scope_and_content',
                'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics',
                'finding_aids', 'location_of_originals', 'location_of_copies',
                'related_units_of_description', 'institution_responsible_identifier',
                'rules', 'sources', 'revision_history',
            ],
            'parent_field' => 'parent_id',
        ],
        'QubitActor' => [
            'table' => 'actor',
            'i18n' => 'actor_i18n',
            'i18n_fields' => [
                'authorized_form_of_name', 'dates_of_existence', 'history',
                'places', 'legal_status', 'functions', 'mandates',
                'internal_structures', 'general_context',
                'institution_responsible_identifier', 'rules', 'sources',
                'revision_history',
            ],
            'parent_field' => 'parent_id',
        ],
        'QubitRepository' => [
            'table' => 'repository',
            'i18n' => 'repository_i18n',
            'i18n_fields' => [
                'geocultural_context', 'collecting_policies', 'buildings',
                'holdings', 'finding_aids', 'opening_times', 'access_conditions',
                'disabled_access', 'research_services', 'reproduction_services',
                'public_facilities', 'desc_institution_identifier', 'desc_rules',
                'desc_sources', 'desc_revision_history',
            ],
            'parent_table' => 'actor',
        ],
        'QubitDonor' => [
            'table' => 'donor',
            'i18n' => null,
            'i18n_fields' => [],
            'parent_table' => 'actor',
        ],
        'QubitRightsHolder' => [
            'table' => 'rights_holder',
            'i18n' => null,
            'i18n_fields' => [],
            'parent_table' => 'actor',
        ],
        'QubitTerm' => [
            'table' => 'term',
            'i18n' => 'term_i18n',
            'i18n_fields' => ['name'],
            'parent_field' => 'parent_id',
        ],
        'QubitTaxonomy' => [
            'table' => 'taxonomy',
            'i18n' => 'taxonomy_i18n',
            'i18n_fields' => ['name', 'note'],
            'parent_field' => 'parent_id',
        ],
        'QubitStaticPage' => [
            'table' => 'static_page',
            'i18n' => 'static_page_i18n',
            'i18n_fields' => ['title', 'content'],
        ],
        'QubitPhysicalObject' => [
            'table' => 'physical_object',
            'i18n' => 'physical_object_i18n',
            'i18n_fields' => ['name', 'description', 'location'],
        ],
        'QubitAccession' => [
            'table' => 'accession',
            'i18n' => 'accession_i18n',
            'i18n_fields' => [
                'appraisal', 'archival_history', 'location_information',
                'physical_characteristics', 'processing_notes',
                'received_extent_units', 'scope_and_content',
                'source_of_acquisition', 'title',
            ],
        ],
        'QubitDigitalObject' => [
            'table' => 'digital_object',
            'i18n' => null,
            'i18n_fields' => [],
        ],
        'QubitEvent' => [
            'table' => 'event',
            'i18n' => 'event_i18n',
            'i18n_fields' => ['name', 'description', 'date'],
        ],
        'QubitOtherName' => [
            'table' => 'other_name',
            'i18n' => 'other_name_i18n',
            'i18n_fields' => ['name'],
        ],
        'QubitMenu' => [
            'table' => 'menu',
            'i18n' => 'menu_i18n',
            'i18n_fields' => ['label', 'description'],
        ],
        'QubitContactInformation' => [
            'table' => 'contact_information',
            'i18n' => 'contact_information_i18n',
            'i18n_fields' => ['contact_person', 'street_address', 'city', 'region', 'note'],
        ],
        'QubitRelation' => [
            'table' => 'relation',
            'i18n' => null,
            'i18n_fields' => [],
        ],
        'QubitObject' => [
            'table' => 'object',
            'i18n' => null,
            'i18n_fields' => [],
        ],
        'QubitObjectTermRelation' => [
            'table' => 'object_term_relation',
            'i18n' => null,
            'i18n_fields' => [],
        ],
    ];

    // ----------------------------------------------------------------
    //  Slug Resolution
    // ----------------------------------------------------------------

    /**
     * Resolve a slug to an object ID + class_name.
     *
     * @return array{id: int, class_name: string, slug: string}|null
     */
    public static function resolveSlug(string $slug): ?array
    {
        $row = DB::table('slug as s')
            ->join('object as o', 's.object_id', '=', 'o.id')
            ->where('s.slug', $slug)
            ->select('s.object_id as id', 'o.class_name', 's.slug')
            ->first();

        if (null === $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'class_name' => $row->class_name,
            'slug' => $row->slug,
        ];
    }

    /**
     * Get the slug for an object ID.
     */
    public static function getSlug(int $objectId): ?string
    {
        return DB::table('slug')
            ->where('object_id', $objectId)
            ->value('slug');
    }

    // ----------------------------------------------------------------
    //  Entity Loading
    // ----------------------------------------------------------------

    /**
     * Load an entity by ID with i18n data.
     *
     * Returns a stdClass with all table fields + i18n fields merged.
     * If className is not provided, it is looked up from the object table.
     */
    public static function findById(int $id, ?string $className = null, string $culture = 'en'): ?object
    {
        if (null === $className) {
            $className = DB::table('object')
                ->where('id', $id)
                ->value('class_name');

            if (null === $className) {
                return null;
            }
        }

        $map = self::$classMap[$className] ?? null;
        if (null === $map) {
            return null;
        }

        return self::loadEntity($id, $className, $map, $culture);
    }

    /**
     * Load an entity by slug with i18n data.
     */
    public static function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        $resolved = self::resolveSlug($slug);
        if (null === $resolved) {
            return null;
        }

        return self::findById($resolved['id'], $resolved['class_name'], $culture);
    }

    /**
     * Load multiple entities by IDs.
     *
     * @param int[] $ids
     * @return object[] Indexed by ID
     */
    public static function findByIds(array $ids, ?string $className = null, string $culture = 'en'): array
    {
        if (empty($ids)) {
            return [];
        }

        // If no className given, look up each ID's class and group them
        if (null === $className) {
            $objects = DB::table('object')
                ->whereIn('id', $ids)
                ->select('id', 'class_name')
                ->get();

            $grouped = [];
            foreach ($objects as $obj) {
                $grouped[$obj->class_name][] = (int) $obj->id;
            }

            $results = [];
            foreach ($grouped as $cls => $clsIds) {
                foreach ($clsIds as $clsId) {
                    $entity = self::findById($clsId, $cls, $culture);
                    if (null !== $entity) {
                        $results[$clsId] = $entity;
                    }
                }
            }

            return $results;
        }

        // All same className -- load individually (safe and consistent)
        $results = [];
        foreach ($ids as $id) {
            $entity = self::findById($id, $className, $culture);
            if (null !== $entity) {
                $results[$id] = $entity;
            }
        }

        return $results;
    }

    // ----------------------------------------------------------------
    //  MPTT Hierarchy (lft/rgt)
    // ----------------------------------------------------------------

    /**
     * Get all descendants of an object (using MPTT lft/rgt on the entity table).
     *
     * AtoM stores lft/rgt on information_object and term tables.
     *
     * @return object[]
     */
    public static function getDescendants(int $objectId, string $className, string $culture = 'en'): array
    {
        $map = self::$classMap[$className] ?? null;
        if (null === $map || !isset($map['parent_field'])) {
            return [];
        }

        $table = $map['table'];

        // Get the node's lft/rgt
        $node = DB::table($table)
            ->where('id', $objectId)
            ->select('lft', 'rgt')
            ->first();

        if (null === $node || null === $node->lft || null === $node->rgt) {
            return [];
        }

        $query = DB::table("{$table} as t")
            ->join('object as o', 't.id', '=', 'o.id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.lft', '>', $node->lft)
            ->where('t.rgt', '<', $node->rgt)
            ->orderBy('t.lft');

        // Add i18n join if available
        $selects = ['t.*', 'o.class_name', 'o.created_at', 'o.updated_at', 's.slug'];
        if (!empty($map['i18n'])) {
            $i18nTable = $map['i18n'];
            $query->leftJoin("{$i18nTable} as i", function ($join) use ($culture) {
                $join->on('t.id', '=', 'i.id')
                    ->where('i.culture', '=', $culture);
            });

            foreach ($map['i18n_fields'] as $field) {
                $selects[] = "i.{$field}";
            }
        }

        return $query->select($selects)->get()->all();
    }

    /**
     * Get direct children of an object.
     *
     * @return object[]
     */
    public static function getChildren(int $parentId, string $className, string $culture = 'en'): array
    {
        $map = self::$classMap[$className] ?? null;
        if (null === $map || !isset($map['parent_field'])) {
            return [];
        }

        $table = $map['table'];
        $parentField = $map['parent_field'];

        $query = DB::table("{$table} as t")
            ->join('object as o', 't.id', '=', 'o.id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where("t.{$parentField}", $parentId);

        $selects = ['t.*', 'o.class_name', 'o.created_at', 'o.updated_at', 's.slug'];
        if (!empty($map['i18n'])) {
            $i18nTable = $map['i18n'];
            $query->leftJoin("{$i18nTable} as i", function ($join) use ($culture) {
                $join->on('t.id', '=', 'i.id')
                    ->where('i.culture', '=', $culture);
            });

            foreach ($map['i18n_fields'] as $field) {
                $selects[] = "i.{$field}";
            }
        }

        // Order by lft if MPTT columns exist, otherwise by id
        if (self::tableHasColumn($table, 'lft')) {
            $query->orderBy('t.lft');
        } else {
            $query->orderBy('t.id');
        }

        return $query->select($selects)->get()->all();
    }

    /**
     * Get ancestors (path from root to this node) using MPTT.
     *
     * Returns ancestors ordered from root to immediate parent.
     *
     * @return object[]
     */
    public static function getAncestors(int $objectId, string $className, string $culture = 'en'): array
    {
        $map = self::$classMap[$className] ?? null;
        if (null === $map || !isset($map['parent_field'])) {
            return [];
        }

        $table = $map['table'];

        // Get the node's lft/rgt
        $node = DB::table($table)
            ->where('id', $objectId)
            ->select('lft', 'rgt')
            ->first();

        if (null === $node || null === $node->lft || null === $node->rgt) {
            return [];
        }

        $query = DB::table("{$table} as t")
            ->join('object as o', 't.id', '=', 'o.id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.lft', '<', $node->lft)
            ->where('t.rgt', '>', $node->rgt)
            ->where('t.id', '!=', $objectId)
            ->orderBy('t.lft');

        $selects = ['t.*', 'o.class_name', 'o.created_at', 'o.updated_at', 's.slug'];
        if (!empty($map['i18n'])) {
            $i18nTable = $map['i18n'];
            $query->leftJoin("{$i18nTable} as i", function ($join) use ($culture) {
                $join->on('t.id', '=', 'i.id')
                    ->where('i.culture', '=', $culture);
            });

            foreach ($map['i18n_fields'] as $field) {
                $selects[] = "i.{$field}";
            }
        }

        return $query->select($selects)->get()->all();
    }

    /**
     * Get the parent of an object.
     */
    public static function getParent(int $objectId, string $className, string $culture = 'en'): ?object
    {
        $map = self::$classMap[$className] ?? null;
        if (null === $map || !isset($map['parent_field'])) {
            return null;
        }

        $table = $map['table'];
        $parentField = $map['parent_field'];

        $parentId = DB::table($table)
            ->where('id', $objectId)
            ->value($parentField);

        if (null === $parentId) {
            return null;
        }

        return self::findById((int) $parentId, $className, $culture);
    }

    /**
     * Count descendants using MPTT.
     */
    public static function countDescendants(int $objectId, string $className): int
    {
        $map = self::$classMap[$className] ?? null;
        if (null === $map || !isset($map['parent_field'])) {
            return 0;
        }

        $table = $map['table'];

        $node = DB::table($table)
            ->where('id', $objectId)
            ->select('lft', 'rgt')
            ->first();

        if (null === $node || null === $node->lft || null === $node->rgt) {
            return 0;
        }

        // MPTT descendant count = (rgt - lft - 1) / 2
        return (int) (($node->rgt - $node->lft - 1) / 2);
    }

    // ----------------------------------------------------------------
    //  Relationships
    // ----------------------------------------------------------------

    /**
     * Get related objects via the relation table.
     *
     * relation table: id, subject_id, object_id, type_id, start_date, end_date, source_culture
     *
     * @return object[]
     */
    public static function getRelations(int $objectId, ?int $typeId = null, string $culture = 'en'): array
    {
        $query = DB::table('relation as r')
            ->join('object as o', 'r.id', '=', 'o.id')
            ->where(function ($q) use ($objectId) {
                $q->where('r.subject_id', $objectId)
                    ->orWhere('r.object_id', $objectId);
            });

        if (null !== $typeId) {
            $query->where('r.type_id', $typeId);
        }

        $relations = $query->select(
            'r.id',
            'r.subject_id',
            'r.object_id',
            'r.type_id',
            'r.start_date',
            'r.end_date',
            'r.source_culture',
            'o.class_name'
        )->get();

        // Enrich each relation with the related entity's basic info
        $results = [];
        foreach ($relations as $rel) {
            $relatedId = ((int) $rel->subject_id === $objectId)
                ? (int) $rel->object_id
                : (int) $rel->subject_id;

            $rel->related_id = $relatedId;
            $rel->related_slug = self::getSlug($relatedId);

            // Get the related entity's display name
            $relatedClass = DB::table('object')
                ->where('id', $relatedId)
                ->value('class_name');

            if (null !== $relatedClass) {
                $relatedEntity = self::findById($relatedId, $relatedClass, $culture);
                $rel->related_name = $relatedEntity->title
                    ?? $relatedEntity->authorized_form_of_name
                    ?? $relatedEntity->name
                    ?? $rel->related_slug
                    ?? '';
                $rel->related_class_name = $relatedClass;
            }

            $results[] = $rel;
        }

        return $results;
    }

    /**
     * Get digital objects for an information object.
     *
     * @return object[]
     */
    public static function getDigitalObjects(int $informationObjectId): array
    {
        return DB::table('digital_object as d')
            ->join('object as o', 'd.id', '=', 'o.id')
            ->where('d.object_id', $informationObjectId)
            ->select(
                'd.id',
                'd.object_id',
                'd.usage_id',
                'd.language',
                'd.mime_type',
                'd.media_type_id',
                'd.name',
                'd.path',
                'd.sequence',
                'd.byte_size',
                'd.checksum',
                'd.checksum_type',
                'd.parent_id',
                'o.class_name',
                'o.created_at',
                'o.updated_at'
            )
            ->orderBy('d.sequence')
            ->orderBy('d.id')
            ->get()
            ->all();
    }

    /**
     * Get events for an information object (dates, creation events, etc.).
     *
     * event table: id, start_date, start_time, end_date, end_time,
     *              type_id, object_id (FK to information_object), actor_id, source_culture
     * event_i18n: id, culture, name, description, date
     *
     * @return object[]
     */
    public static function getEvents(int $informationObjectId, string $culture = 'en'): array
    {
        return DB::table('event as e')
            ->join('object as o', 'e.id', '=', 'o.id')
            ->leftJoin('event_i18n as ei', function ($join) use ($culture) {
                $join->on('e.id', '=', 'ei.id')
                    ->where('ei.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('e.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
                $join->on('e.type_id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->where('e.object_id', $informationObjectId)
            ->select(
                'e.id',
                'e.object_id',
                'e.actor_id',
                'e.type_id',
                'e.start_date',
                'e.start_time',
                'e.end_date',
                'e.end_time',
                'e.source_culture',
                'ei.name as event_name',
                'ei.description as event_description',
                'ei.date as event_date_display',
                'ai.authorized_form_of_name as actor_name',
                'ti.name as event_type_name'
            )
            ->orderBy('e.start_date')
            ->orderBy('e.id')
            ->get()
            ->all();
    }

    /**
     * Get properties for an object.
     *
     * property table: id, object_id, scope, name, source_culture
     * property_i18n: id, culture, value
     *
     * @return object[]
     */
    public static function getProperties(int $objectId, ?string $name = null, string $culture = 'en'): array
    {
        $query = DB::table('property as p')
            ->leftJoin('property_i18n as pi', function ($join) use ($culture) {
                $join->on('p.id', '=', 'pi.id')
                    ->where('pi.culture', '=', $culture);
            })
            ->where('p.object_id', $objectId);

        if (null !== $name) {
            $query->where('p.name', $name);
        }

        return $query->select(
            'p.id',
            'p.object_id',
            'p.scope',
            'p.name',
            'p.source_culture',
            'pi.value',
            'pi.culture'
        )
            ->orderBy('p.name')
            ->orderBy('p.id')
            ->get()
            ->all();
    }

    /**
     * Get notes for an object.
     *
     * note table: id, object_id, type_id, scope, user_id, source_culture
     * note_i18n: id, culture, content
     *
     * @return object[]
     */
    public static function getNotes(int $objectId, ?int $typeId = null, string $culture = 'en'): array
    {
        $query = DB::table('note as n')
            ->leftJoin('note_i18n as ni', function ($join) use ($culture) {
                $join->on('n.id', '=', 'ni.id')
                    ->where('ni.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($join) use ($culture) {
                $join->on('n.type_id', '=', 'ti.id')
                    ->where('ti.culture', '=', $culture);
            })
            ->where('n.object_id', $objectId);

        if (null !== $typeId) {
            $query->where('n.type_id', $typeId);
        }

        return $query->select(
            'n.id',
            'n.object_id',
            'n.type_id',
            'n.scope',
            'n.user_id',
            'n.source_culture',
            'ni.content',
            'ni.culture',
            'ti.name as note_type_name'
        )
            ->orderBy('n.type_id')
            ->orderBy('n.id')
            ->get()
            ->all();
    }

    // ----------------------------------------------------------------
    //  Lightweight Resource Builder
    // ----------------------------------------------------------------

    /**
     * Build a lightweight resource object that works in templates.
     *
     * Supports __get(), __isset(), __toString() for template compatibility.
     * This replaces ActionBridge's buildLightweightResource with a more
     * complete implementation.
     */
    public static function buildResource(int $id, ?string $className = null, string $culture = 'en'): ?LightweightResource
    {
        $entity = self::findById($id, $className, $culture);
        if (null === $entity) {
            return null;
        }

        return new LightweightResource($entity);
    }

    /**
     * Build a lightweight resource from a slug.
     */
    public static function buildResourceBySlug(string $slug, string $culture = 'en'): ?LightweightResource
    {
        $entity = self::findBySlug($slug, $culture);
        if (null === $entity) {
            return null;
        }

        return new LightweightResource($entity);
    }

    // ----------------------------------------------------------------
    //  Class Map Utilities
    // ----------------------------------------------------------------

    /**
     * Get the table name for a class.
     */
    public static function getTableForClass(string $className): ?string
    {
        return self::$classMap[$className]['table'] ?? null;
    }

    /**
     * Get the class name for a table.
     */
    public static function getClassForTable(string $table): ?string
    {
        foreach (self::$classMap as $className => $map) {
            if ($map['table'] === $table) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Check if a class is mapped.
     */
    public static function isClassMapped(string $className): bool
    {
        return isset(self::$classMap[$className]);
    }

    // ----------------------------------------------------------------
    //  Internal Helpers
    // ----------------------------------------------------------------

    /**
     * Load a single entity by ID with all joins.
     */
    private static function loadEntity(int $id, string $className, array $map, string $culture): ?object
    {
        $table = $map['table'];
        $parentTable = $map['parent_table'] ?? null;

        // Base query: entity table + object table + slug
        $query = DB::table("{$table} as t")
            ->join('object as o', 't.id', '=', 'o.id')
            ->leftJoin('slug as s', 't.id', '=', 's.object_id')
            ->where('t.id', $id);

        $selects = ['t.*', 'o.class_name', 'o.created_at', 'o.updated_at', 's.slug'];

        // For entities that inherit from actor (donor, rights_holder, repository)
        if (null !== $parentTable && 'actor' === $parentTable) {
            $query->join('actor as a', 't.id', '=', 'a.id');
            $selects[] = 'a.corporate_body_identifiers';
            $selects[] = 'a.entity_type_id';
            $selects[] = 'a.description_status_id as actor_desc_status_id';
            $selects[] = 'a.description_detail_id as actor_desc_detail_id';
            $selects[] = 'a.description_identifier as actor_desc_identifier';
            $selects[] = 'a.source_standard as actor_source_standard';
            $selects[] = 'a.parent_id as actor_parent_id';
            $selects[] = 'a.source_culture as actor_source_culture';

            // actor_i18n for name resolution
            $query->leftJoin('actor_i18n as ai', function ($join) use ($culture) {
                $join->on('t.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $culture);
            });

            $selects[] = 'ai.authorized_form_of_name';
            $selects[] = 'ai.dates_of_existence';
            $selects[] = 'ai.history';
            $selects[] = 'ai.places';
            $selects[] = 'ai.legal_status';
            $selects[] = 'ai.functions';
            $selects[] = 'ai.mandates';
            $selects[] = 'ai.internal_structures';
            $selects[] = 'ai.general_context';
            $selects[] = 'ai.institution_responsible_identifier as actor_institution_identifier';
            $selects[] = 'ai.rules as actor_rules';
            $selects[] = 'ai.sources as actor_sources';
            $selects[] = 'ai.revision_history as actor_revision_history';

            // For repository, also join repository_i18n
            if ('repository' === $table) {
                $query->leftJoin('repository_i18n as ri', function ($join) use ($culture) {
                    $join->on('t.id', '=', 'ri.id')
                        ->where('ri.culture', '=', $culture);
                });

                foreach (self::$classMap['QubitRepository']['i18n_fields'] as $field) {
                    $selects[] = "ri.{$field}";
                }
            }
        } elseif (!empty($map['i18n'])) {
            // Standard i18n join for non-inherited entities
            $i18nTable = $map['i18n'];
            $query->leftJoin("{$i18nTable} as i", function ($join) use ($culture) {
                $join->on('t.id', '=', 'i.id')
                    ->where('i.culture', '=', $culture);
            });

            foreach ($map['i18n_fields'] as $field) {
                $selects[] = "i.{$field}";
            }
        }

        return $query->select($selects)->first();
    }

    /**
     * Check if a table has a given column.
     *
     * Caches results per table to avoid repeated schema queries.
     */
    private static function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = "{$table}.{$column}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $result = DB::select("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
            $cache[$key] = !empty($result);
        } catch (\Exception $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}
