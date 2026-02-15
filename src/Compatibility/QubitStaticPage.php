<?php

/**
 * QubitStaticPage Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitStaticPage', false)) {
    class QubitStaticPage
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'static_page';
        protected static string $i18nTableName = 'static_page_i18n';
    }
}
