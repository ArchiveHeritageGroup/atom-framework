<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\ZoomPan;

use AtomExtensions\Extensions\ZoomPan\Repositories\ZoomPanRepository;
use AtomExtensions\Extensions\ZoomPan\Services\ZoomPanService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Zoom-Pan Adapter.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ZoomPanAdapter
{
    private static ?ZoomPanService $service = null;

    /**
     * Get service instance.
     */
    public static function getService(): ZoomPanService
    {
        if (self::$service === null) {
            $repository = new ZoomPanRepository();

            $logger = new Logger('zoom-pan');
            $logger->pushHandler(
                new StreamHandler(
                    sfConfig::get('sf_log_dir', '/var/log/atom') . '/zoom-pan.log',
                    Logger::INFO
                )
            );

            self::$service = new ZoomPanService($repository, $logger);
        }

        return self::$service;
    }

    /**
     * Check if digital object supports zoom-pan.
     */
    public static function supportsZoomPan(int $digitalObjectId): bool
    {
        return self::getService()->supportsZoomPan($digitalObjectId);
    }

    /**
     * Get viewer configuration.
     */
    public static function getViewerConfig(int $digitalObjectId): array
    {
        return self::getService()->getViewerConfig($digitalObjectId);
    }
}
