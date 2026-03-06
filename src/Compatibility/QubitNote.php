<?php

/**
 * QubitNote — Compatibility shim.
 *
 * Read-only stub for note entities with i18n support.
 * Notes are attached to objects (information objects, actors, etc.).
 */

if (!class_exists('QubitNote', false)) {
    class QubitNote
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'note';
        protected static string $i18nTableName = 'note_i18n';

        // Propel column constants
        public const ID = 'note.id';
        public const OBJECT_ID = 'note.object_id';
        public const TYPE_ID = 'note.type_id';
        public const USER_ID = 'note.user_id';
        public const SCOPE = 'note.scope';
        public const SOURCE_CULTURE = 'note.source_culture';

        // Note types (taxonomy term IDs from QubitTerm)
        // These constants match vendor/symfony/lib/model/QubitNote.php
        public const GENERAL_NOTE_ID = 268;
        public const TITLE_NOTE_ID = 148;
        public const PUBLICATION_NOTE_ID = 147;
        public const ARCHIVISTS_NOTE_ID = 174;

        /**
         * Get notes for a specific object and type.
         *
         * @param  int       $objectId
         * @param  array     $options  ['typeId' => int, 'culture' => string]
         *
         * @return array
         */
        public static function getByObjectId($objectId, $options = [])
        {
            try {
                $query = \Illuminate\Database\Capsule\Manager::table('note')
                    ->where('object_id', $objectId);

                if (isset($options['typeId'])) {
                    $query->where('type_id', $options['typeId']);
                }

                $rows = $query->get();
                $results = [];

                foreach ($rows as $row) {
                    $results[] = static::hydrate($row);
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }

        /**
         * Get the note type term.
         *
         * @return QubitTerm|null
         */
        public function getType()
        {
            $typeId = $this->__get('type_id');
            if ($typeId && class_exists('QubitTerm', false)) {
                return \QubitTerm::getById($typeId);
            }

            return null;
        }

        /**
         * Get the associated object.
         *
         * @return object|null
         */
        public function getObject()
        {
            $objectId = $this->__get('object_id');
            if ($objectId && class_exists('QubitObject', false)) {
                return \QubitObject::getById($objectId);
            }

            return null;
        }

        /**
         * Get the user who created this note.
         *
         * @return QubitUser|null
         */
        public function getUser()
        {
            $userId = $this->__get('user_id');
            if ($userId && class_exists('QubitUser', false)) {
                return \QubitUser::getById($userId);
            }

            return null;
        }
    }
}
