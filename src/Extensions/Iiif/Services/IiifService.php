<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Iiif\Services;

use AtomExtensions\Extensions\Iiif\Contracts\IiifServiceInterface;
use AtomExtensions\Extensions\Iiif\Repositories\IiifRepository;
use Psr\Log\LoggerInterface;

/**
 * IIIF Service.
 *
 * Handles IIIF manifest generation and image serving.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class IiifService implements IiifServiceInterface
{
    private string $cantaloupeBaseUrl;

    public function __construct(
        private readonly IiifRepository $repository,
        private readonly LoggerInterface $logger,
        ?string $cantaloupeBaseUrl = null
    ) {
        $this->cantaloupeBaseUrl = $cantaloupeBaseUrl
            ?? sfConfig::get('app_iiif_cantaloupe_url', 'http://localhost:8182/iiif/2');
    }

    /**
     * Generate IIIF manifest for a digital object.
     */
    public function generateManifest(int $digitalObjectId, string $culture = 'en'): array
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $informationObject = $this->repository->getInformationObject($digitalObjectId, $culture);

        if (!$informationObject) {
            throw new \RuntimeException("Information object not found for digital object {$digitalObjectId}");
        }

        // Build manifest according to IIIF Presentation API 2.1
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@type' => 'sc:Manifest',
            '@id' => $this->getManifestUrl($digitalObjectId),
            'label' => $informationObject->title ?? 'Untitled',
            'description' => $informationObject->scope_and_content ?? '',
            'metadata' => $this->buildMetadata($informationObject),
            'sequences' => [
                [
                    '@type' => 'sc:Sequence',
                    'canvases' => $this->buildCanvases($digitalObject),
                ],
            ],
        ];

        return $manifest;
    }

    /**
     * Get IIIF image info.
     */
    public function getImageInfo(int $digitalObjectId): array
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            throw new \RuntimeException("Digital object {$digitalObjectId} not found");
        }

        $dimensions = $this->repository->getImageDimensions($digitalObject->path);

        if (!$dimensions) {
            throw new \RuntimeException("Cannot read image dimensions for digital object {$digitalObjectId}");
        }

        // Build IIIF Image API 2.1 info.json
        $imageId = $this->getImageIdentifier($digitalObject->path);

        return [
            '@context' => 'http://iiif.io/api/image/2/context.json',
            '@id' => "{$this->cantaloupeBaseUrl}/{$imageId}",
            'protocol' => 'http://iiif.io/api/image',
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'profile' => [
                'http://iiif.io/api/image/2/level2.json',
                [
                    'formats' => ['jpg', 'png', 'webp'],
                    'qualities' => ['default', 'gray'],
                    'supports' => [
                        'regionByPct',
                        'regionByPx',
                        'sizeByForcedWh',
                        'sizeByWh',
                        'sizeAboveFull',
                        'rotationBy90s',
                        'mirroring',
                    ],
                ],
            ],
        ];
    }

    /**
     * Check if digital object supports IIIF.
     */
    public function supportsIiif(int $digitalObjectId): bool
    {
        $digitalObject = $this->repository->getDigitalObject($digitalObjectId);

        if (!$digitalObject) {
            return false;
        }

        // Check if it's an image
        if (!str_starts_with($digitalObject->mime_type ?? '', 'image/')) {
            return false;
        }

        // Check if file exists
        return $this->repository->imageExists($digitalObject->path);
    }

    /**
     * Build metadata array for manifest.
     */
    private function buildMetadata(object $informationObject): array
    {
        $metadata = [];

        if ($informationObject->identifier) {
            $metadata[] = [
                'label' => 'Identifier',
                'value' => $informationObject->identifier,
            ];
        }

        return $metadata;
    }

    /**
     * Build canvases for manifest.
     */
    private function buildCanvases(object $digitalObject): array
    {
        $dimensions = $this->repository->getImageDimensions($digitalObject->path);

        if (!$dimensions) {
            return [];
        }

        $imageId = $this->getImageIdentifier($digitalObject->path);
        $canvasId = $this->getCanvasUrl($digitalObject->id);

        return [
            [
                '@type' => 'sc:Canvas',
                '@id' => $canvasId,
                'label' => $digitalObject->name ?? 'Image',
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'images' => [
                    [
                        '@type' => 'oa:Annotation',
                        'motivation' => 'sc:painting',
                        'resource' => [
                            '@id' => "{$this->cantaloupeBaseUrl}/{$imageId}/full/full/0/default.jpg",
                            '@type' => 'dctypes:Image',
                            'format' => 'image/jpeg',
                            'width' => $dimensions['width'],
                            'height' => $dimensions['height'],
                            'service' => [
                                '@context' => 'http://iiif.io/api/image/2/context.json',
                                '@id' => "{$this->cantaloupeBaseUrl}/{$imageId}",
                                'profile' => 'http://iiif.io/api/image/2/level2.json',
                            ],
                        ],
                        'on' => $canvasId,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get image identifier for Cantaloupe.
     */
    private function getImageIdentifier(string $path): string
    {
        // Encode path for use as IIIF identifier
        return str_replace('/', '%2F', ltrim($path, '/'));
    }

    /**
     * Get manifest URL.
     */
    private function getManifestUrl(int $digitalObjectId): string
    {
        return sfConfig::get('app_siteBaseUrl') . "/iiif/{$digitalObjectId}/manifest.json";
    }

    /**
     * Get canvas URL.
     */
    private function getCanvasUrl(int $digitalObjectId): string
    {
        return sfConfig::get('app_siteBaseUrl') . "/iiif/{$digitalObjectId}/canvas/1";
    }
}
