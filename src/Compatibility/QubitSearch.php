<?php

/**
 * QubitSearch — Compatibility stub.
 *
 * Provides getInstance() for Elasticsearch integration.
 * In standalone mode, returns a no-op stub. Actual search
 * is handled by the framework's SearchService.
 */
if (!class_exists('QubitSearch', false)) {
    class QubitSearch
    {
        private static $instance;
        private static bool $enabled = true;

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Disable search indexing (used during bulk operations).
         */
        public static function disable(): void
        {
            self::$enabled = false;
        }

        /**
         * Enable search indexing.
         */
        public static function enable(): void
        {
            self::$enabled = true;
        }

        /**
         * Check if search is enabled.
         */
        public static function isEnabled(): bool
        {
            return self::$enabled;
        }

        /**
         * Update a document in the search index (no-op in standalone).
         */
        public function update($resource): void
        {
            // No-op — Elasticsearch updates handled by search:populate CLI
        }

        /**
         * Delete a document from the search index (no-op in standalone).
         */
        public function delete($resource): void
        {
            // No-op
        }

        /**
         * Placeholder index accessor.
         */
        public function __get($name)
        {
            return $this;
        }

        /**
         * Placeholder query method — returns empty result set.
         */
        public function __call($name, $args)
        {
            return $this;
        }
    }
}
