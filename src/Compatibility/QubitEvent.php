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

        // Propel column constants
        public const ID = 'event.id';
        public const OBJECT_ID = 'event.object_id';
        public const ACTOR_ID = 'event.actor_id';
        public const TYPE_ID = 'event.type_id';
        public const START_DATE = 'event.start_date';
        public const END_DATE = 'event.end_date';
        public const SOURCE_CULTURE = 'event.source_culture';
    }
}
