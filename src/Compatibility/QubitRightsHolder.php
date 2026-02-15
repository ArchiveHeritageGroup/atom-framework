<?php

/**
 * QubitRightsHolder Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * QubitRightsHolder inherits from QubitActor (rights_holder.id -> actor.id FK).
 */

if (!class_exists('QubitRightsHolder', false)) {
    class QubitRightsHolder
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'rights_holder';
        protected static string $i18nTableName = '';
    }
}
