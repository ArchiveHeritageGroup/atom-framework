<?php

/**
 * QubitClipboardSave — Compatibility shim.
 *
 * Read-only stub for clipboard save (named clipboard collections).
 */

if (!class_exists('QubitClipboardSave', false)) {
    class QubitClipboardSave
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'clipboard_save';
        protected static string $i18nTableName = '';

        // Propel column constants
        public const ID = 'clipboard_save.id';
        public const USER_ID = 'clipboard_save.user_id';
        public const PASSWORD = 'clipboard_save.password';
        public const CREATED_AT = 'clipboard_save.created_at';

        /**
         * Get clipboard saves for a user.
         *
         * @param  int $userId
         *
         * @return array
         */
        public static function getByUserId($userId)
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('clipboard_save')
                    ->where('user_id', $userId)
                    ->get();

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
         * Get items in this clipboard save.
         *
         * @return array
         */
        public function getItems()
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('clipboard_save_item')
                    ->where('save_id', $this->__get('id'))
                    ->get();

                $results = [];
                foreach ($rows as $row) {
                    if (class_exists('QubitClipboardSaveItem', false)) {
                        $results[] = \QubitClipboardSaveItem::hydrate($row);
                    } else {
                        $results[] = $row;
                    }
                }

                return $results;
            } catch (\Exception $e) {
                return [];
            }
        }
    }
}
