<?php

/**
 * QubitMenu Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * Constants sourced from lib/model/QubitMenu.php.
 */

if (!class_exists('QubitMenu', false)) {
    class QubitMenu
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'menu';
        protected static string $i18nTableName = 'menu_i18n';

        public const ROOT_ID = 1;
        public const MAIN_MENU_ID = 2;
        public const QUICK_LINKS_ID = 3;
        public const BROWSE_ID = 4;
        public const ADD_EDIT_ID = 5;
        public const TAXONOMY_ID = 6;
        public const IMPORT_ID = 7;
        public const TRANSLATE_ID = 8;
        public const ADMIN_ID = 9;
        public const MANAGE_ID = 10;

        public static function getRoot()
        {
            return self::getById(self::ROOT_ID);
        }
    }
}
