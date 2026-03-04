<?php

/**
 * QubitRequestToPublish — Compatibility stub.
 *
 * Column constants + getById() for request-to-publish records.
 * Used by ahgRequestToPublishPlugin and ahgDisplayPlugin.
 */
if (!class_exists('QubitRequestToPublish', false)) {
    class QubitRequestToPublish
    {
        use QubitModelTrait;

        protected static string $tableName = 'request_to_publish';

        public const ID = 'request_to_publish.id';
        public const PARENT_ID = 'request_to_publish.parent_id';
        public const RTP_TYPE_ID = 'request_to_publish.rtp_type_id';
        public const SOURCE_CULTURE = 'request_to_publish.source_culture';

        public static function getById($id)
        {
            return self::findById($id);
        }
    }
}
