<?php

/**
 * QubitDescription — Compatibility stub.
 *
 * Provides addAssets() for adding JS/CSS to edit pages.
 * Used by ahgAccessionManagePlugin and ahgDAMPlugin edit actions.
 */
if (!class_exists('QubitDescription', false)) {
    class QubitDescription
    {
        /**
         * Add description-related assets to the response.
         *
         * In standalone mode, assets are loaded via Blade layout.
         *
         * @param object $response sfWebResponse or adapter
         */
        public static function addAssets($response): void
        {
            // No-op in standalone — assets handled by Blade layout
        }
    }
}
