<?php

/**
 * QubitAccession Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitAccession', false)) {
    class QubitAccession
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'accession';
        protected static string $i18nTableName = 'accession_i18n';
    }
}
