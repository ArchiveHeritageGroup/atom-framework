<?php

declare(strict_types=1);

namespace AtomExtensions\Contracts;

/**
 * Interface for plugins that provide GLAM sector support.
 *
 * Plugins implementing this interface can register their sector support
 * dynamically instead of being hardcoded in the framework.
 *
 * Example implementation in plugin configuration:
 *
 * class ahgMuseumPluginConfiguration extends sfPluginConfiguration
 *     implements \AtomExtensions\Contracts\SectorProviderInterface
 * {
 *     public function getSectorCode(): string
 *     {
 *         return 'museum';
 *     }
 *
 *     public function getSectorLabel(): string
 *     {
 *         return 'Museum';
 *     }
 *
 *     public function getDefaultLevels(): array
 *     {
 *         return ['Artifact', 'Object', 'Specimen'];
 *     }
 * }
 */
interface SectorProviderInterface
{
    /**
     * Get the sector code (e.g., 'museum', 'library', 'gallery', 'dam').
     */
    public function getSectorCode(): string;

    /**
     * Get the human-readable sector label.
     */
    public function getSectorLabel(): string;

    /**
     * Get default levels of description for this sector.
     *
     * @return string[] Array of level names
     */
    public function getDefaultLevels(): array;
}
