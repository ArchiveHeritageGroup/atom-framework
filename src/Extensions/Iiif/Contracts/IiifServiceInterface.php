<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Iiif\Contracts;

/**
 * IIIF Service Interface.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
interface IiifServiceInterface
{
    /**
     * Generate IIIF manifest for a digital object.
     */
    public function generateManifest(int $digitalObjectId, string $culture = 'en'): array;

    /**
     * Get IIIF image info.
     */
    public function getImageInfo(int $digitalObjectId): array;

    /**
     * Check if digital object supports IIIF.
     */
    public function supportsIiif(int $digitalObjectId): bool;
}
