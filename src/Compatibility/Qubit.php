<?php
// Dont define if were in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}
/**
 * Qubit Compatibility Layer
 *
 * Provides static methods that were in the core Qubit class.
 */
if (!class_exists('Qubit', false)) {
    class Qubit
    {
        /**
         * Extract path info from URL for routing.
         */
        public static function pathInfo($url)
        {
            // If sfContext is available, use the proper method
            if (class_exists('sfContext') && \sfContext::hasInstance()) {
                $prefix = \sfContext::getInstance()->getRequest()->getPathInfoPrefix();
                return preg_replace('/^(?:[^:]+:\/\/[^\/]+)?' . preg_quote($prefix, '/') . '/', null, $url);
            }
            // Fallback: just extract path from URL
            $parsed = parse_url($url);
            return $parsed['path'] ?? $url;
        }

        /**
         * Render date with start/end range.
         */
        public static function renderDateStartEnd(?string $date, ?string $startDate, ?string $endDate): string
        {
            return \AtomExtensions\Helpers\DateHelper::renderDateStartEnd($date, $startDate, $endDate);
        }

        /**
         * Render a single date.
         */
        public static function renderDate(?string $date): string
        {
            if (empty($date)) {
                return '';
            }
            // Check if it's just a year
            if (preg_match('/^\d{4}$/', $date)) {
                return $date;
            }
            // Try to parse and format
            $timestamp = strtotime($date);
            if ($timestamp) {
                // Use locale-aware formatting if available
                if (class_exists('sfContext') && \sfContext::hasInstance()) {
                    $culture = \sfContext::getInstance()->getUser()->getCulture();
                    if ('en' === $culture) {
                        return date('F j, Y', $timestamp);
                    }
                }
                return date('Y-m-d', $timestamp);
            }
            return $date;
        }

        /**
         * Render value with escaping.
         */
        public static function renderValue(?string $value): string
        {
            return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        }

        /**
         * Clear all class-level caches.
         *
         * Clears Propel's static instance pools to prevent memory issues
         * and stale data in background jobs and long-running processes.
         * Called by arBaseJob after completing database operations.
         */
        public static function clearClassCaches()
        {
            $tables = [
                'QubitActor',
                'QubitInformationObject',
                'QubitRepository',
                'QubitTerm',
                'QubitTaxonomy',
                'QubitNote',
                'QubitProperty',
                'QubitRelation',
                'QubitEvent',
                'QubitContactInformation',
                'QubitOtherName',
                'QubitObjectTermRelation',
                'QubitStaticPage',
                'QubitMenu',
                'QubitSetting',
                'QubitDigitalObject',
                'QubitPhysicalObject',
                'QubitAccession',
                'QubitDeaccession',
                'QubitDonor',
                'QubitRightsHolder',
                'QubitRights',
                'QubitGrantedRight',
                'QubitFunction',
                'QubitFunctionObject',
                'QubitUser',
                'QubitAclGroup',
                'QubitAclPermission',
                'QubitAclUserGroup',
                'QubitJob',
                'QubitAip',
                'QubitPremisObject',
                'QubitSlug',
            ];

            foreach ($tables as $table) {
                $peerClass = $table . 'Peer';
                if (class_exists($peerClass, false) && method_exists($peerClass, 'clearInstancePool')) {
                    $peerClass::clearInstancePool();
                }

                // Also clear I18n pools if they exist
                $i18nPeerClass = $table . 'I18nPeer';
                if (class_exists($i18nPeerClass, false) && method_exists($i18nPeerClass, 'clearInstancePool')) {
                    $i18nPeerClass::clearInstancePool();
                }
            }

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
}
