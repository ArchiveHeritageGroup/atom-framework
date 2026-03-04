<?php

/**
 * QubitGrantedRight — Compatibility stub.
 *
 * Column constants + checkPremis() for PREMIS rights checking.
 * Used by ahgDisplayPlugin digitalobject metadataComponent.
 */
if (!class_exists('QubitGrantedRight', false)) {
    class QubitGrantedRight
    {
        public const ID = 'granted_right.id';
        public const RIGHTS_ID = 'granted_right.rights_id';
        public const ACT_ID = 'granted_right.act_id';
        public const RESTRICTION = 'granted_right.restriction';
        public const START_DATE = 'granted_right.start_date';
        public const END_DATE = 'granted_right.end_date';

        /**
         * Check PREMIS rights for a resource.
         *
         * Stub returns true (allow access) — actual enforcement via embargo plugin.
         *
         * @param int    $objectId  Object ID
         * @param string $action    Action (e.g. 'readMaster')
         * @param object $user      User object
         *
         * @return bool
         */
        public static function checkPremis($objectId, $action = null, $user = null): bool
        {
            return true;
        }
    }
}
