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
    }
}
