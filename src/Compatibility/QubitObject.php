<?php

/**
 * QubitObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * The `object` table is the base table for all AtoM entities.
 */

if (!class_exists('QubitObject', false)) {
    class QubitObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'object';
        protected static string $i18nTableName = '';

        /**
         * Get the class name for an object ID.
         */
        public static function getClassName(int $id): ?string
        {
            return \Illuminate\Database\Capsule\Manager::table('object')
                ->where('id', $id)
                ->value('class_name');
        }
    }
}
