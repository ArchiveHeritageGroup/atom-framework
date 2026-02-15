<?php

/**
 * QubitContactInformation Compatibility Layer.
 *
 * Read-only stub for standalone Heratio mode.
 */

if (!class_exists('QubitContactInformation', false)) {
    class QubitContactInformation
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'contact_information';
        protected static string $i18nTableName = 'contact_information_i18n';

        /**
         * Get contact information for an actor.
         *
         * @param int $actorId
         * @return array
         */
        public static function getByActorId($actorId)
        {
            return \Illuminate\Database\Capsule\Manager::table('contact_information')
                ->where('actor_id', $actorId)
                ->get()
                ->all();
        }
    }
}
