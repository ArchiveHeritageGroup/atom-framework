<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\ZoomPan\Contracts;

/**
 * Zoom-Pan Service Interface.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
interface ZoomPanServiceInterface
{
    /**
     * Check if digital object supports zoom-pan.
     */
    public function supportsZoomPan(int $digitalObjectId): bool;

    /**
     * Get viewer configuration.
     */
    public function getViewerConfig(int $digitalObjectId): array;

    /**
     * Get image tiles configuration.
     */
    public function getTilesConfig(int $digitalObjectId): ?array;
}
