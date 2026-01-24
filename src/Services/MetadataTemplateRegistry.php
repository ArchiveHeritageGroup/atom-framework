<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Contracts\MetadataTemplateProviderInterface;

/**
 * Registry for metadata template providers.
 *
 * Plugins register their metadata template support here instead of being
 * hardcoded in QubitMetadataRoute::$METADATA_PLUGINS.
 *
 * Usage:
 *   // In plugin configuration initialize():
 *   MetadataTemplateRegistry::register($this);
 *
 *   // To get plugin for a template:
 *   $plugin = MetadataTemplateRegistry::getPluginForTemplate('museum');
 */
class MetadataTemplateRegistry
{
    /**
     * Registered template providers.
     *
     * @var array<string, MetadataTemplateProviderInterface>
     */
    private static array $providers = [];

    /**
     * Core templates that are part of base AtoM.
     *
     * @var array<string, array{plugin: string, module: string}>
     */
    private static array $coreTemplates = [
        'dc' => ['plugin' => 'sfDcPlugin', 'module' => 'sfDcPlugin'],
        'isad' => ['plugin' => 'sfIsadPlugin', 'module' => 'sfIsadPlugin'],
        'isdf' => ['plugin' => 'sfIsdfPlugin', 'module' => 'sfIsdfPlugin'],
        'isdiah' => ['plugin' => 'sfIsdiahPlugin', 'module' => 'sfIsdiahPlugin'],
        'mods' => ['plugin' => 'sfModsPlugin', 'module' => 'sfModsPlugin'],
        'rad' => ['plugin' => 'sfRadPlugin', 'module' => 'sfRadPlugin'],
        'skos' => ['plugin' => 'sfSkosPlugin', 'module' => 'sfSkosPlugin'],
    ];

    /**
     * Register a metadata template provider.
     */
    public static function register(MetadataTemplateProviderInterface $provider): void
    {
        $code = $provider->getTemplateCode();
        self::$providers[$code] = $provider;
    }

    /**
     * Check if a template is available.
     */
    public static function hasTemplate(string $code): bool
    {
        return isset(self::$coreTemplates[$code]) || isset(self::$providers[$code]);
    }

    /**
     * Get plugin name for a template.
     */
    public static function getPluginForTemplate(string $code): ?string
    {
        if (isset(self::$coreTemplates[$code])) {
            return self::$coreTemplates[$code]['plugin'];
        }

        if (isset(self::$providers[$code])) {
            return self::$providers[$code]->getPluginName();
        }

        return null;
    }

    /**
     * Get module name for a template.
     */
    public static function getModuleForTemplate(string $code): ?string
    {
        if (isset(self::$coreTemplates[$code])) {
            return self::$coreTemplates[$code]['module'];
        }

        if (isset(self::$providers[$code])) {
            return self::$providers[$code]->getModuleName();
        }

        return null;
    }

    /**
     * Get all available template codes.
     *
     * @return string[]
     */
    public static function getTemplateCodes(): array
    {
        return array_unique(array_merge(
            array_keys(self::$coreTemplates),
            array_keys(self::$providers)
        ));
    }

    /**
     * Get all templates mapped to their plugins.
     * This provides the same format as the old QubitMetadataRoute::$METADATA_PLUGINS.
     *
     * @return array<string, string>
     */
    public static function getTemplatePluginMap(): array
    {
        $map = [];

        foreach (self::$coreTemplates as $code => $info) {
            $map[$code] = $info['plugin'];
        }

        foreach (self::$providers as $code => $provider) {
            $map[$code] = $provider->getPluginName();
        }

        return $map;
    }

    /**
     * Clear all registered providers (useful for testing).
     */
    public static function clear(): void
    {
        self::$providers = [];
    }
}
