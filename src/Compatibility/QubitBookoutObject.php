<?php

/**
 * QubitBookoutObject — Compatibility stub.
 *
 * Column constants for bookout_object table (used by request-to-publish receipts).
 * Table may not exist on all instances.
 */
if (!class_exists('QubitBookoutObject', false)) {
    class QubitBookoutObject
    {
        public const ID = 'bookout_object.id';
    }
}

if (!class_exists('QubitBookoutObjectI18n', false)) {
    class QubitBookoutObjectI18n
    {
        public const ID = 'bookout_object_i18n.id';
        public const CULTURE = 'bookout_object_i18n.culture';
    }
}
