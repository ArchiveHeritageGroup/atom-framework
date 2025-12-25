<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Ar3dViewer;

use AtomExtensions\Extensions\Ar3dViewer\Repositories\Ar3dRepository;
use AtomExtensions\Extensions\Ar3dViewer\Services\Ar3dService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * AR3D Adapter.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class Ar3dAdapter
{
    private static ?Ar3dService $service = null;

    /**
     * Get service instance.
     */
    public static function getService(): Ar3dService
    {
        if (self::$service === null) {
            $repository = new Ar3dRepository();

            $logger = new Logger('ar3d');
            $logger->pushHandler(
                new StreamHandler(
                    sfConfig::get('sf_log_dir', '/var/log/atom') . '/ar3d.log',
                    Logger::INFO
                )
            );

            self::$service = new Ar3dService($repository, $logger);
        }

        return self::$service;
    }

    /**
     * Check if digital object is 3D model.
     */
    public static function is3dModel(int $digitalObjectId): bool
    {
        return self::getService()->is3dModel($digitalObjectId);
    }

    /**
     * Get viewer configuration.
     */
    public static function getViewerConfig(int $digitalObjectId): array
    {
        return self::getService()->getViewerConfig($digitalObjectId);
    }
}
