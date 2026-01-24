<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AhgCore\Services\WatermarkService as CoreWatermarkService;

/**
 * @deprecated since 2.0.0, use AhgCore\Services\WatermarkService instead.
 * This class is provided for backward compatibility only.
 */
class WatermarkService extends CoreWatermarkService
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Services\WatermarkService is deprecated. Use AhgCore\Services\WatermarkService instead.',
            E_USER_DEPRECATED
        );
    }
}
