<?php

declare(strict_types=1);

namespace AtomFramework\Contracts;

/**
 * Interface for IIIF manifest providers.
 *
 * Plugins that provide IIIF functionality should implement this.
 * Register via: AtomFramework\Providers::register('iiif', $implementation)
 */
interface IiifProviderInterface
{
    /**
     * Generate a IIIF manifest for an object.
     *
     * @param int $objectId The information object ID
     * @param array $options Manifest generation options
     * @return array IIIF 3.0 compliant manifest
     */
    public function generateManifest(int $objectId, array $options = []): array;

    /**
     * Check if IIIF viewer is available for an object.
     *
     * @param int $objectId The information object ID
     * @return bool
     */
    public function hasViewer(int $objectId): bool;

    /**
     * Get viewer URL for an object.
     *
     * @param int $objectId The information object ID
     * @return string|null
     */
    public function getViewerUrl(int $objectId): ?string;
}
