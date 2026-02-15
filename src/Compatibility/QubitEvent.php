<?php

/**
 * QubitEvent Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitEvent', false)) {
    class QubitEvent
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'event';
        protected static string $i18nTableName = 'event_i18n';
    }
}
