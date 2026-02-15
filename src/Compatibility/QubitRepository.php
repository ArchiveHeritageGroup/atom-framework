<?php

/**
 * QubitRepository Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitRepository.php.
 */

if (!class_exists('QubitRepository', false)) {
    class QubitRepository
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'repository';
        protected static string $i18nTableName = 'repository_i18n';

        public const ROOT_ID = 6;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }
    }
}
