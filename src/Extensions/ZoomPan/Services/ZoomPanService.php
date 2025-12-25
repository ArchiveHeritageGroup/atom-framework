<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\ZoomPan\Services;

use AtomExtensions\Extensions\ZoomPan\Contracts\ZoomPanServiceInterface;
use AtomExtensions\Extensions\ZoomPan\Repositories\ZoomPanRepository;
use Psr\Log\LoggerInterface;

/**
 * Zoom-Pan Service.
 *
 * Provides zoom and pan functionality for large images.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ZoomPanService implements ZoomPanServiceInterface
{
    private const MIN_WIDTH = 800;
    private const MIN_HEIGHT = 600;

    public function __construct(
        private readonly ZoomPanRepository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if digital object supports zoom-pan.
     */
    public function supportsZoomPan(int $digitalObjectId): bool
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            return false;
        }

        // Check if it's an image
        if (!str_starts_with($digitalObject->mime_type ?? '', 'image/')) {
            return false;
        }

        // Check dimensions
        $dimensions = $this->repository->getImageDimensions($digitalObject->path);

        if (!$dimensions) {
            return false;
        }

        // Image must be large enough to benefit from zoom-pan
        return $dimensions['width'] >= self::MIN_WIDTH
            && $dimensions['height'] >= self::MIN_HEIGHT;
    }

    /**
     * Get viewer configuration.
     */
    public function getViewerConfig(int $digitalObjectId): array
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $dimensions = $this->repository->getImageDimensions($digitalObject->path);

        if (!$dimensions) {
            throw new \RuntimeException('Cannot read image dimensions');
        }

        return [
            'imageUrl' => '/' . $digitalObject->path,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'mimeType' => $digitalObject->mime_type,
            'name' => $digitalObject->name,
        ];
    }

    /**
     * Get image tiles configuration (for very large images).
     */
    public function getTilesConfig(int $digitalObjectId): ?array
    {
        // Placeholder for future tile generation
        // For now, zoom-pan works with full images
        return null;
    }
}
