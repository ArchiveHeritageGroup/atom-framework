<?php

// Dont define if were in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}

use Illuminate\Database\Capsule\Manager as DB;

/**
 * QubitOai Compatibility Layer
 */
if (!class_exists('QubitOai', false)) {
    class QubitOai
    {
        public static function getRepositoryIdentifier(): string
        {
            $setting = DB::table('setting as s')
                ->leftJoin('setting_i18n as si', 's.id', '=', 'si.id')
                ->where('s.name', 'oai_repository_identifier')
                ->value('si.value');
            
            if ($setting) {
                return $setting;
            }
            
            $siteUrl = DB::table('setting as s')
                ->leftJoin('setting_i18n as si', 's.id', '=', 'si.id')
                ->where('s.name', 'siteBaseUrl')
                ->value('si.value');
            
            if ($siteUrl) {
                return parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost';
            }
            
            return 'localhost';
        }
        
        public static function getOaiSampleIdentifier(): string
        {
            $repoId = self::getRepositoryIdentifier();
            return 'oai:' . $repoId . ':1';
        }
    }
}
