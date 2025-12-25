<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Ar3dViewer\Services;

use AtomExtensions\Extensions\Ar3dViewer\Contracts\Ar3dServiceInterface;
use AtomExtensions\Extensions\Ar3dViewer\Repositories\Ar3dRepository;
use Psr\Log\LoggerInterface;

/**
 * AR3D Service.
 *
 * Handles 3D model viewing functionality.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class Ar3dService implements Ar3dServiceInterface
{
    private const SUPPORTED_FORMATS = [
        'model/gltf-binary' => 'glb',
        'model/gltf+json' => 'gltf',
        'model/obj' => 'obj',
        'model/stl' => 'stl',
        'model/fbx' => 'fbx',
    ];

    public function __construct(
        private readonly Ar3dRepository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if digital object is a 3D model.
     */
    public function is3dModel(int $digitalObjectId): bool
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            return false;
        }

        // Check mime type
        if (!isset(self::SUPPORTED_FORMATS[$digitalObject->mime_type])) {
            return false;
        }

        // Check if file exists
        return $this->repository->fileExists($digitalObject->path);
    }

    /**
     * Get viewer configuration for 3D model.
     */
    public function getViewerConfig(int $digitalObjectId): array
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $format = self::SUPPORTED_FORMATS[$digitalObject->mime_type] ?? null;

        if (!$format) {
            throw new \RuntimeException("Unsupported 3D format: {$digitalObject->mime_type}");
        }

        return [
            'modelUrl' => '/' . $digitalObject->path,
            'format' => $format,
            'mimeType' => $digitalObject->mime_type,
            'name' => $digitalObject->name,
            'size' => $digitalObject->byte_size,
        ];
    }

    /**
     * Get supported 3D formats.
     */
    public function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }
}
