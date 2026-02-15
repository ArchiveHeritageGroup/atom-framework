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
     * @param int   $id
     * @param array $options  Optional. Keys: 'culture' => string
     * @return object|null    stdClass row or null
     */
    public static function getById($id, $options = [])
    {
        $culture = self::resolveCulture($options);
        $className = self::qubitClassName();

        if (EntityQueryService::isClassMapped($className)) {
            return EntityQueryService::findById((int) $id, $className, $culture);
        }

        // Fallback: direct table query
        $query = DB::table(static::$tableName)->where('id', $id);

        if (!empty(static::$i18nTableName)) {
            $query->leftJoin(static::$i18nTableName . ' as i', function ($join) use ($culture) {
                $join->on(static::$tableName . '.id', '=', 'i.id')
                    ->where('i.culture', '=', $culture);
            });
        }

        return $query->first();
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
}
