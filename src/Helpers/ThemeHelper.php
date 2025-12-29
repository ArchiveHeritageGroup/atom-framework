<?php

namespace AtomFramework\Helpers;

class ThemeHelper
{
    protected static ?string $currentTheme = null;
    protected static array $supportedThemes = [
        'arDominionB5Plugin' => [
            'name' => 'Dominion B5',
            'css_prefix' => 'dominion',
            'bootstrap' => '5',
        ],
        'ahgThemeB5Plugin' => [
            'name' => 'AHG Theme B5',
            'css_prefix' => 'ahg',
            'bootstrap' => '5',
        ],
    ];

    /**
     * Detect current active theme
     */
    public static function detect(): string
    {
        if (self::$currentTheme !== null) {
            return self::$currentTheme;
        }

        // Check sfConfig if available (Symfony context)
        if (class_exists('sfConfig')) {
            $plugins = \sfConfig::get('sf_enabled_plugins', []);
            
            foreach (array_keys(self::$supportedThemes) as $theme) {
                if (in_array($theme, $plugins)) {
                    self::$currentTheme = $theme;
                    return $theme;
                }
            }
        }

        // Check plugins directory for enabled themes
        $pluginsPath = '/usr/share/nginx/archive/plugins';
        foreach (array_keys(self::$supportedThemes) as $theme) {
            $configFile = $pluginsPath . '/' . $theme . '/config/' . $theme . 'Configuration.class.php';
            if (file_exists($configFile)) {
                self::$currentTheme = $theme;
                return $theme;
            }
        }

        // Default to AHG theme
        self::$currentTheme = 'ahgThemeB5Plugin';
        return self::$currentTheme;
    }

    /**
     * Get current theme machine name
     */
    public static function current(): string
    {
        return self::detect();
    }

    /**
     * Get theme info
     */
    public static function getInfo(?string $theme = null): array
    {
        $theme = $theme ?? self::detect();
        return self::$supportedThemes[$theme] ?? [];
    }

    /**
     * Get CSS prefix for current theme
     */
    public static function getCssPrefix(?string $theme = null): string
    {
        $info = self::getInfo($theme);
        return $info['css_prefix'] ?? 'ahg';
    }

    /**
     * Check if theme is Bootstrap 5
     */
    public static function isBootstrap5(?string $theme = null): bool
    {
        $info = self::getInfo($theme);
        return ($info['bootstrap'] ?? '') === '5';
    }

    /**
     * Get template path for theme
     */
    public static function getTemplatePath(string $extensionPath, string $template, ?string $theme = null): string
    {
        $theme = $theme ?? self::detect();
        $prefix = self::getCssPrefix($theme);
        
        // Try theme-specific path first
        $themePath = $extensionPath . '/templates/' . $prefix . '/' . $template;
        if (file_exists($themePath)) {
            return $themePath;
        }

        // Try shared path
        $sharedPath = $extensionPath . '/templates/_shared/' . $template;
        if (file_exists($sharedPath)) {
            return $sharedPath;
        }

        // Fall back to default
        return $extensionPath . '/templates/' . $template;
    }

    /**
     * Get CSS file for theme
     */
    public static function getCssFile(string $extensionPath, ?string $theme = null): ?string
    {
        $prefix = self::getCssPrefix($theme);
        
        $cssFile = $extensionPath . '/css/' . $prefix . '.css';
        if (file_exists($cssFile)) {
            return $cssFile;
        }

        $mainCss = $extensionPath . '/css/main.css';
        if (file_exists($mainCss)) {
            return $mainCss;
        }

        return null;
    }

    /**
     * Check if extension supports theme
     */
    public static function extensionSupportsTheme(array $manifest, ?string $theme = null): bool
    {
        $theme = $theme ?? self::detect();
        $supported = $manifest['theme_support'] ?? [];
        
        // Empty means supports all
        if (empty($supported)) {
            return true;
        }

        return in_array($theme, $supported);
    }

    /**
     * Get all supported themes
     */
    public static function getAllThemes(): array
    {
        return self::$supportedThemes;
    }

    /**
     * Register a new theme
     */
    public static function registerTheme(string $machineName, array $info): void
    {
        self::$supportedThemes[$machineName] = $info;
    }

    /**
     * Set current theme (for testing)
     */
    public static function setTheme(string $theme): void
    {
        self::$currentTheme = $theme;
    }

    /**
     * Reset theme detection
     */
    public static function reset(): void
    {
        self::$currentTheme = null;
    }

    /**
     * Generate CSS variable for theme
     */
    public static function cssVar(string $name, ?string $fallback = null): string
    {
        $prefix = self::getCssPrefix();
        $var = "--ahg-{$name}";
        
        if ($fallback) {
            return "var({$var}, {$fallback})";
        }
        
        return "var({$var})";
    }
}
