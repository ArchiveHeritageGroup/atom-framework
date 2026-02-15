<?php

/**
 * QubitActor Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitActor.php.
 */

if (!class_exists('QubitActor', false)) {
    class QubitActor
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'actor';
        protected static string $i18nTableName = 'actor_i18n';

        public const ROOT_ID = 3;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }
    }
}
