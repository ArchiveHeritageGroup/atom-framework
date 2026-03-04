<?php

/**
 * QubitAuditLog — Compatibility stub.
 *
 * Column constants for the audit_log table.
 * Used by ahgSearchPlugin descriptionUpdatesAction.
 */
if (!class_exists('QubitAuditLog', false)) {
    class QubitAuditLog
    {
        public const ID = 'audit_log.id';
        public const TABLE_NAME = 'audit_log.table_name';
        public const RECORD_ID = 'audit_log.record_id';
        public const ACTION = 'audit_log.action';
        public const OBJECT_ID = 'audit_log.record_id';
        public const ACTION_TYPE_ID = 'audit_log.action';
        public const FIELD_NAME = 'audit_log.field_name';
        public const OLD_VALUE = 'audit_log.old_value';
        public const NEW_VALUE = 'audit_log.new_value';
        public const USER_ID = 'audit_log.user_id';
        public const USERNAME = 'audit_log.username';
        public const CREATED_AT = 'audit_log.created_at';
    }
}
