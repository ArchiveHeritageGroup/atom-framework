<?php

/**
 * QubitInformationObject Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitInformationObject.php.
 */

if (!class_exists('QubitInformationObject', false)) {
    class QubitInformationObject
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'information_object';
        protected static string $i18nTableName = 'information_object_i18n';

        public const ROOT_ID = 1;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }
    }
}
