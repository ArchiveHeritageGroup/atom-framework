<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Iiif;

use AtomExtensions\Extensions\Iiif\Repositories\IiifRepository;
use AtomExtensions\Extensions\Iiif\Services\IiifService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * IIIF Adapter.
 *
 * Provides backward compatibility with existing IIIF plugin.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class IiifAdapter
{
    private static ?IiifService $service = null;

    /**
     * Get IIIF service instance.
     */
    public static function getService(): IiifService
    {
        if (self::$service === null) {
            $repository = new IiifRepository();

            $logger = new Logger('iiif');
            $logger->pushHandler(
                new StreamHandler(
                    sfConfig::get('sf_log_dir', '/var/log/atom') . '/iiif.log',
                    Logger::INFO
                )
            );

            self::$service = new IiifService($repository, $logger);
        }

        return self::$service;
    }

    /**
     * Generate manifest for digital object.
     */
    public static function generateManifest(int $digitalObjectId, string $culture = 'en'): array
    {
        return self::getService()->generateManifest($digitalObjectId, $culture);
    }

    /**
     * Get image info for digital object.
     */
    public static function getImageInfo(int $digitalObjectId): array
    {
        return self::getService()->getImageInfo($digitalObjectId);
    }

    /**
     * Check if digital object supports IIIF.
     */
    public static function supportsIiif(int $digitalObjectId): bool
    {
        return self::getService()->supportsIiif($digitalObjectId);
    }
}
