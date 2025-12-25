<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Ar3dViewer\Contracts;

/**
 * AR3D Service Interface.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
interface Ar3dServiceInterface
{
    /**
     * Check if digital object is a 3D model.
     */
    public function is3dModel(int $digitalObjectId): bool;

    /**
     * Get viewer configuration for 3D model.
     */
    public function getViewerConfig(int $digitalObjectId): array;

    /**
     * Get supported 3D formats.
     */
    public function getSupportedFormats(): array;
}
