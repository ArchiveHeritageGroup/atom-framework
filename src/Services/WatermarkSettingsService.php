<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use AhgCore\Services\WatermarkSettingsService as CoreWatermarkSettingsService;

/**
 * @deprecated since 2.0.0, use AhgCore\Services\WatermarkSettingsService instead.
 * This class is provided for backward compatibility only.
 */
class WatermarkSettingsService extends CoreWatermarkSettingsService
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Services\WatermarkSettingsService is deprecated. Use AhgCore\Services\WatermarkSettingsService instead.',
            E_USER_DEPRECATED
        );
    }
}
