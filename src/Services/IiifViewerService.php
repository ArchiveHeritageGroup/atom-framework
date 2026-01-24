<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * @deprecated This class has been moved to AhgIiif\Services\IiifViewerService
 * 
 * This stub remains for backward compatibility. Please update your code to use:
 * use AhgIiif\Services\IiifViewerService;
 * 
 * This file will be removed in a future version.
 */
class_alias(\AhgIiif\Services\IiifViewerService::class, IiifViewerService::class);

// Trigger deprecation notice when this file is included directly
trigger_error(
    'AtomFramework\Services\IiifViewerService is deprecated. Use AhgIiif\Services\IiifViewerService instead.',
    E_USER_DEPRECATED
);
