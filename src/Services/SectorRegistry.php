<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AtomExtensions\Contracts\SectorProviderInterface;

/**
 * Registry for GLAM sector providers.
 *
 * Plugins register their sector support here instead of being hardcoded
 * in LevelOfDescriptionService::SECTOR_PLUGINS.
 *
 * Usage:
 *   // In plugin configuration initialize():
 *   SectorRegistry::register($this);
 *
 *   // To get all registered sectors:
 *   $sectors = SectorRegistry::getSectors();
 *
 *   // To check if a sector is available:
 *   if (SectorRegistry::hasSector('museum')) { ... }
 */
class SectorRegistry
{
    /**
     * Registered sector providers.
     *
     * @var array<string, SectorProviderInterface>
     */
    private static array $providers = [];

    /**
     * Default sectors that are always available.
     *
     * @var array<string, array{label: string, levels: string[]}>
     */
    private static array $defaultSectors = [
        'archive' => [
            'label' => 'Archive',
            'levels' => ['Fonds', 'Sub-fonds', 'Series', 'Sub-series', 'File', 'Item'],
        ],
    ];

    /**
     * Register a sector provider.
     */
    public static function register(SectorProviderInterface $provider): void
    {
        $code = $provider->getSectorCode();
        self::$providers[$code] = $provider;
    }

    /**
     * Check if a sector is available (either default or registered).
     */
    public static function hasSector(string $code): bool
    {
        return isset(self::$defaultSectors[$code]) || isset(self::$providers[$code]);
    }

    /**
     * Get all available sector codes.
     *
     * @return string[]
     */
    public static function getSectorCodes(): array
    {
        return array_unique(array_merge(
            array_keys(self::$defaultSectors),
            array_keys(self::$providers)
        ));
    }

    /**
     * Get sector information.
     *
     * @return array{label: string, levels: string[]}|null
     */
    public static function getSector(string $code): ?array
    {
        if (isset(self::$defaultSectors[$code])) {
            return self::$defaultSectors[$code];
        }

        if (isset(self::$providers[$code])) {
            $provider = self::$providers[$code];

            return [
                'label' => $provider->getSectorLabel(),
                'levels' => $provider->getDefaultLevels(),
            ];
        }

        return null;
    }

    /**
     * Get all sectors with their information.
     *
     * @return array<string, array{label: string, levels: string[]}>
     */
    public static function getSectors(): array
    {
        $sectors = self::$defaultSectors;

        foreach (self::$providers as $code => $provider) {
            $sectors[$code] = [
                'label' => $provider->getSectorLabel(),
                'levels' => $provider->getDefaultLevels(),
            ];
        }

        return $sectors;
    }

    /**
     * Get default levels for a sector.
     *
     * @return string[]
     */
    public static function getLevels(string $code): array
    {
        $sector = self::getSector($code);

        return $sector['levels'] ?? [];
    }

    /**
     * Clear all registered providers (useful for testing).
     */
    public static function clear(): void
    {
        self::$providers = [];
    }
}
