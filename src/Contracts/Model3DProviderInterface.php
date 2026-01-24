<?php

declare(strict_types=1);

namespace AtomFramework\Contracts;

/**
 * Interface for 3D model viewer providers.
 *
 * Plugins that provide 3D model viewing capabilities should implement this.
 * Register via: AtomFramework\Providers::register('model_3d', $implementation)
 */
interface Model3DProviderInterface
{
    /**
     * Check if a digital object is a 3D model.
     *
     * @param int $digitalObjectId The digital object ID
     * @return bool
     */
    public function is3dModel(int $digitalObjectId): bool;

    /**
     * Get viewer configuration for 3D model.
     *
     * @param int $digitalObjectId The digital object ID
     * @return array ['viewer' => string, 'config' => array, 'formats' => array]
     */
    public function getViewerConfig(int $digitalObjectId): array;

    /**
     * Get supported 3D formats.
     *
     * @return array List of supported MIME types or extensions
     */
    public function getSupportedFormats(): array;

    /**
     * Generate a thumbnail for a 3D model.
     *
     * @param int $digitalObjectId The digital object ID
     * @param array $options Thumbnail options (width, height, angle)
     * @return array ['success' => bool, 'path' => ?string, 'error' => ?string]
     */
    public function generateThumbnail(int $digitalObjectId, array $options = []): array;
}
