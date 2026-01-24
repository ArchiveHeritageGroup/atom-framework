<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Interface for plugins that provide metadata template support.
 *
 * Plugins implementing this interface can register their metadata template
 * routing dynamically instead of being hardcoded in QubitMetadataRoute.
 *
 * Example implementation in plugin configuration:
 *
 * class ahgMuseumPluginConfiguration extends sfPluginConfiguration
 *     implements \AtomExtensions\Contracts\MetadataTemplateProviderInterface
 * {
 *     public function getTemplateCode(): string
 *     {
 *         return 'museum';
 *     }
 *
 *     public function getPluginName(): string
 *     {
 *         return 'ahgMuseumPlugin';
 *     }
 *
 *     public function getModuleName(): string
 *     {
 *         return 'museum';
 *     }
 * }
 */
interface MetadataTemplateProviderInterface
{
    /**
     * Get the template code (e.g., 'museum', 'dam', 'isad').
     */
    public function getTemplateCode(): string;

    /**
     * Get the plugin name for routing.
     */
    public function getPluginName(): string;

    /**
     * Get the module name for this template.
     */
    public function getModuleName(): string;
}
