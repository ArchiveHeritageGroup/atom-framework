<?php

/**
 * QubitFindingAidGenerator — Compatibility stub.
 *
 * Provides generatePath() for finding aid file path resolution.
 * Used by ahgDisplayPlugin and ahgLibraryPlugin renameAction.
 */
if (!class_exists('QubitFindingAidGenerator', false)) {
    class QubitFindingAidGenerator
    {
        /**
         * Generate the file path for a finding aid.
         *
         * @param object $resource Information object
         *
         * @return string Path relative to uploads/
         */
        public static function generatePath($resource): string
        {
            $slug = is_object($resource) ? ($resource->slug ?? '') : '';

            return 'downloads/' . $slug . '.pdf';
        }
    }
}
