<?php

/**
 * QubitPhysicalObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitPhysicalObject', false)) {
    class QubitPhysicalObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'physical_object';
        protected static string $i18nTableName = 'physical_object_i18n';

        // Propel column constants
        public const ID = 'physical_object.id';
        public const TYPE_ID = 'physical_object.type_id';
        public const PARENT_ID = 'physical_object.parent_id';
        public const LFT = 'physical_object.lft';
        public const RGT = 'physical_object.rgt';
        public const SOURCE_CULTURE = 'physical_object.source_culture';
    }
}
