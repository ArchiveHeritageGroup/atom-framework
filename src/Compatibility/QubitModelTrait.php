<?php

namespace AtomFramework\Compatibility;

use AtomFramework\Services\EntityQueryService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Shared trait for Qubit model compatibility stubs.
 *
 * Provides read-only getById/getBySlug/getOne/getAll backed by
 * EntityQueryService and Laravel Query Builder.
 *
 * Each using class must define:
 *   protected static string $tableName      — e.g. 'information_object'
 *   protected static string $i18nTableName  — e.g. 'information_object_i18n' (or '' for none)
 */
trait QubitModelTrait
{
    /**
     * Load an entity by primary key.
     *
     * Delegates to EntityQueryService when the class is mapped there,
     * otherwise falls back to a direct table query.
     *
     * Returns an instance of the calling class so that instanceof checks
     * work correctly (e.g., `$obj instanceof QubitTaxonomy`).
     *
     * @param int   $id
     * @param array $options  Optional. Keys: 'culture' => string
     * @return static|null    Instance of calling class or null
     */
    public static function getById($id, $options = [])
    {
        $culture = self::resolveCulture($options);
        $className = self::qubitClassName();

        if (EntityQueryService::isClassMapped($className)) {
            $row = EntityQueryService::findById((int) $id, $className, $culture);
        } else {
            // Fallback: direct table query
            $query = DB::table(static::$tableName)->where(static::$tableName . '.id', $id);

            if (!empty(static::$i18nTableName)) {
                $query->leftJoin(static::$i18nTableName . ' as i', function ($join) use ($culture) {
                    $join->on(static::$tableName . '.id', '=', 'i.id')
                        ->where('i.culture', '=', $culture);
                });
            }

            $row = $query->first();
        }

        if (!$row) {
            return null;
        }

        return static::hydrate($row);
    }

    /**
     * Load an entity by slug.
     *
     * @param string $slug
     * @param array  $options  Optional. Keys: 'culture' => string
     * @return object|null
     */
    public static function getBySlug($slug, $options = [])
    {
        $culture = self::resolveCulture($options);
        $className = self::qubitClassName();

        if (EntityQueryService::isClassMapped($className)) {
            return EntityQueryService::findBySlug($slug, $culture);
        }

        // Fallback: slug table lookup + direct query
        $objectId = DB::table('slug')
            ->where('slug', $slug)
            ->value('object_id');

        if (null === $objectId) {
            return null;
        }

        return self::getById((int) $objectId, $options);
    }

    /**
     * Get a single row matching criteria.
     *
     * @param array $criteria  Column => value pairs
     * @return object|null
     */
    public static function getOne($criteria = [])
    {
        $query = DB::table(static::$tableName);

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->first();
    }

    /**
     * Get all rows matching criteria.
     *
     * @param array $criteria  Column => value pairs
     * @return \Illuminate\Support\Collection
     */
    public static function getAll($criteria = [])
    {
        $query = DB::table(static::$tableName);

        foreach ($criteria as $column => $value) {
            $query->where($column, $value);
        }

        return $query->get();
    }

