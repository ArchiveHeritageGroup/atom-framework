<?php

/**
 * QubitFeedback — Compatibility stub.
 *
 * Provides getById() for feedback record retrieval.
 * Used by ahgFeedbackPlugin and ahgThemeB5Plugin.
 */
if (!class_exists('QubitFeedback', false)) {
    class QubitFeedback
    {
        use QubitModelTrait;

        protected static string $tableName = 'feedback';

        public static function getById($id)
        {
            return self::findById($id);
        }
    }
}
