<?php

/**
 * QubitDonor Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 * QubitDonor inherits from QubitActor (donor.id -> actor.id FK).
 */

if (!class_exists('QubitDonor', false)) {
    class QubitDonor
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'donor';
        protected static string $i18nTableName = '';
    }
}