    /**
     * Resolve culture from options, sfConfig, or default to 'en'.
     *
     * @param array $options
     * @return string
     */
    protected static function resolveCulture($options = []): string
    {
        if (!empty($options['culture'])) {
            return $options['culture'];
        }

        if (class_exists('sfContext', false) && \sfContext::hasInstance()) {
            try {
                return \sfContext::getInstance()->getUser()->getCulture();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        if (class_exists('sfConfig', false)) {
            $culture = \sfConfig::get('default_culture', '');
            if (!empty($culture)) {
                return $culture;
            }
        }

        return 'en';
    }

    /**
     * Derive the Qubit class name from the using class.
     *
     * @return string  e.g. 'QubitInformationObject'
     */
    private static function qubitClassName(): string
    {
        // The using class IS the Qubit class (e.g. QubitTerm, QubitActor)
        return static::class;
    }

    /**
     * Hydrate a database row into an instance of the calling class.
     *
     * Copies all stdClass properties onto a new instance so that
     * instanceof checks work correctly (e.g., `$obj instanceof QubitTaxonomy`).
     * Also resolves the `parent` property from the MPTT `parent_id` column
     * in the `object` table (Qubit entities inherit from object).
     *
     * @param object $row  stdClass from DB query
     * @return static
     */
    protected static function hydrate(object $row): static
    {
        $instance = new static();

        foreach ((array) $row as $key => $value) {
            $instance->$key = $value;
        }

        // Resolve parent from entity's parent_id column.
        // Templates check `isset($resource->parent)` to ensure it's not root.
        if (!isset($instance->parent) && isset($instance->id)) {
            // parent_id may already be in the row data from the join
            $parentId = $instance->parent_id ?? null;

            if (!$parentId) {
                // Try the entity's own table
                try {
                    $parentId = DB::table(static::$tableName)
                        ->where('id', $instance->id)
                        ->value('parent_id');
                } catch (\Throwable $e) {
                    // Table may not have parent_id column — skip
                }
            }

            if ($parentId) {
                $instance->parent = (object) ['id' => $parentId];
            }
        }

        return $instance;
    }

    /**
     * Get ancestors using MPTT (nested set) lft/rgt columns.
     *
     * Returns a fluent collection wrapper that supports andSelf() and orderBy().
     *
     * @return object  Collection-like object with andSelf(), orderBy(), get() methods
     */
    public function getAncestors()
    {
        $self = $this;
        $items = [];

        try {
            // MPTT: ancestors have lft < self.lft AND rgt > self.rgt
            // For terms, both lft/rgt are in the `term` table.
            if (isset($this->lft, $this->rgt)) {
                $lft = $this->lft;
                $rgt = $this->rgt;
            } else {
                // Look up lft/rgt from entity's table
                $row = DB::table(static::$tableName)
                    ->where('id', $this->id)
                    ->select('lft', 'rgt')
                    ->first();
                $lft = $row->lft ?? null;
                $rgt = $row->rgt ?? null;
            }

            if (null !== $lft && null !== $rgt) {
                $ancestors = DB::table(static::$tableName)
                    ->where('lft', '<', $lft)
                    ->where('rgt', '>', $rgt)
                    ->orderBy('lft')
                    ->get();

                foreach ($ancestors as $row) {
                    $items[] = static::hydrate($row);
                }
            }
        } catch (\Throwable $e) {
            // MPTT query failed — return empty collection
        }

        // Return fluent wrapper
        return new class($items, $self) {
            private array $items;
            private ?object $self;
            private string $orderCol = 'lft';
            private string $orderDir = 'asc';

            public function __construct(array $items, ?object $self)
            {
                $this->items = $items;
                $this->self = $self;
            }

            public function andSelf(): static
            {
                if ($this->self) {
                    $this->items[] = $this->self;
                }
                return $this;
            }

            public function orderBy(string $column, string $direction = 'asc'): static
            {
                usort($this->items, function ($a, $b) use ($column, $direction) {
                    $va = $a->$column ?? 0;
                    $vb = $b->$column ?? 0;
                    return 'desc' === $direction ? $vb <=> $va : $va <=> $vb;
                });
                return $this;
            }

            public function get(): array
            {
                return $this->items;
            }

            public function count(): int
            {
                return count($this->items);
            }

            public function __invoke(): array
            {
                return $this->items;
            }

            /** Support foreach iteration */
            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator($this->items);
            }
        };
    }

    /**
     * Get children using MPTT parent_id.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getChildren()
    {
        try {
            return DB::table(static::$tableName)
                ->where('parent_id', $this->id)
                ->orderBy('lft')
                ->get()
                ->map(fn($row) => static::hydrate($row));
        } catch (\Throwable $e) {
            return collect([]);
        }
    }

    /**
     * Get taxonomy (for terms).
     *
     * @return object|null
     */
    public function getTaxonomy()
    {
        if (!isset($this->taxonomy_id)) {
            return null;
        }

        try {
            if (class_exists('QubitTaxonomy', false)) {
                return \QubitTaxonomy::getById($this->taxonomy_id);
            }
            return DB::table('taxonomy')
                ->leftJoin('taxonomy_i18n', function ($join) {
                    $join->on('taxonomy.id', '=', 'taxonomy_i18n.id')
                        ->where('taxonomy_i18n.culture', '=', 'en');
                })
                ->where('taxonomy.id', $this->taxonomy_id)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // Properties that are Propel collection relationships — must return arrays, never null
    private static array $collectionProperties = [
        'digitalObjectsRelatedByobjectId',
    ];

    // Properties stored as serialized arrays in the property table
    private static array $propertyTableArrays = [
        'language', 'script',
    ];

    /**
     * Magic property access — resolves camelCase to snake_case columns,
     * Propel relationships, and serialized property table values.
     */
    public function __get(string $name)
    {
        // Collection relationship properties — query or return []
        if (in_array($name, self::$collectionProperties, true) || str_contains($name, 'RelatedBy')) {
            return $this->resolveCollectionProperty($name);
        }

        // Serialized arrays from property table (language, script)
        if (in_array($name, self::$propertyTableArrays, true)) {
            return $this->resolvePropertyTableArray($name);
        }

        // Convert camelCase to snake_case
        $snakeCase = strtolower(preg_replace('/([A-Z])/', '_$1', $name));
        if (isset($this->$snakeCase)) {
            return $this->$snakeCase;
        }

        // Special properties
        if ('taxonomy' === $name) {
            return $this->getTaxonomy();
        }
        if ('className' === $name) {
            return static::class;
        }

        return null;
    }

    public function __isset(string $name): bool
    {
        // Collection properties always "exist" (as empty arrays)
        if (in_array($name, self::$collectionProperties, true) || str_contains($name, 'RelatedBy')) {
            return true;
        }
        if (in_array($name, self::$propertyTableArrays, true)) {
            return true;
        }

        $snakeCase = strtolower(preg_replace('/([A-Z])/', '_$1', $name));
        if (isset($this->$snakeCase)) {
            return true;
        }
        if ('taxonomy' === $name || 'parent' === $name || 'className' === $name) {
            return true;
        }
        return false;
    }

    /**
     * Resolve a Propel collection relationship property to an array.
     */
    private function resolveCollectionProperty(string $name): array
    {
        if ('digitalObjectsRelatedByobjectId' === $name && isset($this->id)) {
            try {
                return DB::table('digital_object')
                    ->where('object_id', $this->id)
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }

    /**
     * Resolve a serialized array property from the property/property_i18n table.
     */
    private function resolvePropertyTableArray(string $name): array
    {
        if (!isset($this->id)) {
            return [];
        }
        try {
            $row = DB::table('property')
                ->leftJoin('property_i18n', 'property.id', '=', 'property_i18n.id')
                ->where('property.object_id', $this->id)
                ->where('property.name', $name)
                ->value('property_i18n.value');

            if ($row) {
                $unserialized = @unserialize($row);
                return is_array($unserialized) ? $unserialized : [];
            }
        } catch (\Throwable $e) {
            // property table might not exist
        }
        return [];
    }

    /**
     * Magic method caller — handles Propel-style getXxx() accessors
     * and i18n field getters with cultureFallback option.
     */
    public function __call(string $method, array $args)
    {
        // Propel getter: getPropertyName() → property_name
        if (str_starts_with($method, 'get')) {
            $property = lcfirst(substr($method, 3));
            // Convert camelCase to snake_case
            $snakeCase = strtolower(preg_replace('/([A-Z])/', '_$1', $property));

            if (isset($this->$property)) {
                return $this->$property;
            }
            if (isset($this->$snakeCase)) {
                return $this->$snakeCase;
            }

            // Special method routing
            if ('taxonomy' === $property) {
                return $this->getTaxonomy();
            }
            if ('ancestors' === $property) {
                return $this->getAncestors();
            }
            if ('children' === $property) {
                return $this->getChildren();
            }

            // Collection methods — return empty arrays
            if (in_array($method, [
                'getActorRelations', 'getOtherNames', 'getOccupations',
                'getSubjectAccessPoints', 'getPlaceAccessPoints',
                'getNotesByType', 'getProperties',
            ], true)) {
                return [];
            }

            // i18n field getter with cultureFallback — look up from i18n table
            if (!empty(static::$i18nTableName) && isset($this->id)) {
                $options = $args[0] ?? [];
                return $this->resolveI18nField($snakeCase, $options);
            }

            return null;
        }

        // getMaintainingRepository() — specific to actors
        if ('getMaintainingRepository' === $method) {
            return null;
        }

        return null;
    }

    /**
     * Resolve an i18n field value from the entity's i18n table.
     */
    private function resolveI18nField(string $column, array $options = []): ?string
    {
        if (empty(static::$i18nTableName) || !isset($this->id)) {
            return null;
        }

        try {
            $culture = self::resolveCulture($options);
            $value = DB::table(static::$i18nTableName)
                ->where('id', $this->id)
                ->where('culture', $culture)
                ->value($column);

            // cultureFallback: try 'en' if primary culture returned nothing
            if (null === $value && !empty($options['cultureFallback']) && 'en' !== $culture) {
                $value = DB::table(static::$i18nTableName)
                    ->where('id', $this->id)
                    ->where('culture', \AtomExtensions\Helpers\CultureHelper::getCulture())
                    ->value($column);
            }

            return $value;
        } catch (\Throwable $e) {
            // Column may not exist in this i18n table
            return null;
        }
    }
}
