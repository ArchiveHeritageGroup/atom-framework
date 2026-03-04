<?php

/**
 * QubitAclPermission — Compatibility stub.
 *
 * Column constants for the acl_permission table.
 * Used by ahgReportsPlugin for user permission queries.
 */
if (!class_exists('QubitAclPermission', false)) {
    class QubitAclPermission
    {
        public const ID = 'acl_permission.id';
        public const USER_ID = 'acl_permission.user_id';
        public const GROUP_ID = 'acl_permission.group_id';
        public const OBJECT_ID = 'acl_permission.object_id';
        public const ACTION = 'acl_permission.action';
        public const GRANT_DENY = 'acl_permission.grant_deny';
    }
}
