<?php

/**
 * QubitProperty — Compatibility shim.
 *
 * Read-only stub for property entities with i18n support.
 * Properties store flexible metadata (language, script, etc.) on objects.
 */

if (!class_exists('QubitProperty', false)) {
    class QubitProperty
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'property';
        protected static string $i18nTableName = 'property_i18n';

        // Propel column constants
        public const ID = 'property.id';
        public const OBJECT_ID = 'property.object_id';
        public const NAME = 'property.name';
        public const SCOPE = 'property.scope';
        public const SOURCE_CULTURE = 'property.source_culture';

        /**
         * Get one property by object ID and name.
         *
         * @param  int    $objectId
         * @param  string $name
         * @param  array  $options
         *
         * @return static|null
         */
        public static function getOneByObjectIdAndName($objectId, $name, $options = [])
        {
            try {
                $row = \Illuminate\Database\Capsule\Manager::table('property')
                    ->where('object_id', $objectId)
                    ->where('name', $name)
                    ->first();

                if ($row) {
                    return static::hydrate($row);
                }
            } catch (\Exception $e) {
                // Table may not exist
            }

            return null;
        }

        /**
         * Get all properties for an object.
         *
         * @param  int    $objectId
         * @param  array  $options  ['scope' => string]
         *
         * @return array
         */
        public static function getByObjectId($objectId, $options = [])
        {
            try {
                $query = \Illuminate\Database\Capsule\Manager::table('property')
                    ->where('object_id', $objectId);

                if (isset($options['scope'])) {
                    $query->where('scope', $options['scope']);
                }

                $rows = $query->get();
                $results = [];

                foreach ($rows as $row) {
                    $results[] = static::hydrate($row);
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }

        /**
         * Add a property only if it doesn't already exist.
         *
         * @param  int    $objectId
         * @param  string $name
         * @param  string $value
         * @param  array  $options
         *
         * @return static|null
         */
        public static function addUnique($objectId, $name, $value, $options = [])
        {
            // Read-only in standalone mode — return existing or null
            return static::getOneByObjectIdAndName($objectId, $name, $options);
        }

        /**
         * Get the i18n value of this property.
         *
         * @param  array $options  ['culture' => string]
         *
         * @return string|null
         */
        public function getValue($options = [])
        {
            $culture = $options['culture'] ?? ($options['cultureFallback'] ?? true
                ? ($this->__get('source_culture') ?: 'en')
                : (class_exists('sfConfig', false) ? \sfConfig::get('sf_default_culture', 'en') : 'en'));

            try {
                $row = \Illuminate\Database\Capsule\Manager::table('property_i18n')
                    ->where('id', $this->__get('id'))
                    ->where('culture', $culture)
                    ->first();

                return $row->value ?? null;
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
