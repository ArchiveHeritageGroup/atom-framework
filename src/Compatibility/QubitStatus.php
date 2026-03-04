<?php

/**
 * QubitStatus — Compatibility stub.
 *
 * Column constants for the status table.
 * Used by ahgSearchPlugin for publication status filtering.
 */
if (!class_exists('QubitStatus', false)) {
    class QubitStatus
    {
        public const ID = 'status.id';
        public const OBJECT_ID = 'status.object_id';
        public const TYPE_ID = 'status.type_id';
        public const STATUS_ID = 'status.status_id';
    }
}
