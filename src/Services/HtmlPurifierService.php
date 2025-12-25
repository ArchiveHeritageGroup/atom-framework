<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * HTML Purifier Service - Replaces QubitHtmlPurifier.
 *
 * Provides HTML sanitization to prevent XSS attacks.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class HtmlPurifierService
{
    private static ?self $instance = null;
    private ?\HTMLPurifier $purifier = null;

    private function __construct()
    {
        $this->initPurifier();
    }

    /**
     * Get singleton instance.
     *
     * Replaces: QubitHtmlPurifier::getInstance()
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize HTMLPurifier with safe defaults.
     */
    private function initPurifier(): void
    {
        if (!class_exists('\HTMLPurifier')) {
            // Fallback if HTMLPurifier not installed
            return;
        }

        $config = \HTMLPurifier_Config::createDefault();

        // Configure allowed HTML
        $config->set('HTML.Allowed', 'p,br,b,i,strong,em,a[href|title],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,pre,code');
        $config->set('HTML.AllowedAttributes', 'a.href,a.title');
        $config->set('AutoFormat.Linkify', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        // Cache configuration
        $cacheDir = sfConfig::get('sf_cache_dir', '/tmp') . '/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Purify HTML string.
     *
     * Replaces: QubitHtmlPurifier::getInstance()->purify($html)
     */
    public function purify(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        if ($this->purifier) {
            return $this->purifier->purify($html);
        }

        // Fallback: strip all tags except basic formatting
        return strip_tags($html, '<p><br><b><i><strong><em><a><ul><ol><li>');
    }

    /**
     * Static purify method for convenience.
     */
    public static function clean(?string $html): string
    {
        return self::getInstance()->purify($html);
    }

    /**
     * Strip all HTML tags.
     */
    public static function stripAll(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        return strip_tags($html);
    }
}
