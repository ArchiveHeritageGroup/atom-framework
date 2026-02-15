<?php

/**
 * QubitOtherName Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitOtherName', false)) {
    class QubitOtherName
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'other_name';
        protected static string $i18nTableName = 'other_name_i18n';
    }
}
