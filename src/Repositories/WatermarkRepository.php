<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use AhgCore\Repositories\WatermarkRepository as CoreWatermarkRepository;

/**
 * @deprecated since 2.0.0, use AhgCore\Repositories\WatermarkRepository instead.
 * This class is provided for backward compatibility only.
 */
class WatermarkRepository extends CoreWatermarkRepository
{
    public function __construct()
    {
        trigger_error(
            'AtomExtensions\Repositories\WatermarkRepository is deprecated. Use AhgCore\Repositories\WatermarkRepository instead.',
            E_USER_DEPRECATED
        );
    }
}
