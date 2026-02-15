<?php

/**
 * QubitDigitalObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitDigitalObject', false)) {
    class QubitDigitalObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'digital_object';
        protected static string $i18nTableName = '';
    }
}
