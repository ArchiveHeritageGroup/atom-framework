<?php

/**
 * QubitClipboardSaveItem — Compatibility shim.
 *
 * Read-only stub for individual items in a saved clipboard.
 */

if (!class_exists('QubitClipboardSaveItem', false)) {
    class QubitClipboardSaveItem
    {
        use \AtomFramework\Compatibility\QubitModelTrait;

        protected static string $tableName = 'clipboard_save_item';
        protected static string $i18nTableName = '';

        // Propel column constants
        public const ID = 'clipboard_save_item.id';
        public const SAVE_ID = 'clipboard_save_item.save_id';
        public const ITEM_CLASS_NAME = 'clipboard_save_item.item_class_name';
        public const SLUG = 'clipboard_save_item.slug';

        /**
         * Get items for a specific clipboard save.
         *
         * @param  int $saveId
         *
         * @return array
         */
        public static function getBySaveId($saveId)
        {
            try {
                $rows = \Illuminate\Database\Capsule\Manager::table('clipboard_save_item')
                    ->where('save_id', $saveId)
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
         * Resolve this clipboard item to its actual object.
         *
         * @return object|null
         */
        public function getObject()
        {
            $className = $this->__get('item_class_name');
            $slug = $this->__get('slug');

            if ($className && $slug && class_exists($className, false)) {
                return $className::getBySlug($slug);
            }

            return null;
        }
    }
}
