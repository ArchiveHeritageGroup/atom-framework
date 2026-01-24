<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * @deprecated This class has been moved to Ahg3DModel\Services\ThreeDAutoConfigService
 * 
 * This stub remains for backward compatibility. Please update your code to use:
 * use Ahg3DModel\Services\ThreeDAutoConfigService;
 * 
 * This file will be removed in a future version.
 */
class_alias(\Ahg3DModel\Services\ThreeDAutoConfigService::class, ThreeDAutoConfigService::class);

// Trigger deprecation notice when this file is included directly
trigger_error(
    'AtomFramework\Services\ThreeDAutoConfigService is deprecated. Use Ahg3DModel\Services\ThreeDAutoConfigService instead.',
    E_USER_DEPRECATED
);
