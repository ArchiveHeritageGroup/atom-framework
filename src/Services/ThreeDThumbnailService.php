<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * @deprecated This class has been moved to Ahg3DModel\Services\ThreeDThumbnailService
 * 
 * This stub remains for backward compatibility. Please update your code to use:
 * use Ahg3DModel\Services\ThreeDThumbnailService;
 * 
 * This file will be removed in a future version.
 */
class_alias(\Ahg3DModel\Services\ThreeDThumbnailService::class, ThreeDThumbnailService::class);

// Trigger deprecation notice when this file is included directly
trigger_error(
    'AtomExtensions\Services\ThreeDThumbnailService is deprecated. Use Ahg3DModel\Services\ThreeDThumbnailService instead.',
    E_USER_DEPRECATED
);
