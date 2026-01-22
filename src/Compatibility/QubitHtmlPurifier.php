<?php

// Dont define if were in Symfony context - let core handle it
if (defined('SF_ROOT_DIR')) {
    return;
}

/**
 * QubitHtmlPurifier Compatibility Layer
 */
if (!class_exists('QubitHtmlPurifier', false)) {
    class QubitHtmlPurifier
    {
        private static ?\HTMLPurifier $instance = null;
        
        public static function getInstance(): \HTMLPurifier
        {
            if (self::$instance === null) {
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('HTML.Allowed', 'p,br,strong,em,a[href],ul,ol,li,blockquote');
                $config->set('AutoFormat.AutoParagraph', true);
                $config->set('Cache.SerializerPath', sys_get_temp_dir());
                self::$instance = new \HTMLPurifier($config);
            }
            return self::$instance;
        }
        
        public static function clean(?string $html): string
        {
            if (empty($html)) {
                return '';
            }
            return self::getInstance()->purify($html);
        }
    }
}
