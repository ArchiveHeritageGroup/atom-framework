<?php

/**
 * QubitAuditObject — Compatibility stub.
 *
 * Provides column constants for the audit_object table (base AtoM arAuditPlugin).
 * Used by ahgReportsPlugin reportUserAction for audit trail queries.
 *
 * Note: The audit_object table may not exist — ahgAuditTrailPlugin uses audit_log instead.
 * These constants allow Criteria queries to build SQL even if the table is absent.
 */
if (!class_exists('QubitAuditObject', false)) {
    class QubitAuditObject
    {
        public const ID = 'audit_object.id';
        public const ACTION = 'audit_object.action';
        public const DB_TABLE = 'audit_object.db_table';
        public const USER = 'audit_object.user';
        public const RECORD_ID = 'audit_object.record_id';
        public const CREATED_AT = 'audit_object.created_at';
        public const UPDATED_AT = 'audit_object.updated_at';
    }
}
