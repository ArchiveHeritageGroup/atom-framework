<?php

/**
 * @deprecated since 2.0.0, use AhgCore\Services\DerivativeWatermarkService instead.
 * This class is provided for backward compatibility only.
 */

namespace AtomExtensions\Services;

use AhgCore\Services\DerivativeWatermarkService as CoreDerivativeWatermarkService;

class DerivativeWatermarkService extends CoreDerivativeWatermarkService
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Services\DerivativeWatermarkService is deprecated. Use AhgCore\Services\DerivativeWatermarkService instead.',
            E_USER_DEPRECATED
        );
    }
}
